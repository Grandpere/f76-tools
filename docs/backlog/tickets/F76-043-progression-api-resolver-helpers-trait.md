# F76-043 - Progression API Resolver Helpers Trait

## Contexte
Les controllers API progression dupliquent des helpers `resolvePlayerOrNotFound()` (et parfois item) avec la meme logique.

## Scope
- Introduire un trait UI/API pour ces helpers.
- Migrer les controllers progression pour reutiliser ce trait.
- Aucun changement fonctionnel.

## Avancement
- [x] Ajouter trait helpers.
- [x] Migrer controllers cibles.
- [x] Verifier phpstan/unit/integration.
