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
- Done (slice 6): branchement des icones gameplay/devises deja presentes dans le repo (`Daily Ops`, `Raid`, `Unused content`, `caps`, `gold bullion`) sur les filtres et cartes `/plans-recipes`, avec fallback texte pour les signaux sans asset dedie.
- Done (slice 7): alignement du rendu d icones des cartes sur le pattern Minerva (footer inline) et extension prudente de la couverture a `enemies`; `expeditions` reste en texte en attendant un vrai pictogramme `stamp`.
- Done (slice 8): recuperation depuis Fallout Wiki d un premier lot d icones `Obtained/Legend` manquantes (`stamp`, `ticket`, `quest`, `event`, `containers`, `treasure map`, `spawned`, etc.) avec renommage coherent, puis branchement des cas les plus fiables sur `/plans-recipes` (`expeditions`, `quests`, `events`, `containers`, `treasure_maps`, `world_spawns`, `tickets`).
- Done (slice 9): affinage des choix d icones front selon le retour visuel reel: `expeditions` et `stamps` passent sur la vignette wiki `stamp` plus lisible, `world_spawns` adopte l icone `exploration team`, `unused_content` adopte la version wiki, et `seasonal_content` est maintenant illustre explicitement.
- Done (slice 10): le bloc `Marchands` de `/plans-recipes` couvre maintenant aussi les vendeurs exacts detectes dans les metadata wiki (`Minerva`, `Giuseppe`, `Regs/Reginald Stone`, `Samuel`, `Mortimer`), fait matcher les cas mixtes (`X or Minerva`) dans les deux filtres concernes, renomme l icone Giuseppe en `WhitespringResortMarker.svg` et expose `Bullion vendors` comme indice non filtrant dans un sous-bloc dedie.

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
