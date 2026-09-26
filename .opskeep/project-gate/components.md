# Components

Token names refer to `tokens.json`. Components use Lucide icons. Cards use 16px radius and buttons are pill-shaped (PG-036).

## Navigation

- **Header**: logo on the left, text links Home, Shop, Categories, For Businesses, About, Contact, then icon buttons Search, Account and Cart with a count badge. Sticky. On phones the links move into a menu button that opens a full-screen drawer (PG-034).
- **Search**: icon button that opens a search field. Announces result counts. Not built until the search page exists.
- **Footer**: deep navy. Logo and tagline with social icons, then Quick Links, Customer Support and Contact columns, then a copyright line with Privacy Policy and Terms. Each link and icon appears only when its page or address is real (PG-037).
- **Breadcrumb**: on listing and product pages.

## Sections and data display (these carry the character)

- **Hero**: eyebrow label, three-line two-tone headline, one sentence, two buttons, trust row, and a photo slot on the right (`home/hero`). Text sits on the light background, so no overlay is needed.
- **Trust row**: four icon and label pairs (Fast delivery across Ghana, Quality products, Trusted support, Secure payments). Mock copy until confirmed (PG-038).
- **Category card**: photo slot (`categories/<slug>`), name, arrow. Six in "Explore Our Range". The whole card is one link.
- **Promo banner**: deep navy gradient, headline, one sentence, Shop Now button, photo slot on the right (`home/promo`).
- **Audience card**: photo slot, round icon overlapping the photo edge, title, one line. Three: Homes, Businesses, Institutions (`home/audience-*`).
- **Product card**: photo slot (`products/<slug>/main`), name (two lines max), pack size, price, stock badge, Add to Cart button (green, full width). Variants: standard, with size options ("Choose size"), out of stock ("Request stock alert"). Soft shadow, `surface` background, `border` outline (PG-011).
- **Product carousel**: swipe on touch, previous and next buttons and keyboard on desktop, one card per column on phones.
- **Why Choose point**: round green icon, title, one line. Four in a row on desktop, stacked on phones.
- **CTA banner**: deep navy gradient, headline, two buttons, photo slot on the right (`home/cta`).
- **Quote thread**: status header, line items table, message list, action bar (PG-018).
- **Order timeline**: vertical steps with dates and status icons.

## Forms

- **Text field, select, quantity stepper, file upload**: 48px minimum height, visible label above, helper text below, error text that says what went wrong and how to fix it (PG-015, PG-021).
- **Pack-size selector**: segmented control with price change shown live.
- **Search with suggestions**: keyboard navigable.

## Feedback

- **Toast**: confirms add to cart or quote sent, with a Review link. Announced to screen readers.
- **Skeleton loader**: matches the card or banner shape (PG-003, PG-021).
- **Empty state**: one sentence and a next action, such as browsing categories from an empty cart.
- **Error state**: plain message with a Retry button.
- **Image placeholder**: neutral striped panel with the slot's shape, shown until the owner's photo exists (PG-035).

## Overlays

- **Menu drawer**: focus is trapped, Escape closes, focus returns to the trigger.
- **Image zoom**: opens a lightbox with keyboard and pinch support.
- **Confirmation step**: order and quote review screens are pages, not popups (PG-007).

## Buttons

- Primary: `primary` (green) background, `primary_contrast` text, pill shape, one per view (PG-005, PG-036).
- Secondary: white with a navy outline.
- Quiet: `surface` with `border` outline, for icon buttons and small actions.
- Danger: `danger` text, used only for cancel and remove actions.
- Focus ring: 3px outline with 2px offset, visible in both themes.
