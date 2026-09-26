#!/usr/bin/env python3
"""Validate an Opskeep Elliot Design security-baseline.yaml (schema 2.1).

Modes
  lint (default)          structure, allowed values, IDs, references, traceability,
                          lifecycle/approval agreement, lock and SPIKE rules.
  --require-approved      CI gate: additionally fail closed unless the baseline is
                          APPROVED for the current design digest and not expired.
  --print-digest          print the design digest and exit (use at approval time).

CI options (with --require-approved)
  --expected-digest HEX   digest from an independently protected source (signed
                          attestation, protected environment, approval service).
                          Strongly recommended: approval metadata edited in the same
                          change is not proof of approval.
  --target production     also deny PRODUCTION_PROHIBITED baselines and locked deploys.
  --stage STAGE           require PASSED evidence for every blocking gate at or
                          before STAGE (e.g. PRE_DEPLOY).

Exit codes: 0 valid, 1 validation/gate failure, 2 cannot read or parse.
Requires PyYAML (pip install pyyaml).
"""
import argparse
import copy
import datetime as dt
import hashlib
import json
import re
import sys

try:
    import yaml
except ImportError:  # pragma: no cover
    print("ERROR: PyYAML is required (pip install pyyaml)", file=sys.stderr)
    sys.exit(2)

SUPPORTED_SCHEMAS = {"2.1"}
LEVELS = ["LOW", "MEDIUM", "HIGH", "CRITICAL"]
MODES = ["FAST_TRACK", "STANDARD", "FULL", "SPIKE"]
MIN_MODE = {"LOW": "FAST_TRACK", "MEDIUM": "STANDARD", "HIGH": "FULL", "CRITICAL": "FULL"}
MODE_RANK = {"FAST_TRACK": 0, "STANDARD": 1, "FULL": 2}
LIFECYCLE = ["DRAFT", "REVIEW_REQUIRED", "APPROVED", "REJECTED", "SUSPENDED"]
POSTURE = ["ELIGIBLE_WHEN_APPROVED", "PRODUCTION_PROHIBITED"]
DEV_LOCK = ["LOCKED", "SANDBOX_ONLY", "UNLOCKED"]
DEPLOY_LOCK = ["LOCKED", "UNLOCKED"]
CLASSIFICATION = ["PUBLIC", "INTERNAL", "CONFIDENTIAL", "SENSITIVE", "RESTRICTED"]
EXPOSURE = ["INTERNET", "INTERNAL", "PRIVATE", "THIRD_PARTY"]
IDENTITY_TYPES = ["HUMAN", "WORKLOAD", "AGENT"]
THREAT_STATUS = ["OPEN", "MITIGATED", "ACCEPTED", "TRANSFERRED", "RETIRED"]
CONTROL_STATUS = ["REQUIRED", "PLANNED", "IMPLEMENTED", "VERIFIED", "NOT_APPLICABLE", "UNRESOLVED", "RETIRED"]
STAGES = ["PRE_IMPLEMENTATION", "PULL_REQUEST", "MERGE", "BUILD", "PRE_DEPLOY", "DEPLOY", "RUNTIME"]
GATE_STATUS = ["NOT_YET_BUILT", "CONFIGURED", "VERIFIED", "DISABLED", "RETIRED"]
DECISION_STATUS = ["OPEN", "DECIDED", "DEFERRED", "RETIRED"]
EVIDENCE_TYPES = ["OWNER_APPROVAL", "CI_RUN", "TEST_RESULT", "SCAN_REPORT", "CONFIG_INSPECTION", "MANUAL_REVIEW", "ATTESTATION"]
EVIDENCE_STATUS = ["NOT_YET_BUILT", "PENDING", "PASSED", "FAILED", "STALE"]
HANDOFF_STATUS = ["NOT_CONFIGURED", "PLANNED", "CONFIGURED", "VERIFIED"]
SIGNAL_STATUS = ["PLANNED", "CONFIGURED", "VERIFIED", "NOT_APPLICABLE", "RETIRED"]
CHANGE_TYPES = ["ADDED", "REMOVED", "MODIFIED"]
CHANGE_STATUS = ["PROPOSED", "ASSESSED", "APPROVED", "REJECTED"]
APPROVAL_DECISION = ["PENDING", "APPROVED", "REJECTED", "SUSPENDED"]
LIFECYCLE_TO_DECISION = {
    "DRAFT": "PENDING", "REVIEW_REQUIRED": "PENDING", "APPROVED": "APPROVED",
    "REJECTED": "REJECTED", "SUSPENDED": "SUSPENDED",
}
RECOVERY_ITEMS = ["backup", "restore", "rollback", "incident_response"]

