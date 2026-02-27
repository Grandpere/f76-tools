# F76-081 - Admin Boundary Typing Hardening

## Contexte
Il restait des signatures `mixed` sur des frontieres admin/progression (contextes user, query objects et status request), alors que le typage pouvait etre explicitement restreint sans changer le comportement.

## Scope
- Taper les contextes user sur `?UserInterface`.
- Taper les query objects admin (`AuditLogListQuery`, `AuditLogExportQuery`, `ContactMessageListQuery`).
- Taper `ContactMessageStatusUpdateRequest::fromRaw`.
- Adapter les controllers admin et tests associes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Taper `ProgressionApiUserContext` et `AdminAuthenticatedUserContext`.
- [x] Taper les query/status request objects admin.
- [x] Adapter `AuditLogController` et `ContactMessageController`.
- [x] Adapter les tests unitaires associes.
- [x] Verifier phpstan/unit/integration.
