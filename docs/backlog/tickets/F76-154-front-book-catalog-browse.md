# F76-154 - Front book catalog browse

## Status
`Done`

## Contexte
- Le pipeline multi-source et l inspection admin des items `BOOK` sont maintenant en place.
- Il manque encore une vraie page front de lecture des plans/recettes pour valider ce qui est effectivement visible cote produit, au-dela des rapports console et de l admin.

## Objectif
Ajouter une page front authentifiee qui liste les plans et recettes (`BOOK`) avec les informations fusionnees utiles, afin de verifier visuellement le rendu du merge et des signaux canoniques sur de vraies donnees.

## Scope
- Nouvelle route front read-only pour parcourir les items `BOOK`.
- Recherche textuelle sur le nom affiche et filtres simples (statut de merge, pagination).
- Affichage des infos front principales:
- nom affiche,
- prix / prix Minerva / listes,
- providers disponibles,
- statut de merge,
- signaux canoniques utiles.
- Tests unitaires et fonctionnels cibles.

### Progress
- Done (slice 1): read model front pour lister les `BOOK` fusionnes, deriver les statuts/signaux de merge utiles et appliquer recherche + pagination en memoire sur le rendu visible.
- Done (slice 2): route + template front `/plans-recipes` au format catalogue, avec cartes, pagination, recherche et filtre de statut de merge.
- Done (slice 3): tests unitaires/fonctionnels cibles + traductions/navigation/styling + doc projet.

## Hors scope
- Edition front des items.
- Filtres metier exhaustifs des signaux canoniques des le premier slice.
- Refonte du dashboard joueur existant.

## Criteres d acceptance
- Une page front authentifiee permet de parcourir les plans/recettes.
- Les informations affichees suffisent a voir si le merge produit les bons champs visibles.
- La page reste exploitable sans ouvrir l admin pour chaque item.

## Tests
- Unit:
- mapping/filtrage/pagination du read model front.
- Functional:
- acces protege + rendu de la page front catalogue `BOOK`.

## Risques / rollback
- Risque: lecture front un peu lourde si l on filtre en memoire sur tous les `BOOK`.
- Mitigation: premier slice volontairement simple pour valider le besoin visuel; optimisation SQL ensuite seulement si un vrai besoin apparait.
- Rollback: retirer la route front sans toucher au pipeline de merge ni a l admin.
