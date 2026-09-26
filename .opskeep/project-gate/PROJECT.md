# Belis Bell — Project brief

Online store for washroom and cleaning products in Ghana, serving households and businesses plus institutional buyers such as banks, schools, and government and private companies.

## Intent

- **Product:** Online store with a business-supply (B2B) side
- **Primary users:** Institutional buyers (banks, schools, government, private companies) and general shoppers
- **Usage:** Heavy contact: ordering, quotes and conversations all day
- **Primary job:** Find the right product, get a price or quote, and order with confidence
- **Platform:** Responsive web store, full features on phone and desktop
- **Reference:** Owner-supplied home page mockup (structure only); the owner supplies all images

## Product character

- Trustworthy: 95%
- Photo-led: 75%
- Friendly: 70%
- Premium: 60%
- Operational: 65%

## Chosen direction: Bright Supply

Clean, photo-led home page in the Belis Bell logo colours.

Follows the owner's mockup: clear hero, category cards, audience cards, product carousel and two navy banners. Friendly for households and credible for institutions.

## Users & jobs

- **PG-004 Use in the field, on the move and on shared screens** — Large touch targets, strong contrast and larger base type (17px body). Works at a desk, on a phone in a store room, and projected in an office.
- **PG-005 Mixed software comfort** — Plain language, one clear action per screen, no jargon. Repeat buyers get shortcuts such as reorder and saved lists.

## Project

- **PG-001 Online store for two audiences** — One store serves everyday buyers and institutional accounts. Same catalogue, different paths: quick checkout for individuals, quotes and account ordering for institutions.
- **PG-022 Payment model** — Pay online
- **PG-023 Institutional account model** — Request a quote each time
- **PG-030 Logo supplied** — Use the updated Belis Bell logo (blue B with cart, spray bottle, soap and tissue; blue and green wordmark; descriptor 'Cleaning Supplies & More'; line 'For homes, businesses and institutions'). Saved as brand/logo-primary.webp; the first version is archived. The header uses a compact lockup and the full logo appears in the footer and about strip. The file is a 1254px raster on white with a light halo, so a transparent PNG or SVG and a horizontal lockup are needed before build.
- **PG-032 Mock data for the first build** — The first build uses clearly labelled mock data for products, prices (GHS), stock, categories, brands, delivery figures, the trust counter and customer content. Mock data lives in one seed file, is never presented as real, and is removed before launch. Payments run in provider test mode only until the security gate signs off live keys.

## Risks

- (high) High-stakes ordering with payments and institutional data means security must be designed before the first commit (payment handling, account access, quote data). Next gate: Opskeep/Elliot.
- (med) Premium editorial look depends on real product photography. Weak or inconsistent photos undermine trust more than in a plain design.
- (med) Extended accessibility scope (screen reader, low vision, multi-language and RTL) adds test effort across every screen.
- (med) Payment, institutional account and price visibility decisions (PG-022 to PG-024) change checkout, accounts and data model. They must be answered before build.
- (low) The reference site is another company's identity. Only its structure is borrowed; visuals and copy must be original.
- (med) Following another company's visual style closely can look like a clone. Original logo, photography, wording and palette are required, and nothing may suggest affiliation.
- (med) The logo is a raster with a light halo on white. It needs a transparent or SVG file and a compact header lockup before build.
- (med) Mock data can leak into production. Keep it in one labelled seed file, block launch until it is replaced, and never take real payments in test mode.
- (med) The mockup shows recognisable third-party brand packaging (for example detergent, toilet cleaner and tissue brands). Showing them needs the right from the brand or distributor. Use only photos the owner has the right to show.

