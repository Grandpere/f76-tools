# AI Memory

Project memory for recurring pitfalls, decisions, and proven fixes.

## How to use
- Add one entry per incident/decision.
- Keep it short, factual, and actionable.
- Prefer prevention rules over long narratives.
- Update this file immediately after a bug is diagnosed/fixed.

## Entry template

```md
## YYYY-MM-DD - Short title
- Symptom:
- Root cause:
- Fix:
- Prevention:
- Links: (optional)
```

## Incident Log

## 2026-03-11 - OCR fallback must keep best confidence, not last provider
- Symptom: when all OCR providers were below acceptance threshold, snapshot kept a lower-quality result (e.g. tesseract 0.83 over ocr.space 0.89).
- Root cause: `OcrProviderChain` returned the last successful provider instead of the best-confidence successful provider.
- Fix: chain now tracks highest confidence among successful attempts and returns that result when no provider is acceptable.
- Prevention: keep a unit test for "all providers rejected by threshold" asserting best-confidence fallback selection.

## 2026-03-11 - Optional OCR providers should not pollute attempt diagnostics
- Symptom: admin OCR details repeatedly showed `paddle ... not installed/configured`, creating noise and confusion.
- Root cause: unavailable optional provider was treated as a regular failed attempt.
- Fix: introduced `OcrProviderUnavailableException`; chain skips unavailable providers in attempt reporting while preserving real failures.
- Prevention: optional/inactive providers should raise dedicated unavailable exceptions and be excluded from operator-facing failure summaries.

## 2026-03-11 - Roadmap merge quality should rely on reviewed snapshot events when present
- Symptom: merge could reparse raw OCR text and ignore manually corrected `roadmap_event` rows from admin, reducing reliability on older seasons.
- Root cause: merge pipeline always parsed `raw_text` instead of preferring persisted reviewed events.
- Fix: merge now uses snapshot persisted events first (sorted), falls back to parser only when events are absent, and runs quality validation before merge.
- Prevention: for review-driven workflows, merge/publish should consume edited persisted artifacts before any regeneration fallback.

## 2026-03-06 - Admin roadmap functional tests broke on locale query in form action
- Symptom: `RoadmapSnapshotControllerTest` could not find CSRF tokens in parse/approve/save forms (`assertCount(1)` got `0`).
- Root cause: test selectors used `form[action$=\"...\"]` while rendered actions now append `?locale=...`.
- Fix: switched selectors to `form[action*=\"...\"]` and aligned table count expectation to current page layout (snapshots table + events table when a snapshot is selected).
- Prevention: for functional selectors on generated routes, prefer `action*=` (or stable form ids) over strict `action$=` when locale/query params may be appended.

## 2026-03-06 - Functional test stale Doctrine entity after multiple HTTP requests
- Symptom: roadmap snapshot approval test still read `DRAFT` status after successful approve POST.
- Root cause: test reused the same `EntityManager` across requests and could keep a managed stale entity instance.
- Fix: call `$entityManager->clear()` before reloading entities after state-changing HTTP requests.
- Prevention: in functional tests doing write then read across separate requests, clear Doctrine identity map before assertions on persisted state.

## 2026-03-06 - Roadmap month blocks can include non-ranged lines
- Symptom: expected OCR roadmap count was off by one (assumed 20 events for 4 months x 5 lines).
- Root cause: some month blocks include one editorial/update line without a date range (`MISE A JOUR ...`) that is not an event window.
- Fix: parser remains date-range driven; multiline title extraction was improved, but only ranged entries are persisted as events.
- Prevention: validate roadmap totals by counting date ranges, not visual bullet lines.

## 2026-03-07 - OCR roadmap titles can include footer/banner garbage lines
- Symptom: parsed event titles sometimes included unrelated OCR text (`Falleut 76`, `COMMUNITY CALENDAR`, `Bethesda`, `TM` artifacts).
- Root cause: multiline title reconstruction accepted nearby non-event banner/footer lines.
- Fix: strengthened `isIgnoredTitleLine()` noise filtering (calendar/footer/brand/TM patterns + symbol-only lines).
- Prevention: keep a dedicated unit test with known OCR garbage snippets to prevent regressions.

