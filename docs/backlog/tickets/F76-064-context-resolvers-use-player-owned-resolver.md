# F76-064 - Context Resolvers Use PlayerOwnedContextResolver

## Contexte
`PlayerItemActionContextResolver` et `PlayerKnowledgeImportContextResolver` dependaient encore directement de `ProgressionOwnedPlayerApiResolver`, alors qu'un resolver partage `PlayerOwnedContextResolver` existe.

## Scope
- Migrer ces deux resolvers vers `PlayerOwnedContextResolver`.
- Adapter la couverture unitaire existante.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Migrer `PlayerItemActionContextResolver`.
- [x] Migrer `PlayerKnowledgeImportContextResolver`.
- [x] Adapter les tests unitaires associes.
- [x] Verifier phpstan/unit/integration.
