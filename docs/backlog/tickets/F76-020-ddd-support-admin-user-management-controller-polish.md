# F76-020 - DDD Slice 10: Support Admin User Management Controller Polish

## Contexte
Le backoffice utilisateurs fonctionne, mais `UserManagementController` porte encore beaucoup de logique metier inline (toggle actif/admin, reset link, gardes actor/self, mapping flash).

## Scope
- Extraire progressivement les use-cases du controller vers des services applicatifs.
- Introduire des resultats explicites (enum) et des mappers UI pour reduire la logique conditionnelle dans le controller.
- Conserver les routes et messages existants.

## Avancement
- [x] Extraire le use-case `toggle-active` (service + resultat + mapper UI).
- [x] Brancher `UserManagementController::toggleActive()` sur ce use-case.
- [x] Ajouter tests unitaires du service et du mapper.
- [x] Extraire le use-case `toggle-admin` (service + resultat + mapper UI).
- [x] Brancher `UserManagementController::toggleAdmin()` sur ce use-case.
- [ ] Extraire la generation de reset link (service + resultat + mapper UI).

## Criteres d acceptance
- Aucun changement fonctionnel sur `/admin/users/{id}/toggle-active`.
- Controller plus court sur ce path.
- Feedback utilisateur identique.

## Tests
- Unit: service `toggle-active` (not found / self / updated / actor missing), mapper feedback.
- Functional: `UserManagementControllerTest`.

## Risques / rollback
- Risque: divergence de regles self/admin actor.
- Mitigation: enum explicite + tests unitaires ciblant les cas limites.
