# Images: what to supply and where it goes

The site is built with named image slots. Until a photo arrives the slot shows a neutral placeholder, so the page structure is already right. You supply the photos; nothing is generated or taken from other sites (design decision PG-035).

## Supplied so far

- `home/hero`, `home/promo`, `home/cta` and the six category photos (`categories/<slug>`), supplied 2026-09-26. The six categories came as one grid image and were cut into separate files. They are about 490 px wide, so a larger original of each would look sharper on high-resolution phones.
- Ten product photos (`products/<slug>/main`) for the first ten mock products, cut from one grid image. They are only about 275 px wide, so they look soft when shown large on the product page. Larger originals would fix that.
- Still to supply: photos for the other six mock products (pedal bin, microfibre cloths, disposable gloves, wet floor sign, mop and bucket set, toilet brush set). The real catalogue will replace the mock one.

## How it works

1. Save the original in `resources/images/`, using the slot name as the path, for example `resources/images/home/hero.jpg`.
2. Run `npm run build:images`. It makes responsive WebP sizes (400, 800, 1200 and 1600 pixels wide, never larger than your original), fixes phone rotation, and removes camera metadata including location.
3. The page picks the right size for the visitor's screen. On slow connections phones download the small size.

`public/assets/img/` is generated and not committed. Your originals in `resources/images/` are committed.

**This repository is currently public.** Anything under `resources/images/` becomes public when pushed. Make the repository private first if the photos should not be public yet.

## Rules for every image

- Formats: JPG, PNG or WebP. Use PNG or WebP with a transparent background for cut-out product groups (the hero and banners in the mockup).
- File names: lower-case letters, digits and single hyphens only (`home/audience-homes.jpg`). The build rejects anything else and says which file.
- Size: at least the "minimum width" below. Up to 15 MB per original.
- No words baked into the picture (headlines, prices, phone numbers). Text is added by the page, so it can be read by screen readers, translated and updated.
- Rights: only images Belis Bell owns or is licensed to use. Photos of people need their permission. Photos that show other companies' branded packaging (for example detergent, toilet cleaner or tissue brands) need the brand's or distributor's permission.
- Alt text: each image needs a short description of what it shows. Product and category alt text is the item name. Homepage alt text is set in the page templates and can be changed when the final photos arrive.

## Home page slots (structure from the owner's mockup, PG-033)

| Slot (file path without extension) | Where it appears | Shape and minimum width | Notes |
|---|---|---|---|
| `home/hero` | Hero, full-width background | Landscape 16:9, 1600 px | Subject on the right. The left 40% fades into the page colour on desktop, so keep it plain or blurred. On phones the photo sits in a band below the text |
| `categories/<category-slug>` | One card each in "Explore Our Range" (six in the mockup: `cleaning-products`, `paper-products-disposables`, `washroom-supplies`, `bins-waste-management`, `cleaning-tools-accessories`, `general-supplies`) | Square or 4:3, 800 px | Product group for that category on a light background. Slot name is the category's slug, for example `categories/washroom-supplies` |
| `home/promo` | Blue promo banner, full-width background | Wide, about 2.4:1, 1600 px | Subject on the right. The left 40% fades into the banner blue, so keep it plain or blurred |
| `home/audience-homes` | "Homes" card | Landscape 4:3, 1200 px (portrait photos work too: the middle is shown) | A home exterior or interior |
| `home/audience-businesses` | "Businesses" card | Landscape 4:3, 1200 px | Office or shop |
| `home/audience-institutions` | "Institutions" card | Landscape 4:3, 1200 px | School, hospital or public building |
| `home/cta` | Bottom banner "Clean Spaces Start Here", full-width background | Wide, about 2.4:1, 1600 px | Person with a box or a warehouse, subject on the right, left 40% plain. Needs the person's permission. |

Product photos:

| Slot | Where it appears | Shape and minimum width | Notes |
|---|---|---|---|
| `products/<product-slug>/main` | Product cards and the top of the product page | Portrait 3:4 (keep the same shape for every product), 1200 px | Single product or pack on a plain light background |
| `products/<product-slug>/2`, `/3`, `/4` | Extra photos on the product page | Same shape as the main photo | Optional: back label, size, in use |

The product slug is the item's web address name, for example `products/sample-bleach-5l/main` for `/p/sample-bleach-5l`.

## Not images

- The logo: `brand/logo-primary.webp` is a raster on a white background. A transparent PNG or SVG, and a horizontal version for the header, are still needed.
- Icons (trust row, Why Choose, footer): drawn as icons, not photos.
- Social preview picture (shown when the site is shared): not built yet.
