# Providers Agent Guide

## Mission
- Maintain payment/provider integrations under `classes/Kart/Provider` (Stripe, Paddle, Shopify, Gumroad, etc.) so Kart can delegate checkout, licensing, and fulfillment.
- Keep interfaces consistent so carts, orders, and user data behave uniformly across providers.
- Scope is one-time purchases only; subscriptions/recurring flows are out of scope for Kart.

## System
- Providers extend `Bnomei\Kart\Provider` (abstract) and use options fetched from `bnomei.kart.providers.<name>.*`.
- User-specific provider data is stored via `getUserData`/`setUserData` on the provider and serialized with YAML on customer users.
- Provider classes are PSR-4 autoloaded via Composer and are invoked through Kart’s routing and order flows.

## Workflow
- When adding or modifying a provider, start from an existing class as a template; ensure `title()`, option lookups, user data sync, and virtual product behavior stay consistent.
- Map provider-specific API fields (customer identifiers, payment intents, receipts) to Kart’s domain models; keep conversions small and pure.
- Add or adjust tests in `tests/*Provider*Test.php` (or create new ones) to lock behavior; run `composer test` and `composer stan`.
- Document required config keys and defaults in code comments or README notes when behavior changes.

## Guardrails
- Do not rename existing provider classes or option keys—they may be referenced in user configs and content.
- Avoid persisting secrets or tokens outside Kirby’s configured storage; rely on injected options.
- Keep side effects idempotent and cache-aware (see `option()` caching); do not introduce long-running blocking calls inside request handlers.
- Follow Pint formatting and existing namespace/import patterns to minimize noisy diffs.***
