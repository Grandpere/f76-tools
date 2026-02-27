# F76-067 - Typed User Flow In Progression API Contexts

## Contexte
Le flux progression API utilisait encore `mixed $user` dans les resolvers de contexte, ce qui diluait les contrats.

## Scope
- Typer les resolvers de contexte avec `UserEntity`.
- Propager l'utilisateur authentifie explicitement depuis les controllers API.
- Garder `ProgressionOwnedPlayerReadResolver` strictement sur un user deja valide.
- Adapter les tests unitaires associes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Typer `PlayerOwnedContextResolver`, `PlayerItemActionContextResolver`, `PlayerKnowledgeImportContextResolver`.
- [x] Adapter les controllers progression API pour passer `UserEntity`.
- [x] Adapter les tests unitaires associes.
- [x] Verifier phpstan/unit/integration.
