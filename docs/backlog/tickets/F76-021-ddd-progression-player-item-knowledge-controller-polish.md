# F76-021 - DDD Slice 11: Progression Player Item Knowledge Controller Polish

## Contexte
`PlayerItemKnowledgeController` concentre encore la logique de listing des items (chargement catalogue + map des acquis) en plus de la couche HTTP.

## Scope
- Extraire le use-case de listing (`GET /api/players/{playerId}/items`) dans un service applicatif progression.
- Introduire des ports de lecture pour isoler la couche application des repositories Doctrine concrets.
- Conserver strictement le contrat API existant (routes, payload, codes HTTP).

## Avancement
- [x] Introduire les interfaces de lecture catalogue/connaissance.
- [x] Extraire `PlayerKnowledgeCatalogApplicationService`.
- [x] Brancher `PlayerItemKnowledgeController::index()` sur ce service.
- [x] Ajouter tests unitaires du service.
- [x] Extraire le mapping payload item vers `PlayerKnowledgeItemPayloadMapper`.
- [x] Brancher `index` et `setLearned` sur ce mapper dedie.
- [x] Extraire le filtrage de recherche vers `PlayerKnowledgeItemPayloadSearchFilter`.
- [x] Supprimer la logique inline de recherche depuis le controller.
- [ ] Validation fonctionnelle manuelle (`make phpunit-functional`) et ajustements si besoin.

## Criteres d acceptance
- Pas de changement fonctionnel observable sur l’endpoint index.
- Controller plus fin sur le listing.
- Tests unitaires couvrant les cas avec/sans acquis.

## Tests
- Unit: service de listing (type null/type filtre, learned map).
- Functional: `PlayerItemKnowledgeControllerTest`.

## Risques / rollback
- Risque: regression du flag `learned` sur le payload.
- Mitigation: test unitaire dedie + functional existant.
