# F76-107 - Admin Text Filter Field Componentization

## Contexte
Les champs texte des formulaires de filtres admin (`target`, `search`) etaient dupliques dans plusieurs vues.

## Scope
- Extraire un composant Twig partage pour les champs texte de filtres.
- Integrer ce composant dans les vues admin concernees.
- Conserver labels, placeholders et valeurs actuels.

## Criteres d acceptance
- Le markup des champs texte de filtres est centralise.
- Les vues admin utilisent le composant partage.
- Aucun changement fonctionnel des filtres.

## Avancement
- [x] Composant cree: `templates/admin/_text_filter_field.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/admin/item_translations.html.twig` (`target`, `q`)
  - [x] `templates/admin/contact_messages.html.twig` (`q`)
  - [x] `templates/admin/audit_logs.html.twig` (`q`)

## Statut
- Done - 2026-02-28