## 2026-03-07 - Multi-locale roadmap parsing should use locale profiles, not scattered locale conditionals
- Symptom: English/German OCR payloads produced 0 events in some real-world date formats.
- Root cause: locale-specific month aliases/ordinals/date notation were hardcoded in a single parser with limited FR-centric assumptions.
- Fix: introduced `RoadmapLocaleProfile` + registry (`fr/en/de`) and moved month aliases/title normalization per locale into dedicated profiles; parser now uses one common pipeline with locale profile lookup.
- Prevention: when adding/changing locale behavior, update only the matching profile + add targeted unit test for that locale format (ordinals, dots, aliases, OCR typos).

## 2026-02-28 - Flatpickr theme override requires CSS import order
- Symptom: datepicker popup still showed default blue/black selection despite custom styles.
- Root cause: `flatpickr.min.css` loaded after app styles, overriding custom theme rules.
- Fix: import `flatpickr` CSS before `assets/styles/app.css` and keep stronger range selectors in app CSS.
- Prevention: when theming third-party widgets, ensure vendor CSS is imported first and validate final cascade in built output.

## 2026-02-28 - Admin Minerva column widths were applied to the wrong table
- Symptom: requested width change (`Liste` compact, `Localisation` dominant) was not visible.
- Root cause: timeline width class was first attached to the manual-overrides table, then widths stayed weak without fixed table layout.
- Fix: attach class to timeline table, add explicit `colgroup` and `table-layout: fixed` with dedicated column widths.
- Prevention: for admin pages with multiple tables, always verify class target in template and prefer `colgroup` + fixed layout for deterministic widths.

## 2026-02-28 - Minerva player switch did not refresh timeline progression
- Symptom: changing player in Minerva filters updated item cards but timeline progress column stayed stale until reload.
- Root cause: `minerva_knowledge` and `minerva_progression` Stimulus controllers were independent with no shared event.
- Fix: emit `f76:minerva-player-changed` from knowledge controller and reload stats in progression controller on event.
- Prevention: when two controllers share a critical state (active player), define an explicit cross-controller event contract.

## 2026-02-28 - Minerva progression still used legacy modulo-4 fallback
- Symptom: Minerva timeline progress could show values from lists 1..4 for rows >4.
- Root cause: front controller `minerva_progression_controller.js` still mapped list numbers with a legacy modulo-4 fallback.
- Fix: removed fallback mapping and kept strict progress lookup by absolute list number (1..24).
- Prevention: when domain numbering changes (e.g. list model), remove temporary compatibility fallbacks once migration is completed.

## 2026-02-28 - Minerva special list files can contain duplicate item IDs
- Symptom: list 24 showed 39 unique items while raw counts of lists 21/22/23 summed to 40.
- Root cause: source JSON contained duplicated `id` (`931`) within the same special-list payload.
- Fix: importer emits a warning on duplicate source rows in the same file (`Doublon detecte ... (conserve)`) but does not skip/filter rows.
- Prevention: keep importer warnings visible, and treat source row count and unique-item count as distinct metrics by design.

## 2026-02-27 - public/assets may be ignored for new icon files
- Symptom: `git add` failed with `The following paths are ignored ... public/assets`.
- Root cause: `.gitignore` rules ignore parts of `public/assets`, while existing files can still be tracked.
- Fix: add new icon files explicitly with force (`git add -f public/assets/icons/<file>`).
- Prevention: when creating new assets under ignored paths, check tracking with `git ls-files` and use forced add only for intended files.

## 2026-02-27 - Minerva BOOK list model 1..4 caused semantic drift
- Symptom: UI/stats showed progression only for 4 "listes" while Minerva rotation uses 24 real lists.
- Root cause: import collapsed `minerva_61..84` into modulo-4 list numbers (`1..4`) instead of absolute list numbers (`1..24`).
- Fix: switched import mapping to absolute lists (`list_number = minervaNumber - 60`), expanded DB constraint to `1..24`, and reset/rebuilt BOOK list relations on import.
- Prevention: when filenames encode absolute business identifiers, store them as-is in persistence; avoid modulo compression unless explicitly required by product semantics.

