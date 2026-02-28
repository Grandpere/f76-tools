# F76-130 - Auth audit log retention command

## Contexte
Les logs d authentification (`auth_audit_log`) grandissent en continu et n avaient pas de mecanisme de retention dedie.

## Scope
- Ajouter un port applicatif de purge auth logs (`countOlderThan`, `deleteOlderThan`).
- Etendre le repository Doctrine des auth logs avec purge par cutoff date.
- Ajouter la commande console `app:auth:audit:purge` avec options:
  - `--days`
  - `--dry-run`
- Ajouter tests unitaires de la commande (happy path + validation option).

## Criteres d acceptance
- La commande retourne le volume purgeable en mode dry-run.
- La commande supprime les logs plus anciens que le seuil en mode normal.
- Les valeurs invalides de `--days` retournent `Command::INVALID`.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

