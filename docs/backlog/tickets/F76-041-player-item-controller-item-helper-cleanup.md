# F76-041 - Player Item Controller Item-Helper Cleanup

## Contexte
`PlayerItemKnowledgeController` repete encore le pattern `resolve item or 404` + `instanceof JsonResponse`.

## Scope
- Extraire un helper prive `resolveItemOrNotFound()`.
- Reutiliser ce helper dans `setLearned` et `unsetLearned`.
- Aucun changement fonctionnel.

## Avancement
- [x] Extraire helper item.
- [x] Mettre a jour les endpoints write.
- [x] Verifier phpstan/unit/integration.
