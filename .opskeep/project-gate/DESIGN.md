# Belis Bell design source of truth

Read this before touching any UI. It states decisions, not options. Every rule cites the decision that owns it.

## Product

Belis Bell sells washroom and cleaning products in Ghana. It serves households and small businesses, and institutional buyers such as banks, schools, and government and private companies. One responsive web store serves both: individuals check out and pay online, institutions request a quote each time and approve it into an order (PG-001, PG-022, PG-023). Prices are shown to everyone (PG-024). The primary job is to find the right product, get a price or a quote, and order with confidence.

## Direction: Bright Supply (C)

Tagline: clean, photo-led home page in the Belis Bell logo colours.

The structure comes from the owner's home page mockup (PG-033, PG-034). The visual style follows the mockup with one accessibility change (PG-036). The owner supplies every image (PG-035).

What it deliberately is not:
- Not a copy of another store. No text, photos, logos, brand or partner names are taken from other sites, and nothing implies affiliation.
- Not editorial or serif-led (PG-009 superseded).
- Not a sidebar app. Navigation is a header with text links (PG-034).
- Not gold, dark-first or heavy on effects.
- Not image-generated. The developer does not create or source photos (PG-035).

## Foundation

| Aspect | Decision |
|---|---|
| Theme | Light default, dark follows device (PG-012) |
| Navigation | Header: logo, links Home, Shop, Categories, For Businesses, About, Contact, and icons Search, Account, Cart with count. Menu button on phones (PG-034) |
| Content width | 1280px max |
| Density | Balanced |
| Corner radius | 16px cards, pill buttons |
| Typography | Figtree bold for headings, DM Sans for body, 17px base (PG-004) |
| Primary interaction | Hero with two buttons, category cards, audience cards, product carousel with Add to Cart (PG-033) |
| Mobile strategy | Full parity, sections stack, carousels swipe (PG-002) |
| Animation | Carousel scroll and gentle hover lift only, reduced-motion safe |
| Icons | Lucide, in the green or blue of the palette |

## Rules

1. The home page keeps this section order (PG-033): header; hero (eyebrow, three-line two-tone headline, one sentence, two buttons Shop Now and For Businesses, trust row of four); Explore Our Range (six category cards); blue promo banner; Solutions for Every Space (Homes, Businesses, Institutions); Popular Products carousel; Why Choose Belis Bell (four points); Clean Spaces Start Here banner (Shop Now, Contact Us); footer.
2. The header and footer follow PG-034. Links point only to pages that exist. Footer support and legal links (FAQ, shipping, returns, payment methods, track order, privacy, terms) and social icons appear only when the page or link is real (PG-037).
3. The main button is filled green (#2b7d32, white text). The second button is white with a navy outline. Never use the mockup's bright green (#3fa80c) or the logo green (#43b14a) for text or button fills (PG-036).
4. Headlines are navy, with blue and green emphasis words. Eyebrow labels are small, spaced capitals. Banners use deep navy (#00346e, #002b61) with the photo on the right. The footer is #001b38 (PG-036).
5. Every photo is a named slot in `docs/IMAGES.md`, rendered by `image_html()`. A slot with no photo shows a neutral placeholder and keeps its shape (PG-035).
6. Product cards show photo, name, pack size, price in GHS, stock status and an Add to Cart button. Cards use a soft shadow and 16px radius; page sections stay flat (PG-011, PG-020).
7. Every product page offers add-to-cart and add-to-quote, plus a WhatsApp link when a number is configured (PG-020, PG-006).
8. Institutions use the For Businesses path: quote from a cart or list, optional requirements upload, a quote thread with messages, then approve into an order. Repeat orders reuse past quotes (PG-018, PG-023). Until the quote flow exists, its button is switched off (PG-037).
9. Orders and quotes always have a review step, a confirmation with a reference number, and an audit trail. Edits or cancellations after submit are deliberate and recorded (PG-007).
10. Checkout supports online payment through Paystack. Details are in the security baseline (PG-022).
11. One primary action per view. Use plain language and no jargon (PG-005). Copy has no em dashes (PG-008).
12. The trust row and Why Choose copy are the owner's promises. They show as mock text until the owner confirms each one. "Secure payments" appears only once live payments are approved (PG-038).
13. Pages stay light: responsive images, skeleton loading, drafts survive a dropped connection (PG-003, PG-021).
14. Text is at least 17px body. Touch targets are at least 48px (PG-004).
15. All colours come from `tokens.json`. Never hard-code a colour (PG-036).
16. Use the supplied logo (`brand/logo-primary.webp`, PG-030). Its descriptor is "Cleaning Supplies & More" with the line "For homes, businesses and institutions". The header needs a transparent PNG or SVG lockup, produced before launch.
17. The first build uses clearly labelled mock data for products, prices, stock, categories, brands, delivery figures, contact details and customer content. It lives in one seed file, is never presented as real, and is removed before launch (PG-032, AS-08).
18. Meet every rule in `accessibility.md` (PG-015).

## Do and don't

| Do | Don't |
|---|---|
| Keep the section order from the mockup and give each photo a named slot | Reorder sections or bake headlines and prices into images |
| Show delivery figures, contact details and promises only when the owner supplies or confirms them | Invent a phone number, address or a "60,000 deliveries" style counter |
| Use the dark green for buttons and let blue carry links | Use the bright mockup green for text or buttons |
| Ask the owner before showing third-party brand packaging | Add a branded product photo you do not have the right to show |

## Tokens

Values live in `tokens.json`. Do not restate them here or in components.

## Change process

Do not edit these files by hand. Reopen the decision with `gate.py reopen --id PG-xxx --reason "..."`, re-render the dashboard, get it approved, update the affected docs, and re-seal.
