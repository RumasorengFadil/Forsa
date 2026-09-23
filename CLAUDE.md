# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

FORSA (Formasi & Realisasi SH/AP) — internal monitoring app for PLN group workforce formation (FTK) vs. realization, per subsidiary (SH/AP). Stack: PHP Native (no framework) + PostgreSQL. Full product spec lives in `docs/product/PRD.md`; treat it as the source of truth for business rules before changing behavior. `docs/README.md` indexes the rest of the docs (architecture, schema, per-feature notes).

## Commands

```bash
composer install                          # installs phpoffice/phpspreadsheet (only dependency)
cp .env.example .env                       # then edit DB_* vars (Postgres, no password by default locally)

# DB setup (run once, in order — migrations are plain numbered .sql files, no migration runner)
for f in database/migrations/*.sql; do psql forsa -v ON_ERROR_STOP=1 -f "$f"; done
for f in database/seeds/*.sql; do psql forsa -v ON_ERROR_STOP=1 -f "$f"; done
php tools/seed_admin.php "Super Admin" admin@forsa.local <password>   # creates/updates a SUPER_ADMIN login

php -S 127.0.0.1:8080 router.php           # dev server (also works behind Apache via .htaccess -> router.php)

php -l path/to/file.php                    # syntax-check a PHP file — there is no test suite (tests/ is an empty
                                            # placeholder from the PRD's planned folder layout); this + manual
                                            # curl/browser verification is the only safety net right now
node --check public/assets/js/dashboard.js # syntax-check the JS (no bundler/build step — files are served as-is)

php tools/import_ftk_cli.php <shap_code> <YYYY-MM> <file.xlsx> [user_id]  # import a snapshot outside the UI
```

Default login after `seed_admin.php`: `admin@forsa.local` / whatever password was passed.

## Architecture

**No framework, no router library.** `router.php` is the single front controller (used both by `php -S` and by `.htaccess` on Apache). It maps a request path to a file via `route_map.php` — **every new page or `*_api.php` endpoint must be added to `route_map.php`** or it 404s. Static assets under `/assets/*` are served straight from `public/assets/`.

**Every page/endpoint starts with `require shared/bootstrap.php`**, which loads `.env`, starts the session, and pulls in `Database`, `csrf.php`, `flash_message.php`, `response.php`, `validation.php`, `audit.php`, `auth_guard.php`. `Forsa\Database::connection()` is a PDO singleton that sets `search_path TO forsa, public` — all tables live in the `forsa` Postgres schema, never `public`.

**Snapshot-based data model — this is the core invariant of the app.** Uploads never overwrite data:
- `FtkParser` (`modules/import/FtkParser.php`) parses the `.xlsx` (PhpSpreadsheet), validates headers/rows/formulas, and is the *single* place that knows the template's column layout. The CLI importer (`tools/import_ftk_cli.php`) reuses this same class instead of re-implementing parsing.
- `SnapshotWriter` (`modules/import/SnapshotWriter.php`) writes a new `forsa_ftk_snapshots` row (revision_no + 1) and its `forsa_ftk_snapshot_rows`, deactivating the previous active snapshot for that SH/AP+period, all inside one DB transaction. A partial unique index enforces only one `is_active` snapshot per (shap, period).
- **The dashboard and tree never re-read the Excel file or re-parse anything** — they only run SQL aggregates over `forsa_ftk_snapshot_rows`, scoped by `snapshot_id`. If you're adding a feature that needs "the numbers", it reads from snapshot tables, not from `storage/uploads/*.xlsx`.

**Selection string convention**, used identically by `dashboard_api.php`, `history_api.php`, and `ftk_tree_api.php`:
- `period:YYYY-MM-DD` — merge mode: aggregate every SH/AP's *active* snapshot for that period.
- `snapshot:<uuid>` — inspection mode: show one specific (possibly non-active/historical) snapshot as-is, for a single SH/AP.
`DashboardService::resolveSnapshots()` is the one place this is parsed — reuse it rather than re-parsing the selection string elsewhere.

