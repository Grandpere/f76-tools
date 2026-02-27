# F76-046 - Progression Resolver Traits Ergonomics

## Contexte
Le trait actuel impose des appels verbeux (`resolveXxxOrNotFound($resolver, $id)`), ce qui degrade la lisibilite des controllers.

## Scope
- Scinder en 2 traits plus ergonomiques:
  - owned player resolver trait,
  - item resolver trait.
- Simplifier les appels dans les controllers.
- Mettre a jour la couverture unitaire associee.

## Avancement
- [x] Introduire les 2 nouveaux traits.
- [x] Migrer controllers progression.
- [x] Mettre a jour tests unitaires.
- [x] Verifier phpstan/unit/integration.
