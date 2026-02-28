# F76-126 - Auth rate-limit hardening for register/forgot/resend

## Contexte
Les flows `register`, `forgot-password` et `resend-verification` utilisaient des limites identiques; il fallait des fenetres/quotas explicites et verifies par flow.

## Scope
- Definir des limites dediees par flow dans `IdentityEmailFlow`:
  - register: `3` tentatives / `600s`
  - forgot-password: `3` tentatives / `900s`
  - resend-verification: `3` tentatives / `1800s`
- Conserver le flow contact actuel (`5` / `300s`).
- Ajouter/adapter tests unitaires et fonctionnels cibles sur ces limites.

## Criteres d acceptance
- Les trois flows sont limites selon leurs parametres dedies.
- Les redirects/messages de throttling restent coherents.
- La couverture de test verrouille ces reglages.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
