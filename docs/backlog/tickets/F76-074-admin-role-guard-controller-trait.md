# F76-074 - Admin Role Guard Controller Trait

## Contexte
Les controllers admin repetaient des appels directs `denyAccessUnlessGranted('ROLE_ADMIN')`.

## Scope
- Introduire un trait controller partage pour la garde admin.
- Migrer les controllers admin qui utilisent ce pattern.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `AdminRoleGuardControllerTrait`.
- [x] Migrer `AuditLogController`, `ContactMessageController`, `MinervaRotationController`, `UserManagementController`.
- [x] Verifier phpstan/unit/integration.