## 2026-02-27 - Minerva countdown offset by 5h due to wall-time timezone handling
- Symptom: front countdown showed ~5h less than external Minerva trackers for the same next window.
- Root cause: `minerva_rotation` uses `timestamp without time zone` wall times, but timeline service converted DateTime timezone directly (`setTimezone`) instead of reinterpreting stored wall time as `America/New_York`.
- Fix: normalize stored wall times by reparsing them in `America/New_York` (`normalizeStoredWallTime`) and keep timeline timezone explicit to `America/New_York`.
- Prevention: for DB columns storing wall-time schedules (`timestamp without time zone`), never apply direct timezone conversion as if absolute instant; first reinterpret in business timezone.

## 2026-02-27 - PHPUnit mock typing on final class can break phpstan
- Symptom: `phpstan` reported `createMock()` as unresolvable/mixed in Minerva unit tests.
- Root cause: unit test mocked a concrete final service (`MinervaRotationGenerationApplicationService`) and relied on mock-specific typing.
- Fix: switched test to real deterministic generation service + mocked repository/entity manager only.
- Prevention: prefer mocking ports/interfaces; when a deterministic final service has no side effects, instantiate it directly in tests.

## 2026-02-27 - Catalog import stability improves with explicit value objects
- Symptom: import flow used multiple implicit array-shape contracts (`context`, `contextResult`, `translationData`) that were easy to misuse during refactors.
- Root cause: permissive array plumbing across `ItemImport*` services without explicit typed contracts.
- Fix: introduced dedicated value objects (`ItemImportFileContext`, `ItemImportContextApplyResult`, `ItemImportTranslationCatalog`) and aligned service/tests.
- Prevention: in import pipelines, prefer small readonly value objects over associative arrays for cross-class contracts.

## 2026-02-27 - Lock architecture against legacy root namespace regressions
- Symptom: after DDD migration, accidental reintroduction of `App\\Controller`/`App\\Entity` style dependencies was still possible.
- Root cause: architecture tests did not explicitly forbid dependencies toward legacy root namespaces.
- Fix: added PHPat rule `testAppDoesNotDependOnLegacyRootNamespaces` in `tests/Architecture/ArchitectureTest.php`.
- Prevention: when removing legacy roots, add an explicit architecture guard so future refactors cannot silently depend on them again.

## 2026-02-27 - Query object signature hardening requires aligned unit fixtures
- Symptom: unit/phpstan failures after changing admin query `fromRaw(...)` signatures from `int|string|null` to `?int`.
- Root cause: unit tests still passed string pagination values to application query objects.
- Fix: keep string-to-int parsing in UI sanitizers and update query-object unit fixtures to pass already-sanitized ints/null.
- Prevention: when moving sanitization upstream to UI, update application-level tests to reflect typed contracts.

## 2026-02-27 - Route import path must follow DDD controller moves
- Symptom: full functional suite returned HTTP 500 on nearly all pages/endpoints after controller namespace migration.
- Root cause: `config/routes.yaml` still imported `../src/Controller/` while that directory had been removed.
- Fix: switched route attribute import to `path: ../src/` and `namespace: App`.
- Prevention: after any controller relocation/removal, verify routing imports in `config/routes.yaml` before running functional tests.

## 2026-02-27 - Doctrine entities in Infrastructure namespace break PHPat boundaries
- Symptom: after moving entities out of `src/Entity`, PHPat raised many `Application/UI -> Infrastructure` violations and doctrine test bootstrap failed on missing `src/Entity` mapping path.
- Root cause: entities were first moved under `*/Infrastructure/...`, which made every entity type-hint count as an infra dependency; Doctrine config still pointed to `src/Entity`.
- Fix: moved entities to `*/Domain/Entity`, removed `repositoryClass` attributes from domain entities to avoid `Domain -> Infrastructure`, and switched Doctrine mapping to `dir: src` + `prefix: App`.
- Prevention: when removing `src/Entity`, place entities in domain context namespaces and update Doctrine mapping in the same slice.

