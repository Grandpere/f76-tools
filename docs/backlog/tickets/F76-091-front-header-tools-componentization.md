# F76-091 - Front Header Tools Componentization

## Contexte
Le bloc header front (`admin`, compte, deconnexion, selecteur de langue) etait duplique sur plusieurs pages (`mods legendaires`, `minerva`, `progression`), ce qui augmente le risque de divergence UI.

## Scope
- Extraire un composant Twig partage pour le bloc header tools.
- Remplacer les duplications dans les pages front principales.
- Conserver le meme rendu et les memes actions (logout CSRF, locale, lien admin conditionnel).

## Criteres d acceptance
- Un seul composant centralise le bloc header tools.
- Les pages front reutilisent ce composant avec un `id` de selecteur de locale distinct.
- Aucun changement fonctionnel sur logout/langue/lien admin.

## Avancement
- [x] Composant partage cree: `templates/_app_header_tools.html.twig`.
- [x] Integrations effectuees:
  - [x] `templates/dashboard/index.html.twig`
  - [x] `templates/minerva/rotation.html.twig`
  - [x] `templates/progression/index.html.twig`

## Statut
- Done - 2026-02-28
