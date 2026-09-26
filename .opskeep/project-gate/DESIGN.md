# Belis Bell design source of truth

Read this before touching any UI. It states decisions, not options. Every rule cites the decision that owns it.

## Product

Belis Bell sells washroom and cleaning products in Ghana. It serves households and small businesses, and institutional buyers such as banks, schools, and government and private companies. One responsive web store serves both: individuals check out and pay online, institutions request a quote each time and approve it into an order (PG-001, PG-022, PG-023). Prices are shown to everyone (PG-024). The primary job is to find the right product, get a price or a quote, and order with confidence.

## Direction: Bright Supply (C)

Tagline: photo-led supply store with bold tiles in the Belis Bell blue and green.

It follows the visual language and section order of the reference store (supplymaster.store) at the owner's request, rebuilt in Belis Bell's own logo colours, photography and wording (PG-025, PG-026, PG-029, PG-030). It is friendly and clear for households and credible for institutions.

What it deliberately is not:
- Not a copy. No text, product photos, logos, brand or partner names are taken from the reference, and nothing implies affiliation (PG-026).
- Not editorial or serif-led. The earlier editorial direction was superseded (PG-009).
- Not a sidebar app. Navigation is a top bar with a mega-menu (PG-013).
- Not gold, dark-first or heavy on effects. Premium comes from photography and restraint (PG-008).

## Foundation

| Aspect | Decision |
|---|---|
| Theme | Light default, dark follows the device (PG-012) |
| Navigation | Announcement bar, header with search, nav row with Shop mega-menu and Buy for Business (PG-013) |
| Content width | 1280px max |
| Density | Balanced |
| Corner radius | 12px for buttons and cards, 20px for large tiles |
| Typography | Figtree semibold for headings, DM Sans for body, 17px base body size (PG-004) |
| Primary interaction | Split hero with tile cluster, category pill tabs, product carousels with add-to-cart |
| Mobile strategy | Full parity with desktop, tiles stack, carousels swipe, category drawer (PG-002) |
| Animation | Carousel scroll and gentle hover lift only, reduced-motion safe |
| Icons | Lucide |

## Rules

1. The home page keeps this section order: announcement bar, header, nav row, split hero, category pill tabs, product carousels, brand spotlight, three ways to shop tiles, core category tiles, featured partner picks, trust counter, brand in focus, all brands A to Z, footer. Buying guides and hygiene advice form a lower strip (PG-014, PG-026).
2. The split hero is a large photo with a dark overlay and a big white headline on the left, and a 2x2 cluster of coloured feature tiles on the right. On phones the tiles stack below the hero (PG-025, PG-002).
3. Buttons and links use the blue primary. The announcement bar and footer use deep navy. Feature tiles rotate blue, navy, green (#2b7d32) and ink, always with white text above 4.5:1 contrast. The logo green (#43b14a) is decoration only, never text or a button background (PG-029, PG-031).
4. Product cards show photo, name, pack size, price in GHS, stock status and a full-width add-to-cart button. Elevation is for cards and panels you act on; page sections stay flat (PG-011, PG-020).
5. Every product page offers both add-to-cart and add-to-quote, plus a WhatsApp button (PG-020, PG-006).
6. Institutions use the Buy for Business path: quote from a cart or list, optional requirements upload, a quote thread with messages, then approve into an order. Repeat orders reuse past quotes (PG-018, PG-023).
7. Orders and quotes always have a review step, a confirmation with a reference number, and a logged audit trail. Edits or cancellations after submit are deliberate and recorded (PG-007).
8. Checkout supports online payment. Payment methods and processor are decided in the security gate, not here (PG-022).
9. One primary action per view. Use plain language and no jargon (PG-005).
10. Copy has no em dashes. Tone is calm, warm and plain (PG-008).
11. Pages must stay light. Images are responsive and compressed, use skeleton loading, and cart and quote drafts survive a dropped connection (PG-003, PG-021).
12. Text is at least 17px body. Touch targets are at least 48px (PG-004).
13. All colours come from `tokens.json`. Never hard-code a colour (PG-029).
14. Use the supplied logo (`brand/logo-primary.webp`, PG-030). Its descriptor is "Cleaning Supplies & More" with the line "For homes, businesses and institutions". The header needs a transparent PNG or SVG compact lockup, which is produced before build.
15. The first build uses clearly labelled mock data for products, prices, stock, categories, brands, delivery figures, the trust counter and customer content. It lives in one seed file, is never presented as real, and is removed before launch. Payments run in provider test mode only until the security gate signs off live keys (PG-032).
16. Meet every rule in `accessibility.md` (PG-015).

## Do and don't

| Do | Don't |
|---|---|
| Use original Belis Bell photography and wording in the hero and tiles | Reuse the reference's images, headlines or brand names |
| Show delivery and trust figures only when Belis Bell supplies the real numbers | Invent a free-delivery amount or a "60,000 deliveries" style counter |
| Keep blue for actions and let tiles carry navy and green | Use the bright logo green for text or buttons, or add new accent colours and gradients |
| Use the supplied logo from `brand/` and a compact lockup in the header | Redraw, recolour or stretch the logo, or use the white-background raster on a coloured band |

## Tokens

Values live in `tokens.json`. Do not restate them here or in components.

## Change process

Do not edit these files by hand. Reopen the decision with `gate.py reopen --id PG-xxx --reason "..."`, re-render the dashboard, get it approved, update the affected docs, and re-seal.
