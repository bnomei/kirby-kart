# Changelog

## 5.17.2 - 2026-11-26

### Fixed

- Keep storefront login and signup links separate from Kirby 5's Panel codes. Links issued by `$user->sendMagicLink()` now have the same email binding, ten-minute expiry and single-use verification as links issued by the routes.
- Verify the link before creating the customer or firing the signup hook. Links requested before updating must be requested again.

## 5.17.1 - 2026-10-25

### Fixed

- Preserve plus-addressed emails and literal percent sequences in password login, authentication form prefills, and Kirby checkout completion by avoiding redundant URL decoding.
- Escape email values when rendering authentication form attributes.

## 5.17.0 - 2026-08-25

### Added

- Add configurable Shopify product metafields to raw provider data with complete, ordered one-level metaobject resolution and cost-bounded GraphQL batching.

## 5.15.0 - 2026-07-10

### Added

- Add structured `ProductGroup` data for multi-dimensional product variants, including canonical variant properties, stable identifiers, and distinct variant URLs.

### Fixed

- Generate product JSON-LD with safe JSON encoding instead of manual string interpolation.
- Include complete product offers with price, currency, availability, and URL data.
- Omit empty optional product fields and encode colon, equals, and dot variant selectors as valid query parameters.

## 5.14.1 - 2026-06-01

### Fixed

- Reject backslash redirect targets before accepting internal redirects.
- Treat only explicit paid Invoice Ninja invoices as paid, excluding cancelled and reversed invoice states.
- Bind Lemon Squeezy success callbacks to the initiated checkout/cart, expected variant, customer email, and order identifier.
