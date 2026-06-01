# Changelog

## 5.14.1 - 2026-06-01

### Fixed

- Reject backslash redirect targets before accepting internal redirects.
- Treat only explicit paid Invoice Ninja invoices as paid, excluding cancelled and reversed invoice states.
- Bind Lemon Squeezy success callbacks to the initiated checkout/cart, expected variant, customer email, and order identifier.