TOP_LEVEL = [
    "schema_version", "baseline_version", "record_digest", "project", "risk", "lifecycle",
    "classification_levels", "assets", "components", "trust_boundaries", "data_flows",
    "identities", "threats", "controls", "gates", "decisions", "evidence", "monitoring",
    "recovery", "approval", "changes",
]
# collection -> required ID prefix
COLLECTIONS = {
    "assets": "AST-", "components": "CMP-", "trust_boundaries": "TB-", "data_flows": "FLW-",
    "identities": "ID-", "threats": "THR-", "controls": "CTL-", "gates": "GATE-",
    "decisions": "DEC-", "evidence": "EVD-", "changes": "CHG-",
}
SECRET_PATTERNS = [
    (re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----"), "private key block"),
    (re.compile(r"\bAKIA[0-9A-Z]{16}\b"), "AWS access key id"),
    (re.compile(r"\bgh[pousr]_[A-Za-z0-9]{30,}\b"), "GitHub token"),
    (re.compile(r"\bsk-(?:ant-|live_|proj-)?[A-Za-z0-9_-]{20,}\b"), "API secret key"),
    (re.compile(r"\bxox[abprs]-[A-Za-z0-9-]{10,}\b"), "Slack token"),
    (re.compile(r"(?i)\b(password|passwd|secret|api_key|token)\s*[:=]\s*['\"]?[^\s'\"]{8,}"), "credential assignment"),
]
SEMVER = re.compile(r"^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$")
HEX64 = re.compile(r"^[0-9a-f]{64}$")


class Report:
    def __init__(self):
        self.errors, self.warnings = [], []

    def err(self, msg):
        self.errors.append(msg)

    def warn(self, msg):
        self.warnings.append(msg)


def parse_date(value):
    if value is None:
        return None
    if isinstance(value, dt.datetime):
        return value if value.tzinfo else value.replace(tzinfo=dt.timezone.utc)
    if isinstance(value, dt.date):
        return dt.datetime(value.year, value.month, value.day, tzinfo=dt.timezone.utc)
    s = str(value).strip()
    if s.endswith("Z"):
        s = s[:-1] + "+00:00"
    parsed = dt.datetime.fromisoformat(s)  # raises ValueError if invalid
    return parsed if parsed.tzinfo else parsed.replace(tzinfo=dt.timezone.utc)


def _jsonable(obj):
    if isinstance(obj, dict):
        return {str(k): _jsonable(v) for k, v in obj.items()}
    if isinstance(obj, list):
        return [_jsonable(v) for v in obj]
    if isinstance(obj, (dt.date, dt.datetime)):
        return obj.isoformat()
    return obj


def design_digest(doc):
    """SHA-256 over the design, excluding volatile/implementation-progress fields."""
    d = copy.deepcopy(doc)
    for key in ("record_digest", "approval", "evidence"):
        d.pop(key, None)
    lc = d.get("lifecycle") or {}
    for key in ("status", "development_lock", "deployment_lock"):
        lc.pop(key, None)
    mon = d.get("monitoring") or {}
    mon.pop("handoff", None)
    for coll in ("controls", "gates", "threats"):
        for rec in d.get(coll) or []:
            if isinstance(rec, dict):
                rec.pop("status", None)
                rec.pop("evidence_ids", None)
    for rec in mon.get("signals") or []:
        if isinstance(rec, dict):
            rec.pop("status", None)
            rec.pop("evidence_ids", None)
    rec_block = d.get("recovery") or {}
    for item in RECOVERY_ITEMS:
        if isinstance(rec_block.get(item), dict):
            rec_block[item].pop("status", None)
    canonical = json.dumps(_jsonable(d), sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest()


def check_enum(r, where, value, allowed, required=True):
    if value is None:
        if required:
            r.err(f"{where}: missing (allowed: {', '.join(allowed)})")
        return
    if value not in allowed:
        r.err(f"{where}: '{value}' not allowed (allowed: {', '.join(allowed)})")


def active(rec):
    return rec.get("status") != "RETIRED"


def validate(doc, raw_text):
    r = Report()
    if not isinstance(doc, dict):
        r.err("document root must be a mapping")
        return r, {}

    for key in TOP_LEVEL:
        if key not in doc:
            r.err(f"missing top-level key '{key}'")
    for key in doc:
        if key not in TOP_LEVEL:
            r.warn(f"unknown top-level key '{key}'")

    if str(doc.get("schema_version")) not in SUPPORTED_SCHEMAS:
        r.err(f"schema_version '{doc.get('schema_version')}' unsupported (supported: {', '.join(SUPPORTED_SCHEMAS)})")
    if not SEMVER.match(str(doc.get("baseline_version", ""))):
        r.err("baseline_version must be semver (e.g. 1.0.0)")

    for pat, label in SECRET_PATTERNS:
        if pat.search(raw_text):
            r.err(f"possible secret in baseline ({label}); baselines must never store secrets")

    # ---- collections, IDs, index ----
    index = {}
    for coll, prefix in COLLECTIONS.items():
        items = doc.get(coll)
        if items is None:
            continue
        if not isinstance(items, list):
            r.err(f"{coll}: must be a list")
            continue
        for i, rec in enumerate(items):
            if not isinstance(rec, dict):
                r.err(f"{coll}[{i}]: must be a mapping")
                continue
            rid = rec.get("id")
            if not rid or not str(rid).startswith(prefix):
                r.err(f"{coll}[{i}]: id '{rid}' must start with '{prefix}'")
                continue
            if rid in index:
                r.err(f"duplicate id '{rid}'")
            index[rid] = (coll, rec)
    signals = ((doc.get("monitoring") or {}).get("signals")) or []
    for i, rec in enumerate(signals):
        rid = rec.get("id") if isinstance(rec, dict) else None
        if not rid or not str(rid).startswith("MON-"):
            r.err(f"monitoring.signals[{i}]: id '{rid}' must start with 'MON-'")
            continue
        if rid in index:
            r.err(f"duplicate id '{rid}'")
        index[rid] = ("signals", rec)

    def resolve(where, ids, expect=None):
        if ids is None:
            return []
        if not isinstance(ids, list):
            r.err(f"{where}: must be a list of IDs")
            return []
        out = []
        for ref in ids:
            if ref not in index:
                r.err(f"{where}: reference '{ref}' does not exist")
            elif expect and index[ref][0] != expect:
                r.err(f"{where}: '{ref}' is a {index[ref][0][:-1]}, expected {expect[:-1]}")
            else:
                out.append(index[ref][1])
        return out

    # ---- risk / lifecycle ----
    risk = doc.get("risk") or {}
    lc = doc.get("lifecycle") or {}
    appr = doc.get("approval") or {}
    tier, mode = risk.get("tier"), risk.get("mode")
    status = lc.get("status")
    check_enum(r, "risk.tier", tier, LEVELS)
    check_enum(r, "risk.mode", mode, MODES)
    check_enum(r, "lifecycle.status", status, LIFECYCLE)
    check_enum(r, "lifecycle.production_posture", lc.get("production_posture"), POSTURE)
    check_enum(r, "lifecycle.development_lock", lc.get("development_lock"), DEV_LOCK)
    check_enum(r, "lifecycle.deployment_lock", lc.get("deployment_lock"), DEPLOY_LOCK)
    check_enum(r, "risk.likely_production_tier", risk.get("likely_production_tier"), LEVELS, required=(mode == "SPIKE"))
    if "expires_at" in lc:
        r.err("lifecycle.expires_at was renamed in schema 2.1: use lifecycle.spike_expires_at (SPIKE sandbox) or approval.expires_at")

    strict = status not in (None, "DRAFT")
    trace = r.err if strict else r.warn

    if tier in MIN_MODE and mode in MODE_RANK and MODE_RANK[mode] < MODE_RANK[MIN_MODE[tier]]:
        r.err(f"risk.mode {mode} is below the minimum {MIN_MODE[tier]} for tier {tier}")
    if strict and not risk.get("rationale"):
        r.err("risk.rationale: state why this tier/mode was chosen")

    if mode == "SPIKE":
        if lc.get("production_posture") != "PRODUCTION_PROHIBITED":
            r.err("SPIKE requires production_posture PRODUCTION_PROHIBITED")
        if lc.get("deployment_lock") != "LOCKED":
            r.err("SPIKE requires deployment_lock LOCKED")
        if lc.get("development_lock") == "UNLOCKED":
            r.err("SPIKE development_lock must be SANDBOX_ONLY or LOCKED, never UNLOCKED")
        if not lc.get("spike_expires_at"):
            r.err("SPIKE requires lifecycle.spike_expires_at")
    else:
        if lc.get("development_lock") == "SANDBOX_ONLY":
            r.err("development_lock SANDBOX_ONLY is only valid for SPIKE")
        if lc.get("spike_expires_at") is not None:
            r.err("lifecycle.spike_expires_at must be null outside SPIKE")
    if lc.get("spike_expires_at") is not None:
        try:
            parse_date(lc["spike_expires_at"])
        except ValueError:
            r.err("lifecycle.spike_expires_at is not ISO 8601")

    if status != "APPROVED":
        if lc.get("development_lock") == "UNLOCKED":
            r.err(f"development_lock UNLOCKED while lifecycle is {status}")
        if lc.get("deployment_lock") == "UNLOCKED":
            r.err(f"deployment_lock UNLOCKED while lifecycle is {status}")
    if lc.get("deployment_lock") == "UNLOCKED" and lc.get("production_posture") == "PRODUCTION_PROHIBITED":
        r.err("deployment_lock UNLOCKED contradicts PRODUCTION_PROHIBITED")

    # ---- records ----
    for rec in doc.get("assets") or []:
        check_enum(r, f"{rec.get('id')}.classification", rec.get("classification"), CLASSIFICATION)
    for rec in doc.get("components") or []:
        check_enum(r, f"{rec.get('id')}.exposure", rec.get("exposure"), EXPOSURE)
    for rec in doc.get("trust_boundaries") or []:
        to = rec.get("to")
        if isinstance(to, list):
            resolve(f"{rec.get('id')}.to", to, "components")
    for rec in doc.get("data_flows") or []:
        check_enum(r, f"{rec.get('id')}.classification", rec.get("classification"), CLASSIFICATION)
        resolve(f"{rec.get('id')}.boundaries", rec.get("boundaries"), "trust_boundaries")
    for rec in doc.get("identities") or []:
        rid = rec.get("id")
        check_enum(r, f"{rid}.type", rec.get("type"), IDENTITY_TYPES)
        resolve(f"{rid}.resources", rec.get("resources"))
        if not rec.get("forbidden_actions"):
            trace(f"{rid}: list forbidden_actions (authentication never implies authorization)")

    evidence = {e.get("id"): e for e in doc.get("evidence") or [] if isinstance(e, dict)}
    for rid, e in evidence.items():
        check_enum(r, f"{rid}.type", e.get("type"), EVIDENCE_TYPES)
        check_enum(r, f"{rid}.status", e.get("status"), EVIDENCE_STATUS)
        if e.get("status") in ("PASSED", "FAILED", "STALE"):
            for f in ("observed_at", "observer", "location"):
                if not e.get(f):
                    r.err(f"{rid}: status {e.get('status')} requires '{f}' (never invent evidence)")
            try:
                parse_date(e.get("observed_at"))
            except (ValueError, TypeError):
                r.err(f"{rid}.observed_at is not ISO 8601")

    def has_passed(where, ids):
        recs = resolve(where, ids, "evidence")
        return bool(recs) and all(x.get("status") == "PASSED" for x in recs)

    referenced_controls = set()
    for t in doc.get("threats") or []:
        rid = t.get("id")
        check_enum(r, f"{rid}.status", t.get("status"), THREAT_STATUS)
        for f in ("likelihood", "impact", "residual_risk"):
            check_enum(r, f"{rid}.{f}", t.get(f), LEVELS)
        resolve(f"{rid}.component_ids", t.get("component_ids"), "components")
        ctls = resolve(f"{rid}.control_ids", t.get("control_ids"), "controls")
        referenced_controls.update(c.get("id") for c in ctls)
        if active(t) and not t.get("control_ids"):
            trace(f"{rid}: active threat has no controls")
        if t.get("status") == "MITIGATED" and any(c.get("status") not in ("IMPLEMENTED", "VERIFIED") for c in ctls):
            r.err(f"{rid}: MITIGATED requires all linked controls IMPLEMENTED or VERIFIED")
        if t.get("status") == "ACCEPTED" and not (t.get("owner") and t.get("acceptance_reason")):
            r.err(f"{rid}: ACCEPTED requires owner and acceptance_reason")

    gates = doc.get("gates") or []
    for g in gates:
        rid = g.get("id")
        check_enum(r, f"{rid}.stage", g.get("stage"), STAGES)
        check_enum(r, f"{rid}.status", g.get("status"), GATE_STATUS)
        if not isinstance(g.get("blocking"), bool):
            r.err(f"{rid}.blocking must be true or false")
        ctls = resolve(f"{rid}.control_ids", g.get("control_ids"), "controls")
        referenced_controls.update(c.get("id") for c in ctls)
        resolve(f"{rid}.required_evidence", g.get("required_evidence"), "evidence")
        if active(g):
            if not g.get("control_ids"):
                trace(f"{rid}: active gate traces to no control")
            if not g.get("required_evidence"):
                trace(f"{rid}: active gate lists no required evidence")
        if g.get("status") == "VERIFIED" and not has_passed(f"{rid}.evidence_ids", g.get("evidence_ids")):
            r.err(f"{rid}: VERIFIED requires evidence_ids that are all PASSED")
        if g.get("status") == "DISABLED" and g.get("blocking") and status == "APPROVED":
            r.err(f"{rid}: blocking gate is DISABLED on an APPROVED baseline")

    blocking_unresolved = []
    for c in doc.get("controls") or []:
        rid = c.get("id")
        st = c.get("status")
        check_enum(r, f"{rid}.status", st, CONTROL_STATUS)
        if st == "VERIFIED" and not has_passed(f"{rid}.evidence_ids", c.get("evidence_ids")):
            r.err(f"{rid}: VERIFIED requires evidence_ids that are all PASSED")
        if st == "NOT_APPLICABLE" and not c.get("reason"):
            r.err(f"{rid}: NOT_APPLICABLE requires a reason")
        if st == "UNRESOLVED":
            if not isinstance(c.get("blocks_approval"), bool):
                r.err(f"{rid}: UNRESOLVED requires blocks_approval true/false")
            elif c["blocks_approval"]:
                blocking_unresolved.append(rid)
        if active(c) and st != "NOT_APPLICABLE" and rid not in referenced_controls:
            trace(f"{rid}: control is not referenced by any threat or gate")

    rec_block = doc.get("recovery") or {}
    for item in RECOVERY_ITEMS:
        entry = rec_block.get(item)
        if not isinstance(entry, dict):
            r.err(f"recovery.{item}: missing")
            continue
        check_enum(r, f"recovery.{item}.status", entry.get("status"), CONTROL_STATUS)
        if entry.get("status") == "NOT_APPLICABLE" and not entry.get("reason"):
            r.err(f"recovery.{item}: NOT_APPLICABLE requires a reason")
        if entry.get("status") == "UNRESOLVED" and entry.get("blocks_approval") is True:
            blocking_unresolved.append(f"recovery.{item}")

    blocking_decisions = []
    for d in doc.get("decisions") or []:
        rid = d.get("id")
        check_enum(r, f"{rid}.status", d.get("status"), DECISION_STATUS)
        if not isinstance(d.get("blocks_approval"), bool):
            r.err(f"{rid}.blocks_approval must be true or false")
        if d.get("blocks_approval") and d.get("status") in ("OPEN", "DEFERRED"):
            blocking_decisions.append(rid)

    mon = doc.get("monitoring") or {}
    ho = mon.get("handoff") or {}
    check_enum(r, "monitoring.handoff.configuration_status", ho.get("configuration_status"), HANDOFF_STATUS)
    if ho.get("baseline_version") not in (None, doc.get("baseline_version")):
        r.err("monitoring.handoff.baseline_version does not match baseline_version")
    if ho.get("provider") in (None, "", "NONE") and ho.get("configuration_status") in ("CONFIGURED", "VERIFIED"):
        r.err("monitoring.handoff: provider NONE cannot be CONFIGURED/VERIFIED")
    for s in signals:
        if isinstance(s, dict):
            check_enum(r, f"{s.get('id')}.status", s.get("status"), SIGNAL_STATUS)

    for ch in doc.get("changes") or []:
        rid = ch.get("id")
        check_enum(r, f"{rid}.type", ch.get("type"), CHANGE_TYPES)
        check_enum(r, f"{rid}.status", ch.get("status"), CHANGE_STATUS)
        check_enum(r, f"{rid}.security_impact", ch.get("security_impact"), ["NONE"] + LEVELS)
        if not isinstance(ch.get("material"), bool):
            r.err(f"{rid}.material must be true or false")
        resolve(f"{rid}.targets", ch.get("targets"))

    # ---- lifecycle <-> approval ----
    decision = appr.get("decision")
    check_enum(r, "approval.decision", decision, APPROVAL_DECISION)
    if status in LIFECYCLE_TO_DECISION and decision != LIFECYCLE_TO_DECISION[status]:
        r.err(f"lifecycle {status} requires approval.decision {LIFECYCLE_TO_DECISION[status]} (found {decision})")

    digest = design_digest(doc)
    if status == "APPROVED":
        for f in ("approver", "approved_at", "baseline_version", "record_digest"):
            if not appr.get(f):
                r.err(f"APPROVED requires approval.{f}")
        if not appr.get("scope"):
            r.err("APPROVED requires a non-empty approval.scope")
        if appr.get("baseline_version") and appr.get("baseline_version") != doc.get("baseline_version"):
            r.err("approval.baseline_version does not match baseline_version: the approved design changed")
        for label, val in (("record_digest", doc.get("record_digest")), ("approval.record_digest", appr.get("record_digest"))):
            if val and val != digest:
                r.err(f"{label} does not match the computed design digest: the design changed after approval")
        if blocking_decisions:
            r.err(f"APPROVED with approval-blocking decisions open: {', '.join(blocking_decisions)}")
        if blocking_unresolved:
            r.err(f"APPROVED with approval-blocking unresolved controls: {', '.join(blocking_unresolved)}")
    if status in ("REJECTED",):
        for f in ("approver", "approved_at"):
            if not appr.get(f):
                r.err(f"REJECTED requires approval.{f} (who decided, when)")
    if status == "SUSPENDED" and not any(isinstance(c, dict) and c.get("material") for c in doc.get("changes") or []):
        r.err("SUSPENDED requires at least one material CHG-* record explaining why")
    for f in ("approved_at", "expires_at"):
        try:
            parse_date(appr.get(f))
        except (ValueError, TypeError):
            r.err(f"approval.{f} is not ISO 8601")
    for label, val in (("record_digest", doc.get("record_digest")), ("approval.record_digest", appr.get("record_digest"))):
        if val is not None and not HEX64.match(str(val)):
            r.err(f"{label} must be a 64-char lowercase SHA-256 hex digest or null")

    ctx = {
        "digest": digest, "status": status, "approval": appr, "lifecycle": lc,
        "blocking_decisions": blocking_decisions, "blocking_unresolved": blocking_unresolved,
        "gates": gates, "evidence": evidence,
    }
    return r, ctx


def ci_gate(r, ctx, expected_digest, target, stage):
    """Fail-closed approval checks for CI. Adds errors to r."""
    if ctx.get("status") != "APPROVED":
        r.err(f"GATE: lifecycle is {ctx.get('status')}, not APPROVED")
        return
    appr, lc = ctx["approval"], ctx["lifecycle"]
    if expected_digest:
        if expected_digest.strip().lower() != ctx["digest"]:
            r.err("GATE: design digest does not match the digest from the protected approval source")
    else:
        r.warn("GATE: no --expected-digest from a protected source; approval metadata in this file is self-asserted")
    try:
        exp = parse_date(appr.get("expires_at"))
        if exp and exp < dt.datetime.now(dt.timezone.utc):
            r.err("GATE: approval has expired")
    except (ValueError, TypeError):
        pass
    if target == "production":
        if lc.get("production_posture") == "PRODUCTION_PROHIBITED":
            r.err("GATE: baseline is PRODUCTION_PROHIBITED")
        if lc.get("deployment_lock") != "UNLOCKED":
            r.err("GATE: deployment_lock is not UNLOCKED")
        scope = [str(s).lower() for s in appr.get("scope") or []]
        if not any("production" in s for s in scope):
            r.err("GATE: approval.scope does not include production")
    elif lc.get("development_lock") == "LOCKED":
        r.err("GATE: development_lock is LOCKED")
    if stage:
        limit = STAGES.index(stage)
        for g in ctx["gates"]:
            if not active(g) or not g.get("blocking") or g.get("stage") not in STAGES:
                continue
            if STAGES.index(g["stage"]) > limit:
                continue
            missing = [e for e in g.get("required_evidence") or [] if ctx["evidence"].get(e, {}).get("status") != "PASSED"]
            if missing:
                r.err(f"GATE: {g.get('id')} ({g['stage']}) lacks PASSED evidence: {', '.join(missing)}")


def main(argv=None):
    ap = argparse.ArgumentParser(description="Validate an Opskeep Elliot Design security-baseline.yaml")
    ap.add_argument("file")
    ap.add_argument("--print-digest", action="store_true", help="print the design digest and exit")
    ap.add_argument("--require-approved", action="store_true", help="CI mode: fail closed unless approved")
    ap.add_argument("--expected-digest", help="digest from an independently protected approval source")
    ap.add_argument("--target", choices=["development", "production"], default="development")
    ap.add_argument("--stage", choices=STAGES, help="require PASSED evidence for blocking gates up to this stage")
    ap.add_argument("--strict-warnings", action="store_true", help="treat warnings as errors")
    args = ap.parse_args(argv)

    try:
        with open(args.file, encoding="utf-8") as fh:
            raw = fh.read()
        doc = yaml.safe_load(raw)
    except (OSError, yaml.YAMLError) as exc:
        print(f"ERROR: cannot read {args.file}: {exc}", file=sys.stderr)
        return 2

    if args.print_digest:
        if not isinstance(doc, dict):
            print("ERROR: document root must be a mapping", file=sys.stderr)
            return 2
        print(design_digest(doc))
        return 0

    report, ctx = validate(doc, raw)
    if args.require_approved:
        ci_gate(report, ctx, args.expected_digest, args.target, args.stage)

    for w in report.warnings:
        print(f"WARNING: {w}")
    for e in report.errors:
        print(f"ERROR: {e}")
    failed = bool(report.errors) or (args.strict_warnings and report.warnings)
    mode = "gate" if args.require_approved else "lint"
    print(f"{'FAIL' if failed else 'PASS'} ({mode}): {len(report.errors)} error(s), {len(report.warnings)} warning(s)")
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
