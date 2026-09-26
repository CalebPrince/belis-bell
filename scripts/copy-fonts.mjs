// Copies self-hosted variable fonts into public/assets/fonts so no third-party font host is needed (CSP).
import { copyFileSync, mkdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const out = 'public/assets/fonts';
mkdirSync(out, { recursive: true });
const files = [
  ['node_modules/@fontsource-variable/figtree/files/figtree-latin-wght-normal.woff2', 'figtree-latin-wght-normal.woff2'],
  ['node_modules/@fontsource-variable/dm-sans/files/dm-sans-latin-wght-normal.woff2', 'dm-sans-latin-wght-normal.woff2'],
];
for (const [src, name] of files) {
  if (!existsSync(src)) {
    console.error(`Missing ${src}. Run npm ci first.`);
    process.exit(1);
  }
  copyFileSync(src, join(out, name));
}
console.log('fonts copied');
