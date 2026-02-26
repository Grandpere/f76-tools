# F76-018 - DDD Slice 8: Support Admin Contact Status Update

## Contexte
Le backoffice contact fonctionne, mais le controller admin porte encore la logique metier de transition de statut (validation status + lookup + save + mapping flash).

## Scope
- Extraire le use-case "mettre a jour le statut d un message contact" dans un service applicatif.
- Retourner un resultat explicite (enum) pour que le controller ne fasse que mapping UI.
- Conserver routes, CSRF et messages flash existants.

## Avancement
- [x] Ajouter `ContactMessageStatusUpdateApplicationService` + `ContactMessageStatusUpdateResult`.
- [x] Brancher `ContactMessageController::setStatus()` sur ce service.
- [x] Ajouter tests unitaires du service.
- [x] Extraire la sanitation/pagination de `index()` dans `ContactMessageListApplicationService` (+ tests unitaires).

## Criteres d acceptance
- Aucune regression fonctionnelle sur `/admin/contact-messages`.
- Code controller reduit sur le path POST status.
- Comportement flash identique.

## Tests
- Unit: service de status update (not found / invalid / success).
- Functional: `ContactMessageControllerTest`.

## Risques / rollback
- Risque: mapping flash/resultat incoherent.
- Mitigation: enum de resultat explicite + tests unitaires.
