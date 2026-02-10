# Repository Guidelines

## Scoped Guides
- `blueprints/AGENTS.md`: Generated Panel blueprints; explains generation flow and non-edit policy.
- `classes/Kart/AGENTS.md`: Core Kart domain (cart, orders, events, sessions, licensing).
- `classes/Kart/Provider/AGENTS.md`: Payment/provider integrations and option patterns.

## Project Layout
- `classes/Kart`: Core domain (cart, orders, providers, rate limiting, virtual pages, mixins/models) under namespace `Bnomei\Kart\`.
- `blueprints` and `blueprints-kerbs`: Panel schemas for products, orders, and Kerbs demo content (generated, see scoped guide).
- `templates`/`templates-kerbs` and `snippets`: Demo rendering; copy into host Kirby sites when customizing.
- `routes.php`, `index.php`, `index.js`: Plugin bootstrap, routes, Panel assets.
- `assets`, `translations`: Static assets and locale strings.
- `tests`: Pest suite plus Kirby fixture in `tests/kirby` with content/media under `tests/content` and `tests/site`.

## Build, Test, and Development Commands
- `composer install`: Install dependencies (dev required locally).
- `composer kirby`: Prepare Kirby fixture for tests (installs and patches helpers in `tests/kirby`).
- `composer fix`: Laravel Pint formatting.
- `composer stan`: PHPStan level 9 on `classes` and `index.php`.
- `composer testBefore && composer test && composer testAfter`: Prep fixture, run Pest with `--profile`, then clean up (`KIRBY_HOST=kart.test`).
- `composer rector`: Apply Rector refactors (run Pint afterward).

## Coding Style & Naming Conventions
- PHP 8.2, PSR-4, 4-space indent; typed properties/params/returns preferred.
- Use Pint defaults (Laravel/PSR-12 aligned); avoid hand-formatting conflicts.
- Tests named `*Test.php` near related feature; mirror class/provider names.
- Group provider-specific code under `classes/Kart/Provider`; keep routes/blueprints/snippets small and focused.

## Testing Guidelines
- Primary suite in `tests/*.php` (Pest). Fixtures live in `tests/kirby` and `tests/content`.
- Use `composer testBefore` to set `KIRBY_HOST=kart.test` so Kirby resolves URLs consistently.
- Coverage writes to `tests/clover.xml` per `phpunit.xml`.
- Add fixtures in `tests/site`/`tests/content` for new models/providers; prefer expectation-style assertions.

## Commit & Pull Request Guidelines
- Match repo log style: emoji-style tags (`:bug:`, `:recycle:`, `:construction:`, `:tag:`) + short imperative (e.g., `:bug: fix data encoding order before encryption`).
- Keep commits focused; include rationale in body when behavior changes.
- PRs: describe scope, link issues, list setup steps, and include test results (`composer test`, `composer stan`, `composer fix` if formatting changed). Add screenshots for template/Panel changes and note docs updates.

## Configuration & Security Notes
- Do not commit provider secrets, license keys, or customer data; host-site config holds them.
- `classes/Kart/License.php` is excluded from static analysis—edit only when adjusting licensing flows.
