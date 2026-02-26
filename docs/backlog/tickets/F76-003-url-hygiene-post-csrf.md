# F76-003 - URL Hygiene POST + CSRF

## Contexte
Une partie des actions est deja durcie (URLs signees, IDs opaques). Il reste a auditer les actions sensibles encore pilotables en GET/query params.

## Scope
- Inventaire des actions sensibles.
- Basculer vers POST + CSRF quand pertinent.
- Documenter les exceptions.

## Criteres d acceptance
- Aucune action sensible n est declenchable par URL previsible seule.
- CSRF present sur toutes les actions state-changing.

## Tests
- Functional sur actions admin et auth impactees.

## Risques / rollback
- Risque de rupture UX sur liens existants. Mitigation: redirects explicites et messages clairs.
