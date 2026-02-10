# Blueprints Agent Guide

## Mission
- Keep Panel blueprints in `blueprints` and `blueprints-kerbs` synchronized with the PHP models they describe.
- Treat these files as generated artifacts for distribution; source of truth lives in `classes/Kart`.

## System
- Blueprints are generated from PHP models via the Composer `dist` workflow, which runs `vendor/bin/kirby kart:blueprints-publish`.
- Output is committed so downstream Kirby projects can copy them without running generators.

## Workflow
- Modify model definitions or blueprint metadata in PHP, then regenerate with either `composer dist` or `vendor/bin/kirby kart:blueprints-publish`.
- After regeneration, review diffs only for unintended changes; rerun formatting (`composer fix`) if PHP was touched.
- Keep `blueprints-kerbs` aligned with Kerbs demo content for docs and testing fixtures.

## Guardrails
- Do not hand-edit files under `blueprints` or `blueprints-kerbs`; manual edits will be overwritten.
- Never bypass the generator—changes must originate from PHP models to ensure consistency.
- Avoid committing partial generations; regenerate after merges that touch models to prevent drift.***
