# F76-056 - Progression API Context Resolvers Extraction

## Contexte
Les controllers API progression contiennent encore des helper prives de resolution de contexte (`player+item`, `player+payload`).

## Scope
- Extraire ces resolutions dans des composants UI/API dedies.
- Simplifier `PlayerItemKnowledgeController` et `PlayerKnowledgeTransferController`.
- Ajouter tests unitaires des nouveaux resolvers.

## Avancement
- [x] Ajouter resolver `PlayerItemActionContextResolver`.
- [x] Ajouter resolver `PlayerKnowledgeImportContextResolver`.
- [x] Migrer controllers.
- [x] Ajouter tests unitaires.
- [x] Verifier phpstan/unit/integration.
