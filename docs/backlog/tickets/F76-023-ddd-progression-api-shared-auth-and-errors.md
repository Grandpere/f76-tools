# F76-023 - DDD Slice 13: Progression API Shared Auth And Error Responders

## Contexte
Les controllers API progression dupliquent encore la resolution de l utilisateur authentifie et plusieurs reponses JSON d erreur (`Player not found.`, etc.).

## Scope
- Introduire un composant partage de contexte utilisateur API progression.
- Introduire un responder JSON partage pour les erreurs API progression recurrentes.
- Introduire un decodeur JSON partage pour payloads API progression.
- Brancher au minimum `PlayerController`, `PlayerItemKnowledgeController`, `PlayerKnowledgeTransferController`, `PlayerStatsController`.
- Conserver routes, status codes et messages existants.

## Avancement
- [x] Extraire `ProgressionApiUserContext`.
- [x] Extraire `ProgressionApiErrorResponder`.
- [x] Extraire `ProgressionApiJsonPayloadDecoder`.
- [x] Extraire `ProgressionOwnedPlayerResolver`.
- [x] Brancher les controllers progression cibles.
- [x] Ajouter tests unitaires des nouveaux composants.
- [ ] Validation fonctionnelle manuelle (`make phpunit-functional`) et ajustements si besoin.

## Criteres d acceptance
- Aucune regression fonctionnelle sur les endpoints progression.
- Reduction visible de duplication dans les controllers.

## Tests
- Unit: `ProgressionApiUserContext`, `ProgressionApiErrorResponder`.
- Functional: suites API progression existantes.

## Risques / rollback
- Risque: divergence de messages/status d erreur.
- Mitigation: conserver les memes messages/stats qu actuellement et valider par functional.
