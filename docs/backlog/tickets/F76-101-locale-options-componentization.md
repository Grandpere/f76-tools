# F76-101 - Locale Options Componentization

## Contexte
Les options du selecteur de langue (FR/EN/DE + drapeaux) etaient dupliquees dans les composants header front et admin.

## Scope
- Extraire un composant Twig partage pour les options de langue.
- Integrer ce composant dans les deux headers (`front` et `admin`).
- Conserver le comportement de selection de locale courant.

## Criteres d acceptance
- Un seul template centralise les options FR/EN/DE.
- Les headers front/admin reutilisent ce template.
- Aucun changement fonctionnel du selecteur de langue.

## Avancement
- [x] Composant cree: `templates/_locale_options.html.twig`.
- [x] Integration:
  - [x] `templates/_app_header_tools.html.twig`
  - [x] `templates/admin/_header_tools.html.twig`

## Statut
- Done - 2026-02-28
