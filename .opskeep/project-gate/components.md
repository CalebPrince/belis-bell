# Components

Token names refer to `tokens.json`. Components use Lucide icons and 12px radius unless stated (PG-025).

## Navigation

- **Announcement bar**: deep navy with white text, one message such as a delivery offer. Shown only with real figures from Belis Bell. Dismissible, keyboard reachable.
- **Header**: compact Belis Bell logo lockup (PG-030), search field, account, saved lists, cart with count. Sticky. Search is the widest element.
- **Nav row**: Shop Products with mega-menu, Buy for Business, Top Sellers, Deals, Buying Guides. Mega-menu opens on click and keyboard, never hover only. On phones it becomes a full-screen category drawer (PG-013).
- **Category pill tabs**: horizontal scrolling pills above the carousels. Selected pill uses `primary` with `primary_contrast` text.
- **Footer**: deep navy, quick links, categories, contact, payment and delivery notes.

## Data display (these carry the character)

- **Hero with tile cluster**: photo with dark overlay, large white headline and a short line, beside a 2x2 grid of feature tiles. Tiles rotate blue, navy, green and ink with white text (PG-031). Each tile is one link with a photo, title and one line.
- **Product card**: photo, name (two lines max), pack size, price, stock badge, full-width add-to-cart button. Variants: standard, with size options (button reads "Choose size"), out of stock (button becomes "Request stock alert"). Elevated surface, `surface` background, `border` outline (PG-011).
- **Product carousel**: swipe on touch, arrow buttons and keyboard on desktop, progress bar underneath.
- **Entry tile**: large photo tile with title and short copy. Used for Shop Products, Buy for Business and Washroom Solutions (PG-028).
- **Category tile**: photo, category name, item count.
- **Trust counter**: one large real figure and a supporting line. Hidden until Belis Bell supplies a true figure.
- **Quote thread**: status header, line items table, message list, action bar (PG-018).
- **Order timeline**: vertical steps with dates and status icons.

## Forms

- **Text field, select, quantity stepper, file upload**: 48px minimum height, visible label above, helper text below, error text that says what went wrong and how to fix it (PG-015, PG-021).
- **Pack-size selector**: segmented control with price change shown live.
- **Search with suggestions**: keyboard navigable, announces result counts to screen readers.

## Feedback

- **Toast**: confirms add to cart or quote sent, with a Review link. Announced to screen readers.
- **Skeleton loader**: matches the card or tile shape (PG-003, PG-021).
- **Empty state**: illustration-free, one sentence and a next action, such as browsing categories from an empty cart.
- **Error state**: plain message with a Retry button.

## Overlays

- **Mega-menu panel and category drawer**: focus is trapped in the drawer, Escape closes, focus returns to the trigger.
- **Image zoom**: opens a lightbox with keyboard and pinch support.
- **Confirmation step**: order and quote review screens are pages, not popups (PG-007).

## Buttons

- Primary: `primary` background, `primary_contrast` text, 12px radius, one per view (PG-005).
- Secondary: `surface` background with `border` outline.
- Danger: `danger` text, used only for cancel and remove actions.
- Focus ring: 3px `primary` outline with 2px offset, visible in both themes.
