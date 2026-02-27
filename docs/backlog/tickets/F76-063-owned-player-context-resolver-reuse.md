# F76-063 - Owned Player Context Resolver Reuse

## Contexte
Le resolver ajoute en `F76-057` etait nomme `PlayerStatsContextResolver` et limite au controller stats, alors que le meme pattern est utilise dans plusieurs controllers API.

## Scope
- Renommer en `PlayerOwnedContextResolver`.
- Reutiliser ce resolver dans les controllers API qui resolvent uniquement le `player`.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Renommer resolver + test unitaire associe.
- [x] Migrer `PlayerController`, `PlayerStatsController`, `PlayerItemKnowledgeController`, `PlayerKnowledgeTransferController`.
- [x] Verifier phpstan/unit/integration.
