# F76-028 - DDD Slice 18: Progression Player Controller Write Responder

## Contexte
`PlayerController` contient encore du mapping HTTP direct pour les actions d ecriture (`create`, `update`, `delete`).

## Scope
- Extraire un responder dedie pour les actions write du player controller.
- Brancher le controller sur ce responder pour uniformiser les reponses.
- Conserver strictement status codes et payloads existants.

## Avancement
- [x] Extraire `PlayerControllerWriteResponder`.
- [x] Brancher `PlayerController`.
- [x] Ajouter tests unitaires du responder.
- [ ] Validation fonctionnelle manuelle (sera faite en lot final).

## Criteres d acceptance
- Aucun changement fonctionnel sur `POST/PATCH/DELETE /api/players`.
- Controller plus lisible sur les branches write.

## Tests
- Unit: `PlayerControllerWriteResponderTest`.
- Functional: `PlayerControllerTest`.

## Risques / rollback
- Risque: divergence de codes HTTP.
- Mitigation: tests unitaires explicites sur chaque branche.
