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

## 2026-02-27 - Doctrine entities in Infrastructure namespace break PHPat boundaries
- Symptom: after moving entities out of `src/Entity`, PHPat raised many `Application/UI -> Infrastructure` violations and doctrine test bootstrap failed on missing `src/Entity` mapping path.
- Root cause: entities were first moved under `*/Infrastructure/...`, which made every entity type-hint count as an infra dependency; Doctrine config still pointed to `src/Entity`.
- Fix: moved entities to `*/Domain/Entity`, removed `repositoryClass` attributes from domain entities to avoid `Domain -> Infrastructure`, and switched Doctrine mapping to `dir: src` + `prefix: App`.
- Prevention: when removing `src/Entity`, place entities in domain context namespaces and update Doctrine mapping in the same slice.

## 2026-02-27 - UI namespace migration exposes hidden infrastructure coupling
- Symptom: after moving web/security controllers into `.../UI/...`, PHPat started failing on direct infra injections that previously went unnoticed.
- Root cause: legacy `src/Controller/*` classes were outside strict layer checks; once moved under UI namespaces, architectural rules applied.
- Fix: replaced direct infra dependencies with application-level abstractions (`IdentityCaptchaSiteKeyProviderInterface`, `PlayerReadApplicationService`).
- Prevention: when migrating legacy paths into DDD layers, expect PHPat to surface hidden couplings and budget a follow-up decoupling pass in the same slice.

## 2026-02-27 - Moving controllers/repositories can trigger PHPat layer violations
- Symptom: after namespace moves, PHPat reported `Application -> Infrastructure` and `UI -> Infrastructure` violations.
- Root cause: some classes injected concrete repositories (`MinervaRotationEntityRepository`, `UserEntityRepository`) instead of application ports.
- Fix: introduced ports (`MinervaRotationRegenerationRepository`, `AdminUserManagementReadRepositoryInterface`) and rewired dependencies to interfaces.
- Prevention: after structural moves, run phpstan/PHPat early and immediately replace concrete infra dependencies with application-level contracts.

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
- Fix: introduced an interface port and wired consumers to it (`ProgressionOwnedPlayerReadResolverInterface`).
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
