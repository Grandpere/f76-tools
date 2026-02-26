# F76-015 - DDD Slice 5: Identity Request Guards

## Contexte
Les controllers Identity sont devenus plus fins, mais les gardes HTTP repetitives (csrf, honeypot, captcha, rate-limit) restent dupliquees dans plusieurs endpoints.
Il faut factoriser ces gardes sans perdre la lisibilite ni la robustesse securite.

## Scope
- Introduire un service applicatif/anti-corruption pour les gardes de requete Identity.
- Centraliser la decision `allow/deny` + raison metier securite.
- Reducer la duplication dans `Registration`, `ForgotPassword`, `ResendVerification`.

## Criteres d acceptance
- Duplication de gardes reduite de facon visible.
- Messages flash et logs securite restent coherents avec le comportement actuel.
- Aucune regression fonctionnelle observable.

## Tests
- Unit: service de guard decision.
- Functional: valider les flows security existants.

## Risques / rollback
- Risque: erreur de mapping entre raison guard et message flash.
- Mitigation: tests unitaires sur mapping + functional sur cas de refus.
