# F76-058 - Player Controller Trait Removal

## Contexte
`PlayerController` utilisait encore `ProgressionOwnedPlayerApiResolverTrait` pour les actions `show/update/delete`.

## Scope
- Retirer le trait du controller.
- Utiliser explicitement `ProgressionOwnedPlayerApiResolver` avec `getUser()` dans les actions concernees.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Retirer le trait et son method contract.
- [x] Migrer `show`, `update`, `delete` vers le resolver injecte.
- [x] Verifier phpstan/unit/integration.
