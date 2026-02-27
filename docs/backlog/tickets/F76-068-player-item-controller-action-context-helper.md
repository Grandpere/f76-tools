# F76-068 - Player Item Controller Action Context Helper

## Contexte
`PlayerItemKnowledgeController` dupliquait la resolution `player+item` dans `setLearned` et `unsetLearned`.

## Scope
- Extraire un helper prive de resolution de contexte d'action.
- Simplifier les actions `setLearned` et `unsetLearned`.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter helper prive `resolveActionContextOrResponse`.
- [x] Simplifier `setLearned` et `unsetLearned`.
- [x] Verifier phpstan/unit/integration.
