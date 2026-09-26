# Screens

Screen set from PG-019, home structure from PG-033, navigation from PG-034 and navigation pages from PG-037. Every screen has empty, loading and error states as defined in PG-021. Prices are always visible (PG-024).

## Navigation map

Header (logo; Home, Shop, Categories, For Businesses, About, Contact; Search, Account and Cart icons) and footer (logo and tagline, socials when real, Quick Links, Customer Support, Contact, copyright with Privacy Policy and Terms when real). On phones the links open in a menu drawer. Links go only to pages that exist (PG-034, PG-037).

## Home

Purpose: get anyone to a product or a quote in two taps. Sections in order (PG-033): hero with two buttons and a trust row of four; Explore Our Range (six category cards); blue promo banner; Solutions for Every Space (Homes, Businesses, Institutions); Popular Products carousel; Why Choose Belis Bell (four points); Clean Spaces Start Here banner with Shop Now and Contact Us. Each photo is a named slot (`docs/IMAGES.md`). Trust row and Why Choose copy are mock until the owner confirms them (PG-038). States: skeleton cards while loading, and a retryable error per section so one failing section does not blank the page.

## Category and search results

Job: narrow to the right product. Layout: filter column on desktop, filter sheet on phone, product grid, sort control, pack-size and price filters. States: empty results suggest categories and offer a WhatsApp or quote request.

## Product

Job: confirm it is the right product and size, and buy one or many (PG-039, PG-040, PG-042). Layout: breadcrumb; gallery with a vertical strip of thumbnails beside a large photo with an expand button; category label, name, short description, price, Size options (each with a price), quantity stepper, Add to Cart (green, large), save-for-later heart; trust row; a highlights box of three benefits; a bulk purchase panel (quantity price tiers, an estimated total for a chosen quantity, and a Request bulk quote button); tabs for Description, Specifications, How to Use and Delivery & Returns, with a Key Features list; You May Also Like with a View All link; and a Common Uses strip of five photo tiles. WhatsApp link when configured, add-to-quote, and a data sheet download where a file exists. No ratings, review counts or sold counts until a real reviews system exists (PG-041). Add to Cart, save and Request bulk quote are switched off until the cart, saved lists and quote flow are built. Sticky add-to-cart bar on phone. All text beyond name, size and price is mock until supplied (PG-043).

## Cart and checkout

Job: pay online with confidence (PG-022). Layout: cart list, delivery details, review step, payment, confirmation with order reference (PG-007). Drafts survive a dropped connection (PG-003). Payment methods are set by the security gate.

## Categories

Job: see every category and pick one. Layout: grid of category cards, the same cards as on the home page. Built from the category list.

## For Businesses

Job: understand how institutions buy and start a quote (PG-018, PG-023). Layout: short intro, how a quote works in three steps, the audience cards, and a Request a quote button. The button is switched off until the quote flow is built (PG-037). Contact by WhatsApp or phone when configured.

## About

Job: build trust. Layout: who Belis Bell is, who it serves, the promises (mock until confirmed, PG-038) and a Contact link. Text is clearly labelled mock until the owner supplies it.

## Contact

Job: reach a person. Layout: address, phone, email, opening hours and a WhatsApp link, each shown only when supplied (the mockup values are mock, AS-08). No form until the security-reviewed contact flow exists.

## Buy for Business: quote request and quote thread

Job: get a price for a larger or repeat need (PG-018, PG-023). Quote request starts from the cart or a saved list, with quantities, delivery details, an optional requirements upload and contact preferences. The quote thread shows status, line items, messages, and an Approve into order action with a review step. States: draft, sent, replied, approved, expired. WhatsApp and call buttons sit beside the thread (PG-006).

## Account

Job: reorder fast. Sections: orders, quotes, saved lists, addresses, profile. Saved lists can start a cart or a quote in one action. Order tracking shows each step of an order with dates.

## Order tracking

Job: know where an order is. Layout: reference lookup or account order page, step timeline, delivery details, help via WhatsApp.

## Content pages

Buying guides, hygiene advice, about, contact, FAQ. Layout: readable single column, images optional, clear links back to products. The contact page lists phone, WhatsApp and address.

## Shop and category (PG-045, PG-046, PG-050)

Job: find products fast. Layout: hero banner with breadcrumb, title, description, a trust row and a faded photo; a left filter panel; a results bar with "Showing 1 to 12 of N products" and Sort by; a grid (two across on phones up to six on very wide screens, 12 per page); numbered pagination; and a closing strip. Category pages list subcategories with counts and end with a category-specific "Why choose" strip. Empty results explain how to clear filters.

## Cart (PG-052)

Job: review the order before paying. Layout as described in PG-052. One Proceed to Checkout button; no mobile money button. Empty cart shows a friendly message and links to categories.

## Checkout (PG-053, PG-054)

Job: give delivery details and pay. Requires sign-in. Sections: Contact Information, Delivery Address, Delivery Option, then the Order Summary sidebar with a Pay with Paystack button. No payment method section.

## Order confirmation (PG-055, PG-056)

Job: confirm the order and its progress. States: paid, pending, failed, cancelled. Only the owner of the order can open it.

## Register, sign in and code entry (PG-064 to PG-068)

Job: get in safely. Register has Account Type, Your Details and Complete (enter the emailed code). Sign-in is email and password, then the emailed code. Errors are uniform. The admin login is separate, unlinked from the storefront, and has no Remember me or Google button.

## Account (PG-057, PG-058)

Job: manage orders, addresses, saved products and details. Sidebar, four tiles, recent orders, saved products, addresses, quick actions and account details.

## Admin (PG-060 to PG-063)

Job: run the store. Sidebar, top bar, stat tiles, charts, quick actions, recent orders and low stock. Pages and figures depend on the role.
