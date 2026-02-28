# F76-119 - Admin force email verification

## Contexte
Le support avait seulement le renvoi d email de verification. En cas de blocage utilisateur (mail introuvable, delai), il manque une action admin de validation manuelle.

## Scope
- Ajouter une action POST admin `force-verify-email` sur `/admin/users/{id}`.
- Regles:
  - user absent => warning,
  - deja verifie => warning,
  - sinon marquer `isEmailVerified=true` + nettoyer les tokens/expirations de verification.
- Ajouter bouton d action dans la liste users (visible pour users non verifies).
- Ajouter feedback mapper + messages traduits.
- Ajouter audit logs associes a l action.
- Ajouter tests unitaires service + tests fonctionnels admin/non-admin.

## Critere d acceptance
- Un admin peut verifier manuellement un email non verifie.
- Les donnees de verification temporaires sont purgees.
- L action est tracee dans les audit logs.
- Un non-admin recoit 403.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
