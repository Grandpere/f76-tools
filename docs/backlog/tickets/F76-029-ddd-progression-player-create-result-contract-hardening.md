# F76-029 - DDD Slice 19: Progression Player Create Result Contract Hardening

## Contexte
`PlayerController::create()` garde encore un `null-check` defensif sur le player retourne apres un resultat `ok`.

## Scope
- Durcir le contrat de `PlayerCreateResult` pour que la branche succes garantisse un `PlayerEntity`.
- Adapter le controller pour supprimer le `null-check` defensif.
- Ajouter tests unitaires du contrat de resultat.

## Avancement
- [x] Durcir `PlayerCreateResult`.
- [x] Adapter `PlayerController::create()`.
- [x] Ajouter tests unitaires associes.
- [ ] Validation fonctionnelle manuelle (sera faite en lot final).

## Criteres d acceptance
- Aucun changement fonctionnel HTTP.
- Code plus explicite sur le flux create.

## Tests
- Unit: `PlayerApplicationServiceTest` et/ou test dedie du result.
- Functional: `PlayerControllerTest`.

## Risques / rollback
- Risque: incoherence create result/consommation.
- Mitigation: encapsuler la contrainte dans le result et tester le cas conflit/succes.
