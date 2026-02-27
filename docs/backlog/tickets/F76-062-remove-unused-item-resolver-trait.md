# F76-062 - Remove Unused Item Resolver Trait

## Contexte
Apres les extractions de resolvers UI/API, `ProgressionItemApiResolverTrait` n'est plus utilise dans le code applicatif.

## Scope
- Supprimer `ProgressionItemApiResolverTrait`.
- Supprimer le test unitaire du trait devenu obsolete.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Supprimer le trait inutilise.
- [x] Supprimer son test unitaire obsolete.
- [x] Verifier phpstan/unit/integration.
