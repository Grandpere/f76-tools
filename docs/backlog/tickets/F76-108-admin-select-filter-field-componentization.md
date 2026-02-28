# F76-108 - Admin Select Filter Field Componentization

## Contexte
Les champs `select` de filtres admin (notamment `status` et `action`) restaient dupliques dans les templates.

## Scope
- Extraire un composant Twig partage pour les champs `select` de filtres.
- Integrer ce composant dans les vues admin concernees.
- Supporter:
  - option vide (`any_*`),
  - options objet (`{ value, ... }`) et options string simples,
  - prefixe de traduction optionnel pour les labels d'options.

## Criteres d acceptance
- Le markup des `select` de filtres est centralise.
- Contact messages et audit logs utilisent le composant partage.
- Aucun changement fonctionnel des filtres.

## Avancement
- [x] Composant cree: `templates/admin/_select_filter_field.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/admin/contact_messages.html.twig` (`status`).
  - [x] `templates/admin/audit_logs.html.twig` (`action`).

## Statut
- Done - 2026-02-28
