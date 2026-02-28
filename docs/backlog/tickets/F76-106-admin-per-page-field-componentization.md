# F76-106 - Admin Per-Page Field Componentization

## Contexte
Le champ de filtre `perPage` (label + select + options) etait duplique dans les vues admin paginees.

## Scope
- Extraire un composant Twig partage pour le champ `perPage`.
- Integrer le composant dans les vues admin concernees.
- Conserver les jeux d'options existants selon la page.

## Criteres d acceptance
- Le markup du champ `perPage` est centralise.
- Les vues admin utilisent le composant avec leurs options respectives.
- Aucun changement fonctionnel des filtres.

## Avancement
- [x] Composant cree: `templates/admin/_per_page_field.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/admin/item_translations.html.twig` (`20,40,80,120`)
  - [x] `templates/admin/contact_messages.html.twig` (`20,30,50,100`)
  - [x] `templates/admin/audit_logs.html.twig` (`20,30,50,100`)

## Statut
- Done - 2026-02-28
