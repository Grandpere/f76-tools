# F76-061 - Remove Unused Owned Player Resolver Trait

## Contexte
Apres les slices precedents, `ProgressionOwnedPlayerApiResolverTrait` n'est plus utilise dans le code applicatif.

## Scope
- Supprimer `ProgressionOwnedPlayerApiResolverTrait`.
- Supprimer le test unitaire du trait devenu obsolete.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Supprimer le trait inutilise.
- [x] Supprimer son test unitaire obsolete.
- [x] Verifier phpstan/unit/integration.
