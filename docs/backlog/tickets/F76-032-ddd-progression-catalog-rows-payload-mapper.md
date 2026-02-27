# F76-032 - DDD Slice 22: Progression Catalog Rows Payload Mapper

## Contexte
`PlayerItemKnowledgeController::index()` contient encore une boucle de mapping de rows applicatives vers payload API.

## Scope
- Ajouter une methode dediee dans `PlayerKnowledgeItemPayloadMapper` pour mapper les `catalogRows`.
- Utiliser cette methode dans le controller.
- Ajouter les tests unitaires associes.

## Avancement
- [x] Ajouter `mapCatalogRows()` dans le mapper.
- [x] Simplifier `PlayerItemKnowledgeController::index()`.
- [x] Couvrir les nouveaux cas en unit.

## Criteres d acceptance
- Le controller n'implemente plus la boucle de mapping.
- Le format payload renvoye est strictement identique.

## Tests
- Unit + integration.
- Functional: campagne globale finale.

## Risques / rollback
- Risque faible, refacto UI/API sans changement metier.
