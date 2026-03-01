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
