# F76-019 - DDD Slice 9: Support Admin Audit Logs Controller Polish

## Contexte
Le backoffice des logs d audit fonctionne, mais le controller porte encore de la logique repetitive (sanitation query/action/page/perPage et pagination clamp).

## Scope
- Extraire la query list des logs d audit dans un service applicatif dedie.
- Retourner un resultat explicite pour simplifier le controller.
- Conserver le comportement existant (filtres, pagination, tri, export inchange).

## Avancement
- [x] Ajouter `AuditLogListApplicationService` + `AuditLogListResult`.
- [x] Introduire un port read repository pour lister les logs + actions distinctes.
- [x] Brancher `AuditLogController::__invoke()` sur ce service.
- [x] Ajouter tests unitaires du service de listing.

## Criteres d acceptance
- Aucune regression fonctionnelle sur `/admin/audit-logs`.
- Controller plus court sur le path GET listing.
- Comportement pagination/filtres identique.

## Tests
- Unit: service de listing (sanitation, defaults, clamp page).
- Functional: `AuditLogControllerTest`.

## Risques / rollback
- Risque: desync entre comportement actuel et service extrait.
- Mitigation: tests unitaires ciblant les cas limites + tests fonctionnels existants.
