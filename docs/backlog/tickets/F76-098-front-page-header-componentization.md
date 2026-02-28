# F76-098 - Front Page Header Componentization

## Contexte
Les pages front principales (`Mods legendaires`, `Minerva`, `Progression`) dupliquaient la structure complete du header (tools, kicker, titre, sous-titre, nav, etat optionnel).

## Scope
- Extraire un composant Twig partage pour le header des pages front.
- Integrer ce composant dans les 3 pages principales.
- Conserver le rendu et les textes existants.

## Criteres d acceptance
- Un composant unique porte la structure du header front.
- Dashboard, Minerva et Progression utilisent ce composant.
- Le bloc timezone Minerva reste affiche.

## Avancement
- [x] Composant cree: `templates/_app_page_header.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/dashboard/index.html.twig`
  - [x] `templates/minerva/rotation.html.twig`
  - [x] `templates/progression/index.html.twig`

## Statut
- Done - 2026-02-28
