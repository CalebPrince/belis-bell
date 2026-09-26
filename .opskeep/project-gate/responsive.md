# Responsive strategy

Belis Bell is one responsive web app. Phones get full parity with desktop: browsing, quotes, checkout, account and order tracking (PG-002). People use it at a desk, on the move, in the field and on shared screens, often on slow or patchy internet (PG-004, PG-003).

## Breakpoints

- Phone: up to 599px. Single column, 16px gutters.
- Tablet: 600px to 1023px. Two-column grids, tiles in a 2x2 layout.
- Desktop: 1024px and up. Content max width 1280px, centred.
- Wide: 1440px and up. Same 1280px content width, more background visible.

## How navigation adapts

- Phone: announcement bar, compact header with logo, search icon that expands to a full-width field, account and cart. Categories open in a full-screen drawer. Buy for Business is the first item in the drawer.
- Tablet and desktop: full header with search field, nav row and mega-menu.
- Sticky header on every size. The announcement bar scrolls away.

## Screen behaviour by size

- Home: hero photo above, tile cluster stacks below the hero on phones. Carousels swipe. Entry tiles and category tiles stack in one or two columns.
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