## 2026-02-27 - UI namespace migration exposes hidden infrastructure coupling
- Symptom: after moving web/security controllers into `.../UI/...`, PHPat started failing on direct infra injections that previously went unnoticed.
- Root cause: legacy `src/Controller/*` classes were outside strict layer checks; once moved under UI namespaces, architectural rules applied.
- Fix: replaced direct infra dependencies with application-level abstractions (`IdentityCaptchaSiteKeyProvider`, `PlayerReadApplicationService`).
- Prevention: when migrating legacy paths into DDD layers, expect PHPat to surface hidden couplings and budget a follow-up decoupling pass in the same slice.

## 2026-02-27 - Moving controllers/repositories can trigger PHPat layer violations
- Symptom: after namespace moves, PHPat reported `Application -> Infrastructure` and `UI -> Infrastructure` violations.
- Root cause: some classes injected concrete repositories (`MinervaRotationEntityRepository`, `UserEntityRepository`) instead of application ports.
- Fix: introduced ports (`MinervaRotationRegenerationRepository`, `AdminUserManagementReadRepository`) and rewired dependencies to interfaces.
- Prevention: after structural moves, run phpstan/PHPat early and immediately replace concrete infra dependencies with application-level contracts.

## 2026-03-01 - Port/class same-name collision after Interface suffix removal
- Symptom: `phpstan`/PHPUnit fatal error `Cannot redeclare class ...ContactMessageEmailSender`.
- Root cause: after renaming the port to `ContactMessageEmailSender`, the infrastructure class kept `use App\...\ContactMessageEmailSender` and had the same class basename in the same file.
- Fix: removed ambiguous import and implemented the port with fully-qualified name.
- Prevention: when removing `Interface` suffixes, check files where implementation class basename equals the new port basename and avoid conflicting `use` imports.

## 2026-03-01 - Same-namespace port naming collision in UI layer
- Symptom: renaming `ProgressionOwnedPlayerReadResolverInterface` directly caused a filename/class collision with existing `ProgressionOwnedPlayerReadResolver`.
- Root cause: port and implementation lived in the same `App\Progression\UI\Api` namespace with identical basename after suffix removal.
- Fix: renamed the port to `ProgressionOwnedPlayerReadPort` and kept the implementation class name unchanged.
- Prevention: when removing `Interface` suffixes, if port and implementation share namespace, pick a distinct port suffix (`Port`/`Contract`) before applying global replacements.

## 2026-02-27 - Repository namespace moves require entity metadata updates
- Symptom: moving Doctrine repositories between namespaces can silently break runtime if entity `repositoryClass` attributes still point to old FQCNs.
- Root cause: repository migration impacts both service wiring and ORM metadata references.
- Fix: updated repository imports in all entities (`repositoryClass`) and adjusted DI aliases/config usages in the same slice.
- Prevention: after each repository move, run a grep on `repositoryClass:` and `App\\Repository\\` to ensure zero stale references.

## 2026-02-27 - CSRF field name consistency across admin forms
- Symptom: admin translation functional test passed token but save endpoint behaved like invalid CSRF.
- Root cause: template/test used `_token` while shared admin validator reads `_csrf_token`.
- Fix: aligned translation form and functional test to `_csrf_token`.
- Prevention: for admin POST actions using `AdminCsrfTokenValidatorTrait`, always use `_csrf_token` in forms/tests.

## 2026-02-27 - PHPat boundary guard when relocating shared services
- Symptom: after moving security helpers into `Identity/Infrastructure`, `phpstan` failed on PHPat rule (`UI` layer depending on `Infrastructure`).
- Root cause: `Identity/UI` and `Support/UI` classes injected concrete services (`AuthEventLogger`, `SignedUrlGenerator`) relocated too low in architecture.
- Fix: moved these shared services to `Identity/Application/Security` and kept infra classes depending on application layer.
- Prevention: when reorganizing namespaces for DDD, check PHPat boundaries first; `UI -> Application` is acceptable, `UI -> Infrastructure` is not.

## 2026-02-27 - Backoffice translations POST missed CSRF protection
- Symptom: admin translation save endpoint accepted POST without CSRF token.
- Root cause: controller was protected by role guard but lacked `AdminCsrfTokenValidatorTrait` wiring.
- Fix: added CSRF validation in `ItemTranslationController`, `_token` in Twig form, and functional coverage for invalid token rejection.
- Prevention: every admin POST/PUT/DELETE endpoint must include shared CSRF trait + template token + at least one functional assertion.

