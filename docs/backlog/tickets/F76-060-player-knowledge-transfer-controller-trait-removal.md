# F76-060 - Player Knowledge Transfer Controller Trait Removal

## Contexte
`PlayerKnowledgeTransferController` utilisait encore `ProgressionOwnedPlayerApiResolverTrait` pour l'action `export`.

## Scope
- Retirer le trait du controller.
- Utiliser explicitement `ProgressionOwnedPlayerApiResolver` dans `export`.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Retirer le trait et son method contract.
- [x] Migrer `export` vers le resolver injecte.
- [x] Verifier phpstan/unit/integration.