**Tree drill-down** (`TreeService`, `modules/ftk/ftk_tree_api.php`) lazy-loads one organizational level at a time (SH/AP → `organization_level_2/3/4` → jabatan leaf). The important business rule: **a row whose next org field is `NULL` bubbles its jabatan leaf up to the current node instead of creating an empty node** (`TreeService::children()`'s null-bucket handling). Don't "simplify" this into a fixed-depth tree — empty org levels are expected in the source data (e.g. `UI → KP` has no Unit Layanan).

**Business rules are config-driven, not hardcoded**: `config/ftk_rules.php` holds the job-level mapping table (`JobLevelMapper`) and the pemenuhan-status thresholds (`>=100` / `90–99.9` / `<90`). When changing thresholds or level groupings, edit this config — several places (`DashboardService`, `TreeService`, the JS KPI coloring) key off the same buckets and should stay in sync conceptually even though the JS duplicates the thresholds for immediate rendering.

**Sisa/Delta has one stored/backend convention and one dashboard-display convention — don't conflate them:**
- **Stored** (`forsa_ftk_snapshot_rows.sisa_delta`, computed by `FtkParser`, aggregated by `TreeService`'s SQL, and shown as-is in the upload preview modal): `FTK − Total Realisasi` (positive = still short). This is the PRD/import-validation formula — never change it without a data migration for already-imported snapshots.
- **Dashboard display** (`fmtSisa()` in `dashboard.js`, used by every Sisa cell in the drill-down tree and its two detail modals): negates the stored value, i.e. shows `Total Realisasi − FTK` (positive/green = surplus, negative/red = still short). This was an explicit user-requested change so the tree's Sisa column agrees in sign and color with the "Gap FTK" KPI card, which already used `Total Realisasi − FTK`. The negation happens in exactly one place (`fmtSisa()`); don't re-derive Sisa's sign anywhere else in the frontend.
- The **gap-status filter** (`kurang`/`terpenuhi`/`lebih` in `TreeService`) still reads the *stored* value directly (`sisa_delta > 0` = kurang) — that's correct and unaffected, since "kurang" (shortage) is a stable business meaning independent of which sign convention is displayed.

**Single-page dashboard**: `modules/dashboard/dashboard.php` + `public/assets/js/dashboard.js` intentionally combine the Dashboard view, the Upload modal (3-step: form → preview/validate → confirm, via `upload_submit.php?action=preview|confirm`), and the Histori Upload tab into one page with client-side tabs — this was an explicit product decision, not the PRD's original separate-pages nav (`docs/architecture/overview.md` records why). Don't split these back into separate pages without checking with the user first.

**Auth/CSRF**: session-based (`require_login()` guards every page/endpoint; JSON endpoints — files ending `_api.php`, or requests with an `Accept: application/json` header — get a 401 JSON body instead of a redirect on an expired session). Every state-changing POST endpoint calls `csrf_require()` (token from `csrf_token()` embedded in the page and posted back as `_csrf`); GET/read endpoints don't need it.

**File storage**: confirmed uploads are copied permanently into `storage/uploads/` (never auto-deleted, filenames randomized); `storage/temp/` only holds files mid-preview, referenced by a token stored in `$_SESSION['_upload_tokens']` until confirm or expiry.

**Static asset cache-busting**: Apache/MAMP sends no cache-control headers for `public/assets/*`, so browsers can cache `forsa.css`/`dashboard.js`/`users.js` indefinitely on their own heuristics — confirmed to cause real, repeated confusion during manual testing (edits not taking effect even after a hard refresh). Every `<link>`/`<script>` tag for those three files goes through `asset_url()` (`shared/response.php`), which appends `?v=<filemtime>` so a changed file is always fetched fresh. When adding a new CSS/JS file referenced from a page, wrap its path in `asset_url()` too rather than hardcoding the bare path.

## UI conventions (see `docs/reports/2026/09/21/` for the audit that established these)

- Palette is intentionally narrow: one primary (`--blue`), neutral ink/border grays, and a 3-color status system (`--green`/`--amber`/`--red`) that must only ever encode a real pemenuhan/gap state — never used decoratively. Don't reach for new arbitrary accent colors.
- Cards are flat (border only, no shadow) by default; shadow is reserved for the login card and modals only.
- Interactive tree nodes (`.tree-toggle`) must stay real `<button>` elements with `aria-expanded`, not clickable `<span>`s — this was a deliberate accessibility fix. The tree's expand/collapse handler is a single delegated listener bound once on `document` (`treeHandlersBound` guard) — **not** on `#tree-tbody` itself. It was originally on `#tree-tbody`, which broke expand/collapse the moment the dashboard re-rendered (period/history change replaces `#dashboard-content`'s innerHTML, so `#tree-tbody` is a new element every time, but the guard prevented ever re-attaching to it). Don't move the delegation back onto `#tree-tbody`, and don't reintroduce per-node `addEventListener` calls on expand either, since that previously caused duplicate handlers stacking up across repeated expand/collapse cycles.

# Product source of truth

`docs/product/PRD.md` is the authoritative product requirement document. Preserve it verbatim unless the user explicitly requests a requirement change. Track implementation scope and progress in `docs/product/roadmap.md`; never represent planned functionality as implemented.

Develop incrementally. Complete and verify the current increment before starting the next one. Follow the PRD stack: Flutter, NestJS modular monolith, Next.js Admin, PostgreSQL, Redis, MinIO, Nginx, Docker Compose. Do not create per-customer branches or microservices.

## Documentation

All significant changes must keep `/docs` synchronized with the current implementation.

Documentation is required when a change affects:

- architecture,
- database schema,
- API,
- WebSocket events,
- feature behavior,
- authentication or authorization,
- deployment,
- infrastructure,
- configuration,
- security,
- data flow,
- external integration.

Rules:

1. Update the relevant existing document when behavior changes.
2. Create a new document only when the topic does not already exist.
3. Avoid duplicate documentation.
4. Update `docs/README.md` when adding a new documentation file.
5. Documentation must describe the current implementation, not an outdated plan.
6. Database changes must document affected tables, columns, indexes, constraints, and migrations.
7. API changes must document endpoints, payloads, responses, errors, and authorization.
8. WebSocket changes must document event names, payloads, direction, and behavior.
9. Deployment changes must update installation or upgrade instructions when applicable.
10. Significant architectural decisions should be recorded before or together with implementation.

## Implementation Report

After completing a significant change, report:

- Summary
- Files Changed
- Database Changes
- API Changes
- Architecture Changes
- Documentation Updated
- Tests Performed
- Manual Test
- Known Limitations

If a category does not change, write `None`. Store detailed reports under `documentation/reports/<year>/<month>/<day>/` (e.g. `documentation/reports/2026/09/21/talent_matrix_tahap1_data_terakhir_diperbarui.md`) and link them in the final response.

## Verification

- Run `npm run check` for JavaScript changes.
- Run `flutter analyze`, `flutter test`, and Dart format checks for mobile changes.
- Validate Docker Compose configuration after infrastructure changes; report separately whether containers were actually run.
- For testable features, document numbered manual test instructions and expected results. Never claim unexecuted tests passed.
- Database changes require a formal ORM migration, documentation, and backward impact analysis. Do not modify production schemas manually.
- Keep secrets out of source control. Authorization must be enforced by the backend once protected endpoints are introduced.

