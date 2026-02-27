# F76-059 - Player Item Knowledge Controller Trait Removal

## Contexte
`PlayerItemKnowledgeController` utilisait encore `ProgressionOwnedPlayerApiResolverTrait` pour l'action `index`.

## Scope
- Retirer le trait du controller.
- Utiliser explicitement `ProgressionOwnedPlayerApiResolver` dans `index`.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Retirer le trait et son method contract.
- [x] Migrer `index` vers le resolver injecte.
- [x] Verifier phpstan/unit/integration.
