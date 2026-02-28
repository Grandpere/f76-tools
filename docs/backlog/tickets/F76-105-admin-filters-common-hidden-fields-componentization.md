# F76-105 - Admin Filters Common Hidden Fields Componentization

## Contexte
Les formulaires de filtres admin dupliquaient les champs caches communs `locale` et `page=1`.

## Scope
- Extraire un composant Twig partage pour ces champs caches communs.
- Integrer le composant dans les vues admin concernees.

## Criteres d acceptance
- Les champs caches `locale/page` ne sont plus dupliques.
- Les formulaires de filtres conservent le meme comportement.

## Avancement
- [x] Composant cree: `templates/admin/_filters_common_hidden_fields.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/admin/item_translations.html.twig`
  - [x] `templates/admin/contact_messages.html.twig`
  - [x] `templates/admin/audit_logs.html.twig`

## Statut
- Done - 2026-02-28