## 2026-02-27 - Admin input sanitization drift across controllers
- Symptom: duplicated `optionalString` / `optionalIntOrString` / pagination sanitation logic in multiple admin controllers.
- Root cause: sanitation helpers were added incrementally per controller instead of a shared component.
- Fix: extracted `AdminInputSanitizerTrait` and reused it in `AuditLogController`, `ContactMessageController`, `ItemTranslationController`.
- Prevention: for new admin endpoints, reuse the shared sanitizer trait and avoid local helper duplication.

## 2026-02-27 - Port and adapter with same short class name need alias in tests/adapters
- Symptom: `phpstan` failed with `Cannot use ... as ... because the name is already in use`.
- Root cause: after removing `Interface` suffix, a file imported both the port and adapter class sharing the same short name (`TranslationCatalogWriter`).
- Fix: keep same naming convention but alias one import (`... as YamlTranslationCatalogWriter` / `... as ...Port`) where both are needed.
- Prevention: when suffix-free ports mirror adapter names, check each file for short-name collisions after refactors.

## 2026-02-27 - DDD inventory: legacy root folders explicitly tracked
- Symptom: several legacy root folders remained in `src` during the DDD migration (`Controller`, `Domain`, `Entity`, `EventSubscriber`, `Repository`, `Security`, `Service`) plus empty `src/Translation`.
- Root cause: migration slices prioritized behavior and safety before final namespace placement cleanup.
- Fix: explicit inventory item tracked in backlog/current focus to migrate remaining files into context namespaces and remove empty `src/Translation`.
- Prevention: after each DDD slice batch, run a structural inventory pass on `src` and track leftovers immediately.

## 2026-02-27 - Interface naming and location convention aligned with DDD target
- Symptom: interfaces remained split between `src/Contract` and context namespaces, with inconsistent naming (`*Interface` suffix).
- Root cause: transition refactors prioritized behavior safety over final architectural placement/convention.
- Fix: decision recorded: move ports into context namespaces and use interface names without `Interface` suffix.
- Prevention: new ports should be created directly in context namespaces and follow suffix-free naming.

## 2026-02-27 - Constructor signature changes require synchronized test fixture updates
- Symptom: `phpstan` and unit tests failed after resolver constructor/signature refactor.
- Root cause: test fixtures still instantiated old constructor argument order/count and outdated null-user case.
- Fix: updated all impacted unit tests to new constructor contracts and removed obsolete unauthenticated-path test where typing made it impossible.
- Prevention: after any constructor/signature change, run a targeted search on `new <ClassName>(` and update every fixture before running full quality gates.

## 2026-02-27 - Final classes block PHPUnit doubles in unit tests
- Symptom: unit tests failed with `ClassIsFinalException` while trying to mock a `final` resolver.
- Root cause: test targeted a concrete `final` class instead of a contract.
- Fix: introduced an interface port and wired consumers to it (`ProgressionOwnedPlayerReadPort`).
- Prevention: when a service is expected to be doubled in unit tests, depend on an interface in collaborators.

## 2026-03-08 - Never run functional suite in parallel against shared test DB
- Symptom: massive false negatives in functional tests (deadlocks, missing tables, random 302/404), while code changes were unrelated.
- Root cause: two `make phpunit-functional` runs executed concurrently and both mutated the same `app_test` database lifecycle (drop/create/migrate) at the same time.
- Fix: rerun functional tests in a single serial execution only; ignore parallel run output as invalid.
- Prevention: never launch multiple functional-suite commands concurrently in this project unless isolated test databases are configured per process.

## 2026-02-27 - Unit test on 204 JsonResponse content expected empty string
- Symptom: unit test failed asserting `''` for a `JsonResponse` with HTTP 204.
- Root cause: Symfony `JsonResponse(null, 204)` serializes as `{}` content, not empty string.
- Fix: adjusted assertion to expect `'{}'`.
- Prevention: when asserting raw response body, verify Symfony serializer behavior for each response class/status.

