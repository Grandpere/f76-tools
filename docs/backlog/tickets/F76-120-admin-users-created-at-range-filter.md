# F76-120 - Admin users created-at range filter

## Contexte
La vue `/admin/users` permettait deja de filtrer par statut/role/google mais pas par periode de creation de compte, utile pour support/audit et pour les exports.

## Scope
- Ajouter deux filtres date:
  - `createdFrom` (date debut),
  - `createdTo` (date fin, inclusive).
- Appliquer le filtre sur:
  - la table users,
  - l export CSV users.
- Preserver ces params dans:
  - formulaires d actions POST,
  - redirections post-action,
  - switch locale.
- Ajouter traductions FR/EN/DE.
- Ajouter tests fonctionnels:
  - filtrage par plage,
  - preservation des params en redirection.

## Critere d acceptance
- La liste users respecte la plage de creation demandee.
- L export CSV respecte la meme plage.
- Les actions admin n effacent pas ces filtres en redirection.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
