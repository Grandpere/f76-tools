# F76-077 - Admin Audit And Contact Query Objects

## Contexte
Les services admin audit/contact prenaient des parametres `mixed` et faisaient eux-memes la sanitation, ce qui diluait les contrats applicatifs.

## Scope
- Introduire des objets de requete typés avec `fromRaw(...)`.
- Migrer les services audit/contact pour accepter ces query objects.
- Migrer les controllers admin qui invoquent ces services.
- Adapter les tests unitaires associes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `AuditLogListQuery`, `AuditLogExportQuery`, `ContactMessageListQuery`.
- [x] Migrer `AuditLogListApplicationService`, `AuditLogExportApplicationService`, `ContactMessageListApplicationService`.
- [x] Migrer `AuditLogController` et `ContactMessageController`.
- [x] Adapter les tests unitaires associes.
- [x] Verifier phpstan/unit/integration.
