# Kart Core Agent Guide

## Mission
- Define and evolve the core shopping cart domain for Kirby CMS (Kirby 5) within `classes/Kart`.
- Deliver carts, orders, licensing, queues, rate limiting, virtual pages, and provider orchestration that stay lightweight and Kirby-friendly.

## System
- PSR-4 namespace `Bnomei\Kart\` maps to this folder; plugin bootstrap lives in `index.php`/`routes.php` and Panel assets in `index.js`.
- Key models: `Cart`/`CartLine`, `OrderLine`, `Queue`, `Ratelimit`, `Wishlist`, `VirtualPage`, enums (`ProviderEnum`, `ContentPageEnum`), and provider base class.
- Uses Kirby session for carts, site-level `kart()` accessor, and Kirby events (`kart.<event>`) for lifecycle hooks.

## Workflow
- Keep domain logic cohesive: prefer small methods and pure helpers in `Models/` and `Mixins/`.
- When changing cart/order flows, update blueprints generators and fixtures as needed, then run `composer fix`, `composer stan`, and `composer test`.
- Align new features with Panel UX: ensure template/snippet expectations and routes remain compatible with Kirby’s router.
- For licensing or environment-sensitive logic, mirror existing patterns (e.g., limit lines when license inactive, `environment()->isLocal()` checks).

## Guardrails
- Preserve backward compatibility for public methods and event names consumed by downstream Kirby sites.
- Avoid storing state outside sessions, content models, or configured caches; respect `Kart::option()` access patterns.
- Keep proprietary headers intact and avoid introducing third-party dependencies without Composer entries.
- Favor configuration over hardcoded values; use `kart()->option()`/`option()` accessors for tunables.***
