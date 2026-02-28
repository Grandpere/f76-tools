# F76-133 - Support ports without Interface suffix

## Contexte
La convention DDD retenue sur le projet est d eviter le suffixe `Interface` sur les ports applicatifs.
Le contexte `Support` contenait encore plusieurs ports suffixes.

## Scope
- Renommer les ports `Support` suivants sans suffixe:
  - `AdminUserAuditReadRepositoryInterface` -> `AdminUserAuditReadRepository`
  - `AdminUserManagementReadRepositoryInterface` -> `AdminUserManagementReadRepository`
  - `AdminUserManagementWriteRepositoryInterface` -> `AdminUserManagementWriteRepository`
  - `AuditLogReadRepositoryInterface` -> `AuditLogReadRepository`
  - `ContactMessageEmailSenderInterface` -> `ContactMessageEmailSender`
  - `ContactMessageReadRepositoryInterface` -> `ContactMessageReadRepository`
  - `ContactMessageStatusWriteRepositoryInterface` -> `ContactMessageStatusWriteRepository`
- Propager les renommages dans services, repositories, services applicatifs et tests.

## Criteres d acceptance
- Aucun use/type-hint `*Interface` ne subsiste pour ces ports `Support`.
- Les tests unitaires/integration existants restent verts.
- Le wiring DI (`config/services.yaml`) reste valide.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

