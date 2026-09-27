-- One role per staff account (CTL-AUTHZ-002). Existing staff have no role until the owner assigns one, so they can
-- sign in and see the dashboard but reach nothing else. Owners need no role.
ALTER TABLE users ADD COLUMN staff_role ENUM('content','fulfilment','sales') NULL AFTER role;