## 2026-02-27 - Mock configured on wrong repository method name
- Symptom: PHPUnit error `MethodCannotBeConfiguredException` while testing owned player read resolver.
- Root cause: test mocked `findOwnedByPublicId` but repository contract method is `findOneByPublicIdAndUser`.
- Fix: updated mock to correct method name and argument order.
- Prevention: always align mocks with interface signatures (not service convenience names).

## 2026-02-27 - Reflection setAccessible deprecated in PHP 8.5 tests
- Symptom: unit suite failed with deprecations treated as errors on `ReflectionProperty::setAccessible()`.
- Root cause: PHP 8.5 deprecates `setAccessible()` (no-op since PHP 8.1).
- Fix: removed `setAccessible()` calls from tests and kept direct `setValue()` usage.
- Prevention: when setting private IDs in tests, use `ReflectionProperty::setValue()` directly.

## 2026-02-26 - Final repositories blocked unit tests in application services
- Symptom: unit tests for new application service failed (`Class ...Repository is declared final and cannot be doubled`), plus weak type guarantees in service result arrays.
- Root cause: application service depended directly on Doctrine repositories (`final`) instead of contracts; result array shapes were too loose for static analysis.
- Fix: introduced dedicated transfer contracts in `src/Contract`, made repositories implement them, and tightened service return union shapes.
- Prevention: new application services should depend on interfaces (ports), not concrete repositories, and use explicit success/error union return shapes.

## 2026-02-26 - Dependency approval must be explicit
- Symptom: dependency discussions happened too late during implementation.
- Root cause: approval/justification flow was not enforced early enough.
- Fix: mandatory policy in `AGENTS.md` and checklist: ask first, explain package, rationale, and fallback cost.
- Prevention: no `composer require`/new tool before explicit user confirmation.

## 2026-02-26 - Public IDs leaked via numeric route params
- Symptom: API routes exposed predictable incremental IDs.
- Root cause: resources used internal DB IDs in public URLs/payloads.
- Fix: added `public_id` for `player` and `item`, migrated routes/lookups to opaque IDs.
- Prevention: all new externally exposed resources must use opaque public IDs.
- Links: `migrations/Version20260226200000.php`

## 2026-02-26 - Temporary link rules duplicated across controllers
- Symptom: TTL/cooldown values were repeated in multiple auth/admin controllers.
- Root cause: no shared policy service for temporary links.
- Fix: introduced `TemporaryLinkPolicy` and centralized durations/cooldowns.
- Prevention: auth link timing rules must live in one service.
- Links: `src/Security/TemporaryLinkPolicy.php`

## 2026-02-26 - Sensitive links lacked URL signature
- Symptom: verify/reset URLs accepted token-only links.
- Root cause: no URL integrity check.
- Fix: introduced `SignedUrlGenerator` (`UriSigner`) and enforced signature check on verify/reset endpoints.
- Prevention: all temporary action links must be signed and validated server-side.

## 2026-02-26 - Dashboard stored selected player in query param
- Symptom: selected player leaked in URL (`?player=...`) and caused noisy links.
- Root cause: front state sync used query params by default.
- Fix: switched selected player persistence to localStorage metadata.
- Prevention: avoid query params for state that is not shareable/business-critical.

## 2026-02-26 - Root memory diverged from project docs
- Symptom: memory content existed in root and docs with risk of drift.
- Root cause: no single source of truth for AI memory.
- Fix: `memory.md` at root now points to `docs/ai/memory.md`.
- Prevention: update only `docs/ai/memory.md`.

## 2026-02-26 - Python command unavailable in shell scripts
- Symptom: ad-hoc script failed with `python: command not found`.
- Root cause: environment did not guarantee `python` binary.
- Fix: prefer shell-native tools (`sed`, `rg`, `apply_patch`) for repo updates.
- Prevention: do not assume Python availability for simple text edits.

## 2026-02-26 - DDD extraction broke controller dependency wiring
- Symptom: `phpstan` reported undefined property in API controller after refactor.
- Root cause: moved item lookup to app service but forgot controller still needed repository for list query.
- Fix: restored explicit `ItemEntityRepository` injection for list endpoint while keeping app service for use-cases.
- Prevention: during incremental extraction, re-check constructor dependencies per endpoint path.

