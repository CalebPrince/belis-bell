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
- **Size selector**: row of option cards, each showing the size and its price; the chosen one has a blue outline. Choosing one updates the price shown (PG-040).
- **Quantity stepper**: minus, number field, plus. 48px targets.
- **Product gallery**: vertical strip of thumbnails beside a large photo with an expand button. On phones the thumbnails sit under the photo. Photos are owner slots (PG-035).
- **Highlights box**: pale blue panel with three icon, title and line rows (PG-039).
- **Bulk panel**: quantity tier table (range and price per unit), a quantity field with an estimated total, and a Request bulk quote button. Tier prices are mock until Belis Bell sets them (PG-042).
- **Product tabs**: Description, Specifications, How to Use, Delivery & Returns. Works with the keyboard; the first tab is open. Without JavaScript all panels show one under another.
- **Common Uses tile**: owner photo with a label below, five in a row on desktop (PG-043).
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

## Shop, cart, account and admin components

- **Filter panel**: grouped options with counts, a price range with min and max fields and an Apply button, and Clear Filters. On phones it opens from a Filters button (PG-046).
- **Pagination**: numbered pages with previous and next, and an ellipsis for long lists.
- **Cart line**: remove control, photo, name, size, stock, unit price, quantity stepper, line total.
- **Order summary card**: subtotal with item count, delivery fee, total, and one primary button.
- **Progress steps**: numbered steps with a line between them (checkout and register).
- **Option cards**: radio cards for address type, delivery option and account type.
- **Order tracking**: five steps with dates and status icons.
- **Status pill**: Pending, Processing, Dispatched, Delivered, Completed, Cancelled, Low Stock, always with text as well as colour.
- **Stat tile**: icon, label, big number and a change against the previous month (admin).
- **Charts**: a line chart and a donut chart, each with a text table alternative for screen readers (admin).
- **Data table**: sortable headers, row actions in a menu, and a stacked layout on phones (admin).
- **Split screen**: photo panel on the left and the form on the right for sign-in and register; the photo panel is hidden on small screens.
