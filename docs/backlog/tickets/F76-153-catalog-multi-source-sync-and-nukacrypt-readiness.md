# F76-153 - Catalog multi-source sync pipeline + Nukacrypt readiness

## Status
`In progress`

## Contexte
- Les imports Nukaknights/Fandom existent, mais la consolidation multi-sources doit etre stabilisee apres la refonte de modele F76-152.
- Nukacrypt doit etre ajoute avec un format d URL/reference specifique.

## Objectif
Ajouter un pipeline de sync multi-sources coherent (sans duplication instable) et preparer l integration Nukacrypt sur le nouveau modele metadata source.

## Scope
- Etendre `app:data:sync` pour orchestrer les sources supportees (Nukaknights + Fandom, puis Nukacrypt).
- Ajouter un import Nukacrypt initial (read-only):
- generation `external_ref` (form_id),
- construction `external_url` standard (`https://nukacrypt.com/FO76/w/latest/SeventySix.esm/{FORM_ID}`),
- metadata minimale utile.
- Mettre en place des rapports de sync:
- volumes par source,
- lignes ignorees/rejetees,
- collisions detectees.
- Documenter le runbook operateur pour lancer/valider les syncs.

### Progress
- Done (slice 1): `app:data:sync` orchestre maintenant `all|nukaknights|fandom`, avec delegation vers `app:data:sync:fandom`.
- Done (slice 1): tests unitaires ajoutes pour la delegation Fandom (`--only=fandom` success/failure).
- Done (slice 2): preparation Nukacrypt cote import via resolver URL externe (`form_id` -> URL Nukacrypt standard) quand provider=`nukacrypt`.
- Done (slice 3): reporting sync multi-sources (`--format=json`, compteurs par source, validation d options) + couverture unitaire.
- Done (slice 4): enrichissement metadata Nukacrypt depuis `keywords` (noms de keywords + derive `tradeable=false` si `UnsellableObject`).
- Done (slice 5): commande `app:data:sync:fallout-wiki` versionnee et raccordee a l orchestrateur `app:data:sync` avec reporting dedie.
- Done (slice 6): l import lit maintenant les payloads `resources` Fandom/Fallout Wiki, ignore `index.json`, mappe `form_id` -> `source_id` et consolide plusieurs providers sur un meme item.
- Done (slice 7): rapport console `app:data:report:source-diff` pour visualiser les champs divergents Fandom/Fallout Wiki avant arbitrage de merge.
- Done (slice 8): rapport console `app:data:report:source-collisions` pour identifier les `external_ref` rattaches a plusieurs items.
- Done (slice 9): `app:data:sync` ecrit aussi un `index.json` pour Nukaknights et affiche une progression plus lisible par dataset afin de mieux suivre les syncs longs.
- Done (slice 10): le sync Fandom n abandonne plus tout le lot sur une seule page en erreur; il conserve les pages reussies, ajoute `page_errors` dans l index et permet une relance ciblee via `--fandom-page`.
- Done (slice 11): le sync `fallout.wiki` applique la meme strategie de resilience partielle que Fandom, avec index partiel et relance ciblee via `--fallout-wiki-page`.
- Done (slice 12): premiere politique de merge cross-source en lecture (`fandom` prioritaire pour disponibilite/availability, `fallout.wiki` prioritaire pour `unlocks`/`obtained`/`type`, noms consolides seulement s ils sont equivalentes) + rapport `app:data:report:source-merge`.
- Done (slice 13): le payload API `PlayerItemKnowledge` expose maintenant un bloc additif `sourceMerge` (retained/conflicts) afin de rendre la consolidation cross-source disponible aux futurs usages UI sans modifier le comportement front courant.
- Done (slice 14): ajout d une synthese `app:data:report:source-merge-summary` pour suivre la politique de merge au niveau catalogue (par champ, provider retenu, conflits restants).
- Done (slice 15): correction de la qualite source cote import pour `fandom`/`fallout_wiki` en ignorant les doublons `form_id` intra-provider (premiere occurrence conservee) + regle de merge nom plus specifique pour les variantes parenthetiques comme `Healing Salve (Toxic Valley)`.
- Done (slice 16): ajout d un probe console Nukacrypt `app:data:probe:nukacrypt-record` appuye sur `esmRecords(searchTerm + signatures)` pour verifier ponctuellement un nom/source sans sync exhaustif.
- Done (slice 17): ajout d un probe d arbitrage `app:data:probe:nukacrypt-conflict` qui confronte plusieurs noms candidats et/ou un `editorId` a un `form_id` attendu pour aider au tri des conflits source.
- Done (slice 18): ajout d un rapport `app:data:report:source-arbitration` qui cible les conflits de noms entre deux providers et s appuie sur Nukacrypt pour produire un verdict (`confirmed_provider_a`, `confirmed_provider_b`, `no_result`, etc.).
- Done (slice 19): le sync `fallout.wiki` conserve maintenant les URLs/specifiques issues des liens de tableau (`href`), derive `source_slug` depuis ces liens et deduplique d abord par `form_id`, ce qui preserve les variantes partageant un libelle generique.
- Done (slice 20): le rapport d arbitrage distingue les labels generiques confirmes par URL cible/form_id (`provider_a_generic_label_confirmed`, `provider_b_generic_label_confirmed`) et expose des compteurs separes pour le bruit de labeling vs les conflits materiels.
- Done (slice 21): la politique de merge et ses rapports de synthese exposent maintenant la raison `generic_label_confirmed_by_specific_target` afin de separer les resolutions de libelle generique des vrais conflits restants.
- Done (slice 22): ajout d une page admin read-only `/admin/catalog/items` pour filtrer les items catalogue, inspecter les sources externes persistées et rendre le `sourceMerge` consultable en environnement deploye sans passer par la console.
- Done (slice 23): ajout d un champ de merge canonique `purchase_currency` pour normaliser les devises d achat inter-sources (`Bottle cap`/`caps`, `gold bullion`/`gold`, etc.) sans presenter `value_currency` et `type` comme deux notions distinctes.
- Done (slice 24): enrichissement `fallout_wiki` a l import avec des flags d acquisition derives (containers, enemies, quests, vendors, world_spawns, seasonal_content, treasure_maps) et une `purchase_currency` derivee afin de rapprocher son modele des booléens Fandom tout en conservant les labels bruts.
- Done (slice 25): les rows `fallout_wiki` qui portent deux noms incompatibles (`resource.name` vs `columns.name`) conservent maintenant le nom divergent en `source_name_raw`, et la policy de merge cesse de faire confiance a cette source pour les champs de nom lorsqu un autre provider propre est disponible.
- Done (slice 26): l interface admin catalogue affiche maintenant un statut de merge synthétique par item et dans le panneau detail (`aligned`, `generic label`, `source issue`, `material conflict`) avec compteurs pour rendre les cas utiles visibles en environnement deploye.
- Done (slice 27): la liste admin catalogue peut maintenant etre filtree par statut de merge ; le filtre repose sur une lecture complete admin lorsque necessaire, pour garder la pagination SQL simple sur le chemin par defaut et ne pas dupliquer la logique `ItemSourceMergePolicy` dans Doctrine.
- Done (slice 28): l enrichissement `fallout_wiki` mappe maintenant les alias d acquisition reellement presents dans les snapshots (`Enemy Drop`, `Spawned`, `Merchants`, `Quests`, `Containers`, `Fallout 76 Limited Time Content`), afin que les booléens derives racontent la meme chose que `obtained`.
- Done (slice 29): ajout d un rapport `app:data:report:source-vocabulary` qui inspecte directement les snapshots JSON bruts pour inventorier les valeurs/flags reellement observes dans `fandom.availability`, `fallout_wiki.obtained` et `fallout_wiki.type`, avec compteurs et sortie `text|json`.
- Done (slice 30): le mapping `fallout_wiki` couvre maintenant aussi les alias supplementaires remontes par le rapport de vocabulaire (`Fallout 76 Quests`, `Caps`/`bullion`, `Scoreboard`) afin d ameliorer la derive des flags canoniques et de `purchase_currency`.
- Done (slice 31): le rapport de vocabulaire annote maintenant chaque label brut avec les champs canoniques qu il alimente deja (`mapped_fields`), ce qui permet d identifier plus rapidement les labels encore purement bruts avant d etendre la taxonomie.
- Done (slice 32): le rapport de vocabulaire peut maintenant etre filtre avec `--only-unmapped` pour n afficher que les labels encore sans couverture canonique, utile pour prioriser les prochains concepts metier/event taxonomy sans bruit.
- Done (slice 33): ajout des champs canoniques derives `events` et `daily_ops` pour `fallout_wiki`, avec propagation dans le merge cross-source afin de rendre ces signaux filtrables/exploitables au meme niveau que `quests`, `vendors` ou `seasonal_content`.
- Done (slice 34): le rapport de vocabulaire accepte maintenant aussi `--only-mapped`, mutuellement exclusif avec `--only-unmapped`, pour visualiser rapidement la couverture canonique deja en place sans le bruit du residuel.
- Done (slice 35): ajout du champ canonique derive `unused_content` pour `fallout_wiki` (`Overview:Unused Content`, `Unused Content`, `Fallout 76 Unused Content`) avec propagation dans le merge/reporting au meme titre que les autres flags d acquisition.
- Note: verification live du GraphQL Nukacrypt le 2026-03-17 : `nukeCodes` et l introspection repondent, `esmRecord(formId)` reste instable/HTTP 500 depuis l app. Un `curl` navigateur colle manuellement dans le shell du conteneur `app` peut repondre sur `esmRecords(searchTerm)`, mais ce succes n est pas encore reproductible via le runtime PHP de l application.
- Remaining: utiliser l UI admin pour les prochains arbitrages en deploye puis, seulement si besoin, enrichir cette lecture avec plus de guidage Nukacrypt; pas de sync global `BOOK` tant que le contrat public par `formId` n est pas fiable.

## Hors scope
- Matching semantique avance cross-source par similarite de nom.
- UI admin d edition/moderation source par source.

## Criteres d acceptance
- `app:data:sync` produit des sorties explicites par source sans casser les imports existants.
- Les enregistrements Nukacrypt sont persistes via metadata source avec URL/reference coherentes.
- Les erreurs de sync sont exploitables sans aller lire du code.

## Tests
- Unit:
- mapping Nukacrypt vers source metadata.
- Integration:
- sync multi-sources et rapport de sortie.
- Functional:
- non requis sur ce slice (commande + persistence).

## Risques / rollback
- Risque: duplication d items si matching encore naif.
- Mitigation: phase read-only + rapports de collision avant toute logique de merge metier.
- Rollback: desactiver le provider Nukacrypt dans la commande tout en conservant Nukaknights/Fandom.
