# F76-025 - DDD Slice 15: Progression Player Write Results

## Contexte
`PlayerController` depend encore d exceptions (`PlayerNameConflictException`) pour les conflits de nom lors de create/rename.

## Scope
- Remplacer les exceptions de conflit par des resultats applicatifs explicites pour les ecritures player.
- Adapter `PlayerController` pour consommer ces resultats sans `try/catch`.
- Ajouter tests unitaires des services de write.

## Avancement
- [x] Introduire resultats de write (`create`, `rename`).
- [x] Adapter `PlayerApplicationService`.
- [x] Adapter `PlayerController`.
- [x] Ajouter tests unitaires `PlayerApplicationService`.
- [ ] Validation fonctionnelle manuelle (sera faite en lot final).

## Criteres d acceptance
- Comportement HTTP identique (`409` sur conflit, payload inchange).
- Suppression des `try/catch` de conflit dans `PlayerController`.

## Tests
- Unit: `PlayerApplicationServiceTest`.
- Functional: `PlayerControllerTest`.

## Risques / rollback
- Risque: changement de flux de controle create/rename.
- Mitigation: resultats explicites + tests unitaires et validation fonctionnelle finale.
