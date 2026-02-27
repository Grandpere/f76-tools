# F76-079 - Admin CSRF Token Validator Trait

## Contexte
Plusieurs controllers admin dupliquaient la meme logique de validation CSRF (`_csrf_token` + `CsrfTokenManagerInterface`).

## Scope
- Introduire un trait partage de validation CSRF.
- Migrer les controllers admin concernes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `AdminCsrfTokenValidatorTrait`.
- [x] Migrer `UserManagementController`, `ContactMessageController`, `MinervaRotationController`.
- [x] Verifier phpstan/unit/integration.
