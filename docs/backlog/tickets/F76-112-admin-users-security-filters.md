# F76-112 - Admin users security filters (verified/local password)

## Contexte
Le backoffice users exposait deja des infos de securite par ligne (`email verified`, `local login`) mais sans filtres dedies, ce qui ralentit les actions admin de support sur des volumes plus grands.

## Scope
- Ajouter deux filtres GET sur `/admin/users`:
  - `verified`: `verified|unverified`
  - `localPassword`: `enabled|disabled`
- Conserver ces filtres dans:
  - formulaires d actions POST (toggle active/admin, reset link, resend verification, unlink google),
  - redirections post-action,
  - selecteur de locale header admin users.
- Ajouter les cles de traduction FR/EN/DE associees.
- Ajouter des tests fonctionnels cibles:
  - filtrage verification,
  - filtrage local password,
  - preservation des filtres en redirection post-action.

## Critere d acceptation
- Un admin peut afficher uniquement les users verifies/non verifies.
- Un admin peut afficher uniquement les users avec/sans mot de passe local.
- Apres une action admin, la redirection conserve `verified` et `localPassword`.
- Pas de regression sur les filtres/sorts/pagination existants.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

## Risques / rollback
- Risque: oubli de propagation des nouveaux query params dans un formulaire d action.
- Mitigation: tests fonctionnels de preservation + rollback simple via revert du ticket.
