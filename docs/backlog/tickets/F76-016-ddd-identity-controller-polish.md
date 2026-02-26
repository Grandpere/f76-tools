# F76-016 - DDD Slice 6: Identity Controller Polish

## Contexte
Les use-cases Identity sont extraits, mais certains controllers conservent encore du code de mapping HTTP repetitif (status -> flash key, redirect target).
Un dernier polish peut encore reduire le bruit UI sans changer le comportement.

## Scope
- Introduire des petits mappers UI dedies (par flux) pour convertir resultats applicatifs en feedback HTTP.
- Garder les controllers concentres sur orchestration minimale.
- Eviter toute rupture de routes/messages existants.

## Criteres d acceptance
- Duplication de mapping reduite.
- Messages flash existants conserves.
- Aucune regression fonctionnelle.

## Tests
- Unit: mappers UI.
- Functional: login/register/verify/forgot/reset/resend.

## Risques / rollback
- Risque: divergence entre status metier et flash key.
- Mitigation: tests unitaires exhaustifs sur mapping.
