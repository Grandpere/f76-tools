# F76-003 - URL Hygiene POST + CSRF

## Contexte
Une partie des actions est deja durcie (URLs signees, IDs opaques). Il reste a auditer les actions sensibles encore pilotables en GET/query params.

## Scope
- Inventaire des actions sensibles.
- Basculer vers POST + CSRF quand pertinent.
- Documenter les exceptions.

## Avancement
- [x] Audit des routes sensibles (admin/auth/API).
- [x] `logout` bascule en `POST + CSRF` (plus de deconnexion via URL GET previsible).
- [x] Documentation des exceptions justifiees (liens verifies/signes).
- [x] Test functional ajoute: un `GET /logout` ne doit plus invalider la session active.

## Exceptions documentees
- `GET /verify-email/{token}`: action state-changing, mais protegee par token aleatoire + signature URL.
- `GET /reset-password/{token}`: affichage de formulaire protege par token aleatoire + signature URL (modification effective en POST + CSRF).

## Criteres d acceptance
- Aucune action sensible n est declenchable par URL previsible seule.
- CSRF present sur toutes les actions state-changing.

## Tests
- Functional sur actions admin et auth impactees.

## Risques / rollback
- Risque de rupture UX sur liens existants. Mitigation: redirects explicites et messages clairs.
