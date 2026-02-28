# F76-110 - Front Catalog Filters Componentization

## Contexte
Les blocs de filtres front (player/search + sources) etaient dupliques entre:
- page Mods legendaires,
- page Minerva (bloc plans).

## Scope
- Extraire un composant Twig partage pour le bloc player/search.
- Extraire un composant Twig partage pour le groupe de filtres sources.
- Integrer ces composants dans les deux pages.

## Criteres d acceptance
- Les deux pages reutilisent les composants de filtres communs.
- Les targets Stimulus restent inchanges (`item-catalog`, `minerva-knowledge`).
- Aucun changement fonctionnel des filtres.

## Avancement
- [x] Composant cree: `templates/_catalog_player_search_filters.html.twig`.
- [x] Composant cree: `templates/_catalog_source_filters.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/dashboard/index.html.twig`
  - [x] `templates/minerva/rotation.html.twig`

## Statut
- Done - 2026-02-28
