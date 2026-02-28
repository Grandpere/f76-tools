# F76-123 - Account security unlink Google

## Contexte
Un utilisateur pouvait lier son compte via Google OIDC, mais ne pouvait pas gerer lui-meme cette liaison depuis son profil securite.

## Scope
- Ajouter une action front `POST /account-security/unlink-google`.
- Proteger l action avec CSRF.
- Appliquer le garde-fou metier:
  - deliaison autorisee uniquement si un mot de passe local est actif.
- Ajouter feedback utilisateur (flash success/warning) + journalisation d evenement securite.
- Ajouter couverture:
  - tests unitaires du service applicatif,
  - tests fonctionnels du profil securite (success + blocage sans mot de passe local).

## Criteres d acceptance
- Un utilisateur connecte avec mot de passe local actif peut delier Google depuis son profil securite.
- Sans mot de passe local, l action est bloquee.
- Le statut de liaison Google reste coherent dans les ecrans admin/export (identite supprimee en base en cas de succes).

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
