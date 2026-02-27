# F76-036 - Player Item Controller Duplication Cleanup

## Contexte
`PlayerItemKnowledgeController` repete la resolution du player et de l'item avec les memes branches d'erreur.

## Scope
- Extraire des methodes privees pour la resolution `player` et `item`.
- Reutiliser ces methodes dans `setLearned` et `unsetLearned`.
- Aucun changement de logique.

## Avancement
- [x] Extraire helpers de resolution.
- [x] Mettre a jour le controller.
- [x] Verifier phpstan + unit + integration.
