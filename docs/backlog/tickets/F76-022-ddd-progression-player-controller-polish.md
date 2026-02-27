# F76-022 - DDD Slice 12: Progression Player Controller Polish

## Contexte
`PlayerController` contient encore des details techniques HTTP/JSON (decode payload, validation de nom, mapping de reponse) qui peuvent etre extraits.

## Scope
- Extraire le mapping JSON du `PlayerEntity` vers un mapper UI dedie.
- Extraire le parsing/validation du champ `name` depuis la requete JSON vers un composant dedie.
- Conserver strictement les routes, codes HTTP et messages existants.

## Avancement
- [x] Extraire `PlayerPayloadMapper`.
- [x] Extraire `PlayerNameRequestExtractor`.
- [x] Brancher `PlayerController` sur ces composants.
- [x] Ajouter tests unitaires des composants.
- [ ] Validation fonctionnelle manuelle (`make phpunit-functional`) et ajustements si besoin.

## Criteres d acceptance
- Aucune regression fonctionnelle sur les endpoints `/api/players`.
- Controller plus court et limite a l orchestration.

## Tests
- Unit: mapper + extractor.
- Functional: `PlayerControllerTest`.

## Risques / rollback
- Risque: changement involontaire de validation du nom.
- Mitigation: conserver exactement les regles actuelles (string trim non vide) + tests unitaires.
