# Ops Runbook

## Contexte
Ce document regroupe les commandes d exploitation courantes pour ce projet Symfony (Docker).

## Prerequis
- Stack Docker disponible.
- Execution depuis la racine du projet.

## Commandes de base
- Demarrer la stack:
  - `make up`
- Arreter la stack:
  - `make down`
- Ouvrir un shell dans le conteneur app:
  - `make shell`

## Base de donnees
- Initialiser/reinitialiser la DB (drop + create + migrations):
  - `make db-init`
- Appliquer les migrations:
  - `make db-migrate`

## Import des items
- Dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:items:import data --dry-run`
- Import reel:
  - `docker compose -f compose.yaml exec -T app php bin/console app:items:import data`

## Utilisateurs
- Creer un utilisateur:
  - `docker compose -f compose.yaml exec -T app php bin/console app:user:create user@example.com --password='secret123'`
- Promouvoir admin:
  - `docker compose -f compose.yaml exec -T app php bin/console app:user:promote-admin user@example.com`

## Audit logs
- Purge unifiee dry-run (auth + admin):
  - `make audit-retention-dry-run`
- Purge unifiee reelle (auth + admin):
  - `make audit-retention-run`
- Purge dediee admin audit dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:admin:audit:purge --days=90 --dry-run`
- Purge dediee auth audit dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:auth:audit:purge --days=90 --dry-run`
- Export CSV via UI:
  - Backoffice > Logs d audit > `Export CSV`
- Export CSV auth par utilisateur via UI:
  - Backoffice > Utilisateurs > Activite securite > `Export CSV`

## Planification cron
- Exemple de cron quotidien (02:15) depuis l hote Docker:
  - `15 2 * * * cd /chemin/vers/f76 && make audit-retention-run >> var/log/audit-retention.log 2>&1`
- Exemple de cron quotidien Minerva (02:30):
  - `30 2 * * * cd /chemin/vers/f76 && make minerva-refresh-run >> var/log/minerva-refresh.log 2>&1`
- Recommandation:
  - lancer d abord quelques jours en `audit-retention-dry-run` pour verifier les volumes.
  - lancer quelques jours `minerva-refresh-dry-run` avant activation cron.

## Rotation Minerva
- Generation dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31 --dry-run`
- Generation reelle:
  - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31`
- Refresh couverture dry-run (horizon glissant):
  - `make minerva-refresh-dry-run`
- Refresh couverture dry-run strict (exit code non-zero si trous):
  - `make minerva-refresh-check`
- Refresh couverture reelle (horizon glissant):
  - `make minerva-refresh-run`
- Backoffice admin:
  - `GET /admin/minerva-rotation` (formulaire de regeneration + visualisation timeline).
- Page publique authentifiee:
  - `GET /minerva-rotation`.
- Gouvernance source:
  - `docs/ops/minerva-governance.md`.

## Qualite
- Analyse statique:
  - `make phpstan`
- Tests unitaires:
  - `make phpunit-unit`
- Tests fonctionnels:
  - `make phpunit-functional`

## Notes
- Les tests fonctionnels et integration recreent la DB de test.
- En pratique, lancer `make phpstan` + `make phpunit-unit` avant les suites plus couteuses.
