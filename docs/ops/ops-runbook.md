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
- Purge dry-run (aucune suppression):
  - `docker compose -f compose.yaml exec -T app php bin/console app:admin:audit:purge --days=90 --dry-run`
- Purge reelle:
  - `docker compose -f compose.yaml exec -T app php bin/console app:admin:audit:purge --days=90`
- Export CSV via UI:
  - Backoffice > Logs d audit > `Export CSV`

## Rotation Minerva
- Generation dry-run:
  - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31 --dry-run`
- Generation reelle:
  - `docker compose -f compose.yaml exec -T app php bin/console app:minerva:generate-rotation --from=2026-01-01 --to=2026-12-31`
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