## 2026-02-25 - Functional tests brittle on localized strings
- Symptom: functional tests failed when locale/text changed (e.g. expecting `Bonjour`).
- Root cause: assertions coupled to translated UI labels.
- Fix: switched assertions to stable signals (status code, route, form selector, known data).
- Prevention: never assert on fragile localized wording in functional tests.

## 2026-02-25 - WebTestCase helper naming collision
- Symptom: unexpected behavior around browser/client helpers in tests.
- Root cause: local helper name conflicted with `WebTestCase` static methods.
- Fix: standardized helper name to `browser()` in tests.
- Prevention: avoid helper names that shadow framework test APIs.

## 2026-02-25 - Test DB setup duplicated schema work
- Symptom: redundant schema operations and unstable test setup.
- Root cause: schema recreation in tests while `db-test-init` already handled migrations.
- Fix: rely on migrated schema and isolate tests with `TRUNCATE ... RESTART IDENTITY CASCADE`.
- Prevention: keep one DB bootstrap path for test suites.

## 2026-02-25 - YAML comments lost on translation write
- Symptom: translation files lost section comments after save.
- Root cause: default YAML dumper does not preserve comments.
- Fix: custom writer/renderer for controlled output sections.
- Prevention: use dedicated writer for human-maintained translation files.

## 2026-02-24 - PostgreSQL platform API mismatch (DBAL 4)
- Symptom: migration/runtime error calling undefined platform method (`getName`).
- Root cause: DBAL 4 API differences.
- Fix: platform checks now use `instanceof PostgreSQLPlatform`.
- Prevention: avoid legacy DBAL platform name APIs.

## 2026-02-24 - Duplicate item import on `(type, source_id)`
- Symptom: import failed with unique constraint violation.
- Root cause: same source item encountered multiple times in one import pass.
- Fix: dedup cache during import write mode.
- Prevention: importer must deduplicate by business key before persist.

## 2026-02-24 - DB host `database` unresolved outside Docker
- Symptom: `could not translate host name "database"` on local CLI.
- Root cause: app expected Docker network DNS.
- Fix: run Symfony commands inside app container.
- Prevention: use `make`/docker exec commands as default.

## 2026-02-24 - Build failure around opcache install
- Symptom: Docker build failed while installing `opcache`.
- Root cause: extension already present in base image.
- Fix: removed redundant `docker-php-ext-install opcache`.
- Prevention: verify base image capabilities before adding extension install steps.

## 2026-03-01 - Functional test brittle on fixed createdAt window
- Symptom: admin users functional test could not find row/token with `createdFrom/createdTo` filters.
- Root cause: test used a fixed February 2026 date range while test users were created at current date.
- Fix: explicitly set `createdAt` for involved users inside the asserted range before requesting the page.
- Prevention: when asserting date-range filters, always control timestamps in fixtures instead of relying on `now`.

## 2026-03-01 - Final services reduce testability without ports
- Symptom: unit tests failed while mocking `final` application services (PHPUnit cannot double final classes).
- Root cause: command/service constructors depended on concrete `final` classes instead of application ports.
- Fix: introduced explicit Minerva ports (`Generator`, `Regenerator`, `Refresher`) and injected interfaces.
- Prevention: expose application behavior through interfaces (ports) when class will be consumed/mocked in tests.

## 2026-03-01 - Keep audit read queries out UI controllers
- Symptom: admin Minerva controller contained inline query-builder logic to resolve latest refresh audit summary.
- Root cause: missing application-level read use-case over existing audit read repository.
- Fix: added `LatestMinervaRefreshSummaryApplicationService` + `AuditLogReadRepository::findLatestByActions()` and moved query to infrastructure repository.
- Prevention: when a controller needs persisted read-model data, add/extend an application service/port instead of embedding persistence queries in UI layer.

