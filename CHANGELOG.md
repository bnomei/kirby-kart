# Changelog

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
