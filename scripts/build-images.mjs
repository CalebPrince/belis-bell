// Builds responsive WebP variants from the owner's original images (PG-035).
//   originals: resources/images/<path>.jpg|jpeg|png|webp        (committed, the source of truth)
//   output:    public/assets/img/<path>-<width>.webp             (generated, not committed)
// Widths above the original are never produced (no upscaling). EXIF orientation is applied and all
// metadata (including GPS) is dropped. Run: npm run build:images
import { readdirSync, statSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { join, relative, dirname, extname, sep } from 'node:path';
import sharp from 'sharp';

const SRC = process.argv[2] ?? 'resources/images';
const OUT = process.argv[3] ?? 'public/assets/img';
const WIDTHS = [400, 800, 1200, 1600];
const QUALITY = 80;
const MAX_BYTES = 15 * 1024 * 1024;       // refuse huge originals
const MAX_PIXELS = 60_000_000;            // refuse decompression bombs
const NAME = /^[a-z0-9]+(?:-[a-z0-9]+)*$/; // each path segment, so the PHP helper can serve it

function* walk(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, entry.name);
    if (entry.isDirectory()) yield* walk(p);
    else yield p;
  }
}

if (!existsSync(SRC)) {
  console.log(`No ${SRC} folder yet: nothing to build.`);
  process.exit(0);
}

rmSync(OUT, { recursive: true, force: true });
let built = 0;
const problems = [];

for (const file of walk(SRC)) {
  const ext = extname(file).toLowerCase();
  if (!['.jpg', '.jpeg', '.png', '.webp'].includes(ext)) continue;
  const rel = relative(SRC, file).split(sep).join('/');
  const noExt = rel.slice(0, -ext.length);
  const segments = noExt.split('/');
  if (!segments.every((s) => NAME.test(s))) {
    problems.push(`${rel}: use lower-case letters, digits and single hyphens only (for example home/hero.jpg)`);
    continue;
  }
  if (statSync(file).size > MAX_BYTES) {
    problems.push(`${rel}: larger than ${MAX_BYTES / 1024 / 1024} MB, please export a smaller original`);
    continue;
  }
  try {
    const image = sharp(file, { limitInputPixels: MAX_PIXELS }).rotate();
    const meta = await sharp(file, { limitInputPixels: MAX_PIXELS }).rotate().toBuffer({ resolveWithObject: true });
    const width = meta.info.width;
    const widths = WIDTHS.filter((w) => w <= width);
    // Small originals: also keep their own width, so a 500px photo is not reduced to 400px.
    const last = widths[widths.length - 1] ?? 0;
    if (width < WIDTHS[WIDTHS.length - 1] && width - last >= 50) widths.push(width);
    mkdirSync(dirname(join(OUT, noExt)), { recursive: true });
    for (const w of widths) {
      await image.clone().resize({ width: w, withoutEnlargement: true }).webp({ quality: QUALITY }).toFile(join(OUT, `${noExt}-${w}.webp`));
      built++;
    }
    console.log(`${rel} -> ${widths.join(', ')}px`);
  } catch (err) {
    problems.push(`${rel}: ${err.message}`);
  }
}

console.log(`${built} image file(s) built`);
if (problems.length) {
  console.error('\nProblems:\n - ' + problems.join('\n - '));
  process.exit(1);
}
