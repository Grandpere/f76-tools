# F76-066 - Owned Player Read Resolver User Typing

## Contexte
Le resolver de lecture utilisait encore `mixed $user`, alors que la validation d'authentification peut etre centralisee au niveau `PlayerOwnedContextResolver`.

## Scope
- Typer `ProgressionOwnedPlayerReadResolverInterface::resolve` avec `UserEntity`.
- Deplacer la validation d'authentification dans `PlayerOwnedContextResolver`.
- Adapter les tests unitaires associes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Typer l'interface et l'implementation de lecture.
- [x] Deplacer la validation auth dans `PlayerOwnedContextResolver`.
- [x] Adapter la couverture unitaire.
- [x] Verifier phpstan/unit/integration.
