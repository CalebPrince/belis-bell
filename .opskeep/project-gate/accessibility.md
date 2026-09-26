# Accessibility

Baseline is WCAG 2.2 AA. On top of it the owner asked for heavy keyboard use, screen-reader users, low-vision needs and several languages including right-to-left (PG-015). These are testable rules.

## Keyboard

- Every action works with the keyboard alone, in a logical order that follows the visual order.
- A skip link reaches the main content. Mega-menu, drawers, carousels, filters and the quote thread all work from the keyboard.
- Drawers and dialogs trap focus, close with Escape, and return focus to the control that opened them.
- Carousels have previous and next buttons and never trap focus.
- Focus is always visible: a 3px `primary` outline with a 2px offset that meets 3:1 against its background in both themes.

## Screen readers

- Use semantic landmarks: header, nav, main, footer. Each page has one h1 and headings do not skip levels.
- Every image has useful alt text or is marked decorative. Product photos describe the product and pack.
- Cart and quote changes, toasts and form errors are announced with live regions.
- Buttons and links say what they do. "Choose size" and "Add to quote" are specific, and "Click here" is never used.
- Carousels expose their name, current slide and total count.
- Tables (quote line items, order lists) use real table markup with headers.

## Contrast and colour

- Text contrast is at least 4.5:1 and large text at least 3:1, using the validated values in `tokens.json` for light and dark themes.
- White text on the blue, navy, green (#2b7d32) and ink tiles was checked above 4.5:1 (PG-031). The bright logo green fails contrast and is never used for text. Text over photos sits on a dark overlay that keeps it above 4.5:1.
- Colour is never the only signal. Stock, price changes, errors and status use text or icons as well.

## Low vision

- Body text is 17px, and the page stays usable at 200% text size and 400% zoom without horizontal scrolling or clipped content.
- Touch and click targets are at least 48px, with clear spacing.
- Line length stays under about 75 characters in reading areas.
- Both themes are fully designed, so people who need dark mode or high contrast can use them.

## Forms and errors

- Every field has a visible label. Placeholders are hints only.
- Errors name the field, say what went wrong and how to fix it, and move focus to the first error.
- Required fields are marked in text.
- Checkout and quote forms do not time out without warning and keep entered data when an error occurs.

## Motion

- Respect `prefers-reduced-motion`: carousels stop auto-scrolling and hover lifts are removed.
- Nothing flashes more than three times per second.

## Language and direction

- Layouts use logical properties so right-to-left works without redesign. Text can expand by 40% without breaking layouts.
- Language is set on the page and on any text in another language. English is the launch language and translated content is not in v1.

## Verify before release

1. Keyboard-only run through browse, add to cart, checkout, quote request and quote approval.
2. Screen-reader run on the same flows with NVDA or VoiceOver.
3. Automated audit with axe or Lighthouse on every screen in `screens.md`, in both themes.
4. Contrast check on tile text, text over photos and both themes.
5. 200% text and 400% zoom test on phone and desktop widths.
6. Right-to-left smoke test on home, product and checkout.
7. Reduced-motion test.
