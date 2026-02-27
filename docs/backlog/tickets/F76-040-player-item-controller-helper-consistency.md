# F76-040 - Player Item Controller Helper Consistency

## Contexte
`PlayerItemKnowledgeController` utilise deja un helper pour la resolution player dans les endpoints write, mais `index` utilisait encore l'appel resolver direct.

## Scope
- Reutiliser le helper `resolvePlayerOrNotFound()` dans `index`.
- Aucun changement de comportement.

## Avancement
- [x] Uniformiser `index` avec le helper.
- [x] Verifier phpstan/unit/integration.
