# F76-124 - Account active sessions and revoke others

## Contexte
Le profil securite n offrait pas de controle des sessions actives utilisateur (multi-device), ce qui limite la reponse en cas de session compromisee.

## Scope
- Ajouter un registre des sessions actives par utilisateur (cache applicatif).
- Afficher les sessions actives sur `/account-security`.
- Ajouter une action `POST /account-security/logout-other-sessions` (CSRF) pour conserver uniquement la session courante.
- Ajouter un controle a chaque requete authentifiee:
  - si la session courante n est plus active, forcer la deconnexion.

## Criteres d acceptance
- Le profil securite liste les sessions actives.
- L action "deconnecter les autres sessions" invalide les autres sessions.
- Une session invalidee est forcee a se reconnecter.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
