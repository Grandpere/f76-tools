# F76-049 - Player Item Controller Context Resolver Cleanup

## Contexte
Les endpoints `setLearned` et `unsetLearned` repetent la meme resolution `player + item` puis branches d'erreur.

## Scope
- Extraire un helper prive qui retourne `player + item` ou `JsonResponse` d'erreur.
- Reutiliser ce helper dans `setLearned` et `unsetLearned`.
- Aucun changement de comportement.

## Avancement
- [x] Extraire helper de contexte.
- [x] Migrer les 2 endpoints.
- [x] Verifier phpstan/unit/integration.