## 2026-03-01 - Minerva governance docs must match runtime capabilities
- Symptom: governance doc still stated "override not active" while admin UI supported manual overrides.
- Root cause: documentation drift after enabling backoffice override flow.
- Fix: updated governance/runbook to "generated by default, manual override only for incidents, then return to generated" and surfaced this rule in admin UI.
- Prevention: whenever enabling/disabling an operational override mechanism, update both ops docs and the related admin page wording in the same slice.

## 2026-03-01 - Reliability checks need machine-readable output for cron monitoring
- Symptom: Minerva coverage checks were human-readable only, making alerting/parsing harder.
- Root cause: command output format was text-only despite non-zero exit support.
- Fix: added `--format=json` to `app:minerva:refresh-rotation` and `make minerva-refresh-check-json`.
- Prevention: for recurring ops checks, provide both stable exit codes and structured output (JSON) in the same command.

## 2026-03-03 - Keep a fast smoke gate separate from full functional suite
- Symptom: regressions on critical routes could be detected late when only full functional runs were used.
- Root cause: no dedicated quick gate mixing ops drift signal and key functional flows.
- Fix: added `make smoke` (`smoke-ops` + `smoke-app`) and documented thresholds/triage in ops runbook.
- Prevention: run smoke first for rapid signal, then run full `make phpunit-functional` when smoke is green.

## 2026-03-06 - EasyOCR sidecar unstable in local Docker for roadmap scans
- Symptom: OCR chain kept falling back to Tesseract with `EasyOCR HTTP request failed` and `Empty reply from server`.
- Root cause: EasyOCR sidecar process was unstable during heavy inference on this setup (connection dropped / service stopped).
- Fix: removed EasyOCR sidecar path and switched to `ocr.space` HTTP provider as OCR fallback.
- Prevention: for large roadmap-image OCR on constrained local CPU memory, prefer external OCR provider fallback instead of heavy local Python inference service.

## 2026-03-07 - Roadmap cross-locale date drift from OCR day truncation
- Symptom: FR snapshot produced `2026-05-03 -> 2026-06-01` while EN/DE produced `2026-05-28 -> 2026-06-01`, creating low-confidence merge warnings.
- Root cause: OCR truncated a cross-month start day near month-end (`28` read as `3`) on FR raw text.
- Fix: parser now corrects suspicious cross-month ranges when continuity indicates a late-month start; merge also emits explicit `Potential OCR day mismatch` warning when locale buckets split on same end date with large start-day gap.
- Prevention: keep locale-merge warnings enabled and manually review any mismatch warning before approving canonical roadmap.

## 2026-03-10 - Functional test service override lost between requests
- Symptom: roadmap upload functional test created zero snapshots even with an OCR stub provider configured.
- Root cause: `KernelBrowser` rebooted the kernel between GET and POST, dropping the overridden `OcrProviderChain` service from the test container.
- Fix: disabled reboot in that test before overriding the service (`$client->disableReboot()`), so the same container is reused for upload POST.
- Prevention: when a functional test overrides container services and performs multiple requests, disable reboot (or re-inject before each request).

## 2026-03-11 - OCR date parsing regressed on split `BIS` lines and mixed locale snapshots
- Symptom: some roadmap snapshots dropped events or produced broken windows (`end < start`) when OCR split date lines (`... BIS` on one line, continuation later) and when snapshot locale did not match text language.
- Root cause: normalization merged `... BIS` with any next line (including titles), and month resolution was strict to selected locale for month-first patterns.
- Fix: restrict `... BIS` merge to date-like continuations, support deferred continuation lines, and add cross-locale month token fallback (FR/EN/DE maps) for month-first parsing while keeping strict token checks to avoid false positives.
- Prevention: validate parser changes against mixed-locale real snapshots (including legacy uploads) and keep dedicated unit tests for split-date continuations.

## 2026-03-11 - Duplicate range warning should not flag valid overlapping events
- Symptom: roadmap quality flagged `Duplicate range detected` for snapshots where two distinct events intentionally shared the same date window.
- Root cause: validator warning logic keyed only on date range and ignored title difference.
- Fix: duplicate warning now triggers only when both range and normalized title match (true duplicate), not when different events share the same dates.
- Prevention: keep validator tests for both cases (same range + different titles => no warning, same range + same title => warning).
