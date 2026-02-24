# Memory

Ce fichier sert de memo de travail pour eviter de reproduire les memes erreurs.

## Preferences utilisateur (a retenir)
- L'utilisateur prefere limiter les executions/outils couteux en tokens quand possible.
- Quand c'est possible, fournir des commandes courtes a executer plutot que de longues sorties.
- Besoin de docs internes de suivi (roadmap/todo) directement dans le repo.
- A chaque correction utilisateur ou autocorrection technique, mettre a jour `memory.md`.
- Si un commit est utile pour fiabiliser un lot de changements, le faire (workflow explicite).
- A chaque nouvelle fonctionnalite, ajouter des tests dans le meme lot (ne pas repousser).

## Decisions techniques prises
- `Item` stocke des cles de traduction (`nameKey`, `descKey`) et non les textes EN/DE en base.
- Traductions generees/maj dans:
  - `translations/items.en.yaml`
  - `translations/items.de.yaml`
- Structure de fichier traduction attendue:
  - section `# misc (legendary mod)`
  - section `# book (minerva plan)`
- `BOOK` peut etre dans plusieurs listes:
  - relation dediee `item_book_list`.
- `MISC` a un seul `rank`:
  - validation metier cote entite,
  - contrainte SQL `CHECK` cote PostgreSQL.

## Problemes rencontres et correctifs
- Host DB `database` non resolu hors Docker:
  - solution: executer les commandes Symfony dans le conteneur `app`.
- Build Docker initial echouait sur `opcache`:
  - retire de `docker-php-ext-install` (deja present dans image PHP).
- Import initial provoquait un duplicate `(type, source_id)`:
  - ajout cache memoire en mode write dans la commande d'import.
- DBAL 4:
  - `getName()` indisponible sur plateforme, utiliser `instanceof PostgreSQLPlatform`.
- YAML dump standard ne conserve pas les commentaires:
  - renderer personnalise pour produire les sections lisibles.
- PHPStan strict:
  - normalisation des types `mixed` dans la commande,
  - suppression des `assert()` inutiles,
  - typing explicite payload, adaptations Doctrine entity IDs.
- Auth phase 1:
  - ajout `UserEntity` + `form_login` + commande `app:user:create`.
  - correction PHPStan `non-empty-string` sur `getUserIdentifier()`:
    - `setEmail()` refuse les emails vides,
    - garde-fou dans `getUserIdentifier()` avec `LogicException` si vide.
- Tests fonctionnels `WebTestCase`:
  - ne pas nommer un helper local `getClient()` (collision avec methode statique de `WebTestCase`).
  - utiliser un nom dedie (`browser()`) pour retourner le `KernelBrowser` initialise en `setUp()`.
- Tests fonctionnels + migrations:
  - ne pas recreer le schema via `SchemaTool` si `make phpunit-functional` lance deja `db-test-init`.
  - utiliser `TRUNCATE ... RESTART IDENTITY CASCADE` en `setUp()` pour isoler les cas.
- Knowledge player/item:
  - modele retenu = presence/absence dans `player_item_knowledge` (pas de bool persiste).
  - endpoints `PUT/DELETE learned` idempotents avec ownership strict sur le `Player`.
- Front catalogue:
  - selection du player actif stockee dans query param `?player=<id>` (pas en session pour l'instant).
  - API knowledge renvoie aussi les textes traduits (`name`, `description`) en plus des cles (`nameKey`, `descKey`).

## Commandes utiles
- Import dry-run:
  - `docker compose exec app php bin/console app:items:import data --dry-run`
- Import reel:
  - `docker compose exec app php bin/console app:items:import data`
- Migration:
  - `docker compose exec app php bin/console doctrine:migrations:migrate -n`
- Analyse statique:
  - `make phpstan`
- Tests:
  - `make phpunit-unit`
  - `make phpunit-functional`
  - `make phpunit-integration`
