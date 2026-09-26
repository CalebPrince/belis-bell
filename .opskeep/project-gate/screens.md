# Screens

Screen set from PG-019, home structure from PG-033, navigation from PG-034 and navigation pages from PG-037. Every screen has empty, loading and error states as defined in PG-021. Prices are always visible (PG-024).

## Navigation map

Header (logo; Home, Shop, Categories, For Businesses, About, Contact; Search, Account and Cart icons) and footer (logo and tagline, socials when real, Quick Links, Customer Support, Contact, copyright with Privacy Policy and Terms when real). On phones the links open in a menu drawer. Links go only to pages that exist (PG-034, PG-037).

## Home

Purpose: get anyone to a product or a quote in two taps. Sections in order (PG-033): hero with two buttons and a trust row of four; Explore Our Range (six category cards); blue promo banner; Solutions for Every Space (Homes, Businesses, Institutions); Popular Products carousel; Why Choose Belis Bell (four points); Clean Spaces Start Here banner with Shop Now and Contact Us. Each photo is a named slot (`docs/IMAGES.md`). Trust row and Why Choose copy are mock until the owner confirms them (PG-038). States: skeleton cards while loading, and a retryable error per section so one failing section does not blank the page.

## Category and search results

Job: narrow to the right product. Layout: filter column on desktop, filter sheet on phone, product grid, sort control, pack-size and price filters. States: empty results suggest categories and offer a WhatsApp or quote request.

## Product

Job: confirm it is the right product and pack size. Layout: large photo with zoom, name, pack size and unit price, stock status, safety and usage notes, downloadable spec or safety data sheet where relevant, WhatsApp button, add-to-cart and add-to-quote (PG-020). Sticky add-to-cart bar on phone.

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
