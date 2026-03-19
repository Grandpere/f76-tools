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
- Done (slice 11): les cards `/plans-recipes` exposent maintenant aussi `unlocks` comme information joueur de premier niveau, derivee du merge quand disponible puis des metadata source en fallback, afin d identifier plus vite ce que debloque un plan/une recette sans ouvrir l admin.
- Done (slice 12): `/plans-recipes` partage maintenant la meme progression `BOOK` que Minerva via l API `player_item_knowledge` existante (select personnage + checkbox appris/non appris sur les cards), avec reapplication automatique de l etat appris apres chaque refresh AJAX des filtres.
- Done (slice 13): la ligne de prix des cards `/plans-recipes` est maintenant plus compacte pour les items Minerva: prix de base en lingots = logo + valeur, prix remisé = marqueur Minerva + valeur, sans redondance textuelle `Lingots/Minerva`.
- Done (slice 14): les cards `/plans-recipes` utilisent maintenant un footer unique de signaux en bas (icones vendeurs + signaux iconifies + pills texte fallback) au lieu de multiplier des lignes distinctes selon l item.
- Done (slice 15): ajout d un vrai filtre `Progression` (`Tout afficher`, `À apprendre`, `Appris`) sur `/plans-recipes`, branche serveur sur le personnage actif pour filtrer tout le catalogue pagine selon `player_item_knowledge`, tout en gardant la synchronisation front partagee avec Minerva.
- Done (slice 16): ajout d un resume de progression `appris / total` sur `/plans-recipes`, calcule cote serveur a partir du personnage actif et des filtres en cours, pour rendre la page plus utile comme outil de suivi sans dupliquer la logique Minerva.
- Done (slice 17): ajout d un tri simple sur `/plans-recipes` (`nom`, `prix`, `prix Minerva`), applique cote service apres filtres et avant pagination, avec select front dedie et persistance dans la pagination.
- Done (slice 18): ajout d un bloc `Plans et recettes` sur la page `/progression`, branche sur les stats du personnage courant et reliant directement au catalogue joueur `/plans-recipes`, afin d exposer la progression `BOOK` sans dupliquer un second catalogue dans la page.
- Done (slice 19): les stats progression distinguent maintenant aussi `plan` vs `recipe` pour les `BOOK`, ce qui permet au bloc `/progression` d afficher trois cartes de progression (`total`, `plans`, `recettes`) et renomme les sections de detail en vocabulaire joueur (`Mods légendaires`, `Listes Minerva`).
- Done (slice 20): ajout d une premiere taxonomie canonique `BOOK` basee sur les familles de pages source (`weapon_plan`, `weapon_mod_plan`, `armor_plan`, `armor_mod_plan`, `power_armor_plan`, `power_armor_mod_plan`, `workshop_plan`, `recipe`), exposee comme filtre de categorie sur `/plans-recipes` et comme nouveau detail de progression par categorie sur `/progression`.
- Done (slice 21): branchement d icones de categorie `BOOK` dans `/plans-recipes` avec trois assets legers (`plan`, `workshop`, `recipe`) visibles a la fois dans le filtre `Categorie` et dans une ligne de categorie discrete sur les cards, sans surcharger le footer de signaux deja utilise pour les activites/vendeurs.

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
