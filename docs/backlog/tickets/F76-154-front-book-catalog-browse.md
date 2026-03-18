# F76-154 - Front book catalog browse

## Status
`Done`

## Contexte
- Le pipeline multi-source et l inspection admin des items `BOOK` sont maintenant en place.
- Il manque encore une vraie page front de lecture des plans/recettes pour valider ce qui est effectivement visible cote produit, au-dela des rapports console et de l admin.

## Objectif
Ajouter une page front authentifiee qui liste les plans et recettes (`BOOK`) avec une presentation orientee joueur, afin de verifier ce qui est reellement affiche dans le produit sans exposer les details internes du merge.

## Scope
- Nouvelle route front read-only pour parcourir les items `BOOK`.
- Recherche textuelle sur le nom affiche et filtres simples (liste de plans, pagination).
- Affichage des infos front principales:
- nom affiche,
- prix / prix Minerva / listes,
- signaux canoniques utiles.
- Tests unitaires et fonctionnels cibles.

### Progress
- Done (slice 1): read model front pour lister les `BOOK` fusionnes, deriver les statuts/signaux de merge utiles et appliquer recherche + pagination en memoire sur le rendu visible.
- Done (slice 2): route + template front `/plans-recipes` au format catalogue, avec cartes, pagination, recherche et premier filtre de statut interne.
- Done (slice 3): tests unitaires/fonctionnels cibles + traductions/navigation/styling + doc projet.
- Done (slice 4): recentrage de la page front sur l experience joueur (suppression des infos de source/merge visibles, filtre par liste de plans, stats metier utiles) et correction du rendu Minerva qui injectait a tort les livres sans liste dans la liste 1.
- Done (slice 5): retrait de la notion trompeuse de `liste speciale` du front joueur, remplacement par un indicateur Minerva plus parlant, deplacement du menu `Plans et recettes` en 3e position et adoption d un filtre de listes au style des autres pages joueur.

## Hors scope
- Edition front des items.
- Filtres metier exhaustifs des signaux canoniques des le premier slice.
- Refonte du dashboard joueur existant.

## Criteres d acceptance
- Une page front authentifiee permet de parcourir les plans/recettes.
- Les informations affichees sont comprehensibles cote joueur et ne montrent pas la mecanique interne du merge.
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
