# F76-047 - Player Controller Name Extraction Cleanup

## Contexte
`PlayerController::create()` et `update()` repetent la meme extraction/validation de nom.

## Scope
- Extraire un helper prive `extractPlayerNameOrInvalid()`.
- Reutiliser ce helper dans `create` et `update`.
- Aucun changement comportemental.

## Avancement
- [x] Extraire helper.
- [x] Migrer create/update.
- [x] Verifier phpstan/unit/integration.
