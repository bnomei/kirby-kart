# Changelog

## 6.0.0 — Unreleased

### Changed

- Target Kirby 6 (currently tested with 6.0.0-beta.1) and PHP 8.3 or newer.
- Migrate magic-link challenges to Kirby's instance-based authentication API and computed Panel fields to field classes.
- Continue accepting `kart-magic-link` in `auth.methods` for standalone frontend login/signup; also accept `auth.challenges`. No configuration move is required, and the compatibility method adds no Panel login option.
- Keep frontend magic links independent of Panel second-factor requirements.

### Fixed

- Resolve product gallery thumbnails in gallery order and accept nullable sessions in logout hooks.
- Update runtime type contracts, repeatable test-fixture preparation, and generated blueprints for Kirby 6.

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
