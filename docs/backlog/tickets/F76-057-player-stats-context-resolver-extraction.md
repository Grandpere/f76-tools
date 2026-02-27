# F76-057 - Player Stats Context Resolver Extraction

## Contexte
`PlayerStatsController` resolvait encore le player via le trait controller (`ProgressionOwnedPlayerApiResolverTrait`).

## Scope
- Extraire la resolution de contexte dans un composant UI/API dedie.
- Simplifier `PlayerStatsController` en retirant la logique de resolution.
- Ajouter une couverture unitaire du resolver.

## Avancement
- [x] Ajouter `PlayerStatsContextResolver`.
- [x] Migrer `PlayerStatsController` vers ce resolver.
- [x] Ajouter tests unitaires.
- [x] Verifier phpstan/unit/integration.
