# Checklists

Reusable, update-friendly checklists for delivery quality.

## 1) Daily implementation checklist
- [ ] Confirm target ticket ID (e.g. `F76-xxx`).
- [ ] Confirm scope and out-of-scope from ticket file.
- [ ] Identify impacted layers (`Domain/Application/Infrastructure/UI`).
- [ ] Implement smallest viable slice.
- [ ] Add/update tests for changed behavior (mandatory):
- [ ] `Unit` for domain/application rules.
- [ ] `Integration` for persistence/repository/infrastructure behavior.
- [ ] `Functional` tests are prepared/updated for HTTP/security/UI/API flows when endpoints/pages are touched.
- [ ] Ask the user to run `make phpunit-functional` manually and share any errors (agent does not run it automatically).
- [ ] Run quality gates locally.
- [ ] Update docs if behavior/contracts changed.

## 2) Review checklist
- [ ] Clear summary: problem, approach, impact.
- [ ] Security/ownership implications documented.
- [ ] Migration impact documented (if any).
- [ ] Backward compatibility impact documented.
- [ ] Commands run and results listed.
- [ ] Screenshots/HTTP examples added for UI/API changes when relevant.

## 3) Symfony/API change checklist
- [ ] Route requirements validate IDs strictly (opaque ID regex).
- [ ] Invalid input returns controlled 4xx (not 500).
- [ ] DTO/payload validation rules updated.
- [ ] Provider/manager/controller behavior covered by tests.

## 4) Database/migration checklist
- [ ] `make db-diff` only when migrations are up to date.
- [ ] Migration SQL reviewed (indexes, constraints, FK actions).
- [ ] Test database migration executed.
- [ ] Roll-forward path verified (no destructive surprises).

## 5) Async/process checklist (if Messenger introduced)
- [ ] Message + handler are idempotent.
- [ ] Retry strategy defined.
- [ ] Failure state persisted and readable.
- [ ] Logs include correlation context.
- [ ] Manual reprocess path defined if needed.

## 6) Front/UI checklist
- [ ] Mobile + desktop behavior verified.
- [ ] Empty/loading/error states are visible and usable.
- [ ] Filtering/sorting/export parity verified.
- [ ] Forms have server-side validation and clear feedback.

## 7) Importmap/assets checklist
- [ ] New JS package added to `importmap.php`.
- [ ] `php bin/console importmap:install` executed (if required).
- [ ] Turbo/frame reload behavior verified.

## 8) Final local gate checklist
- [ ] `make phpstan`
- [ ] `make phpunit-unit`
- [ ] `make phpunit-integration`
- [ ] `make php-cs-fixer-check`
- [ ] If functional coverage is impacted: ask user to run `make phpunit-functional`, then process returned errors.

## Maintenance rule
When a recurring issue appears, append a short entry in `docs/ai/memory.md` and, if process-related, update this checklist file.
