# Responsive strategy

Belis Bell is one responsive web app. Phones get full parity with desktop: browsing, quotes, checkout, account and order tracking (PG-002). People use it at a desk, on the move, in the field and on shared screens, often on slow or patchy internet (PG-004, PG-003).

## Breakpoints

- Phone: up to 599px. Single column, 16px gutters.
- Tablet: 600px to 1023px. Two-column grids, category cards in three columns.
- Desktop: 1024px and up. Content max width 1280px, centred.
- Wide: 1440px and up. Same 1280px content width, more background visible.

## How navigation adapts

- Phone: compact header with logo, search, account and cart icons, and a menu button that opens a full-screen drawer with the six links. For Businesses sits near the top of the drawer.
- Tablet and desktop: full header with the six text links and the three icon buttons.
- Sticky header on every size. The announcement bar scrolls away.

## Screen behaviour by size

- Home: the hero photo moves below the text on phones. Trust row wraps to two columns. Category cards go two per row. Banners stack with the photo below the text. Audience cards and Why Choose points stack in one column. Carousels swipe.
- Category page: filters become a bottom sheet on phone. The grid is two columns on phone, three on tablet, four on desktop.
- Product page: photo gallery swipes on phone. A sticky bar holds price, add-to-cart and WhatsApp.
- Cart and checkout: single column with a sticky order summary on phone and a side summary on desktop.
- Quote thread: messages and line items are tabs on phone, side by side on desktop.
- Account: list of sections on phone, sidebar of sections on desktop.

## Touch and reading

- Touch targets are at least 48px, with 8px between them.
- Body text is 17px minimum, with strong contrast so screens remain readable outdoors and on shared displays (PG-004).
- Text can grow to 200% without clipping or horizontal scrolling (PG-015).

## Slow-network behaviour

- Responsive images with modern formats, explicit dimensions and lazy loading below the fold. Hero image is the only priority image.
- Skeleton loaders for cards, tiles and carousels. Text loads before images.
- Requests are retryable, with a clear Retry button and no lost input.
- Cart and quote drafts are saved locally and sync when the connection returns.
- Carousels load their first four cards and fetch more on demand.
- No autoplay video. No heavy scripts on first load.
