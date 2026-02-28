# F76-132 - Audit retention automation

## Contexte
La purge des logs auth/admin etait disponible via commandes separees mais pas unifiee pour une execution planifiee simple.

## Scope
- Ajouter une commande unifiee `app:audit:retention:run`:
  - `--days`
  - `--dry-run`
  - traitement des logs `auth_audit_log` + `admin_audit_log`.
- Ajouter tests unitaires de la commande.
- Ajouter targets Make:
  - `make audit-retention-dry-run`
  - `make audit-retention-run`
- Documenter un exemple de planification cron dans le runbook ops.

## Criteres d acceptance
- Une seule commande permet de purger les deux familles de logs.
- Le mode dry-run affiche un comptage par famille et un total.
- L exploitation dispose d un mode d execution standard via Make + cron.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

