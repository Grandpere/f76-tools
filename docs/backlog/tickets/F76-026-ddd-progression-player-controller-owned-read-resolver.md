# F76-026 - DDD Slice 16: Progression Player Controller Owned Read Resolver

## Contexte
`PlayerController` duplique encore la resolution du player proprietaire (`user + publicId`) pour `show/update/delete`.

## Scope
- Introduire un resolver UI partage base sur `PlayerReadApplicationService`.
- Brancher `PlayerController` sur ce resolver pour `show/update/delete`.
- Conserver routes, status codes et payloads existants.

## Avancement
- [x] Extraire `ProgressionOwnedPlayerReadResolver`.
- [x] Brancher `PlayerController` sur ce resolver.
- [x] Ajouter test unitaire du resolver.
- [x] Validation fonctionnelle manuelle (faite).

## Criteres d acceptance
- Aucune regression fonctionnelle sur `show/update/delete`.
- Moins de duplication dans `PlayerController`.

## Tests
- Unit: `ProgressionOwnedPlayerReadResolverTest`.
- Functional: `PlayerControllerTest`.

## Risques / rollback
- Risque: mauvaise resolution ownership.
- Mitigation: resolver base sur `PlayerReadApplicationService` existant + test unitaire.
