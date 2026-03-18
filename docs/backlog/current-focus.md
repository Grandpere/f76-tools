# Current Focus

## Priorite active
- F76-153: pipeline de sync multi-sources + preparation Nukacrypt (apres F76-152) - `In progress`.
- F76-150: OCR microservice Python optionnel - `Deferred` (remplace par flux JSON manuel pour roadmap).

### Avancement F76-153
- Done (slice 1): `app:data:sync` devient orchestrateur (`all|nukaknights|fandom`) avec delegation a `app:data:sync:fandom`.
- Done (slice 1): tests unitaires ajoutés pour l orchestration Fandom (`only=fandom` success/failure).
- Done (slice 2): generation d URL externe Nukacrypt en import (`form_id` -> URL standard) quand provider=`nukacrypt`.
- Done (slice 3): reporting sync enrichi (`--format=json`, compteurs par source, validation options).
- Done (slice 4): enrichissement metadata Nukacrypt depuis `keywords` (extraction noms + `UnsellableObject` => `derived.tradeable=false`).
- Done (slice 5): `app:data:sync` orchestre aussi `fallout-wiki`, avec une commande dediee et des tests unitaires.
- Done (slice 6): l import sait maintenant lire les payloads `resources` Fandom/Fallout Wiki, ignorer `index.json` et consolider un meme item cross-source via `form_id`.
- Done (slice 7): ajout d un rapport console `app:data:report:source-diff` pour comparer Fandom/Fallout Wiki item par item avant de figer la politique de merge.
- Done (slice 8): ajout d un rapport console `app:data:report:source-collisions` pour detecter les `external_ref` rattaches a plusieurs items.
- Done (slice 9): `app:data:sync` produit maintenant un `index.json` cote Nukaknights et affiche une progression plus explicite par dataset (`Legendary mods`, `Minerva`).
- Done (slice 10): le sync Fandom conserve les pages reussies en cas d erreur distante, ecrit un index partiel avec `page_errors` et permet une relance ciblee via `--fandom-page`.
- Done (slice 11): le sync `fallout.wiki` applique le meme mode partiel que Fandom (`page_errors`, index partiel, relance ciblee via `--fallout-wiki-page`).
- Done (slice 12): premiere politique de merge cross-source en lecture + rapport console `app:data:report:source-merge` pour rendre visibles les champs retenus et les conflits restants entre `fandom` et `fallout.wiki`.
- Done (slice 13): le payload API des items expose maintenant `sourceMerge` de facon additive, pour rendre la consolidation cross-source consommable sans casser le front existant.
- Done (slice 14): ajout d un rapport de synthese `app:data:report:source-merge-summary` pour voir, par champ, combien de fois la politique retient chaque provider et combien de conflits subsistent.
- Done (slice 15): les doublons intra-provider `fandom`/`fallout_wiki` sur un meme `form_id` sont maintenant ignores a l import (premiere occurrence conservee), et le merge de nom prefere la variante la plus specifique quand une source ajoute un suffixe parenthetique.
- Done (slice 16): ajout d un probe console Nukacrypt read-only (`app:data:probe:nukacrypt-record`) base sur `esmRecords(searchTerm + signatures)` pour arbitrage cible des conflits source par nom.
- Done (slice 17): ajout d un probe d arbitrage `app:data:probe:nukacrypt-conflict` qui compare plusieurs noms candidats et/ou un `editorId` a un `form_id` attendu via la recherche publique Nukacrypt.
- Done (slice 18): ajout d un rapport `app:data:report:source-arbitration` qui isole les conflits de noms entre deux providers et demande a Nukacrypt de trancher item par item.
- Done (slice 19): correction du sync `fallout.wiki` pour conserver les vraies URLs issues des ancres `<a>` et ne plus ecraser les variantes a libelle generique (`Recipe: Healing Salve`) quand leurs `form_id`/liens sont distincts.
- Done (slice 20): le rapport d arbitrage distingue maintenant les libelles generiques confirmes par URL cible/form_id (`provider_*_generic_label_confirmed`) des vrais conflits materiels, avec compteurs `generic_label_items` et `material_conflict_items`.
- Done (slice 21): la politique de merge et ses rapports (`source-merge`, `source-merge-summary`) propagent aussi cette distinction via la raison `generic_label_confirmed_by_specific_target` et des compteurs dedies.
- Done (slice 22): ajout d une UI admin read-only `admin/catalog/items` pour consulter les items catalogue, leurs sources externes et le detail `sourceMerge` sans dependre des rapports console en environnement deploye.
- Done (slice 23): normalisation cross-source de la devise d achat via un champ merge canonique `purchase_currency`, pour aligner `fandom.value_currency` et `fallout_wiki.type` quand ils decrivent la meme monnaie du jeu.
- Done (slice 24): enrichissement des metadata `fallout_wiki` a l import avec des flags canoniques derives (`containers`, `enemies`, `quests`, `vendors`, `world_spawns`, `seasonal_content`, `treasure_maps`) et une `purchase_currency` derivee afin de rendre le filtrage et le merge plus coherents avec Fandom.
- Done (slice 25): les noms `fallout_wiki` contradictoires entre `resource.name` et `columns.name` sont maintenant traces a l import (`source_name_raw`) et n ecrasent plus la source saine au merge ; les rapports `source-merge` reviennent a `0` conflit materiel sur le lot controle.
- Done (slice 26): l UI admin `admin/catalog/items` expose maintenant un statut de merge lisible en liste et en detail (`aligned`, `generic label`, `source issue`, `material conflict`) avec compteurs, pour rendre l arbitrage exploitable sans console.
- Done (slice 27): l UI admin `admin/catalog/items` permet maintenant de filtrer la liste par statut de merge ; ce filtre passe par une lecture admin complete en memoire quand il est actif, afin d eviter de reencoder la policy de merge en SQL.
- Done (slice 28): la normalisation `fallout_wiki` reconnait maintenant les alias reels observes dans les JSON (`Enemy Drop`, `Spawned`, `Merchants`, `Quests`, `Containers`, `Fallout 76 Limited Time Content`) afin d aligner les booléens derives avec `obtained`.
- Done (slice 29): ajout d un rapport console `app:data:report:source-vocabulary` qui lit les snapshots bruts et inventorie les vocabulaires reels observes dans `fandom.availability`, `fallout_wiki.obtained` et `fallout_wiki.type`, avec compteurs et sortie `text|json`.
- Done (slice 30): la normalisation `fallout_wiki` reconnait aussi les alias reels supplementaires exposes par le rapport de vocabulaire (`Fallout 76 Quests`, `Caps`/`bullion`, `Scoreboard`) afin de mieux aligner `quests`, `vendors`, `purchase_currency` et `seasonal_content`.
- Done (slice 31): le rapport `app:data:report:source-vocabulary` indique maintenant aussi quels labels bruts alimentent deja un champ canonique (`mapped_fields`), afin d isoler plus vite le vrai residuel non mappe.
- Done (slice 32): le rapport `app:data:report:source-vocabulary` accepte maintenant `--only-unmapped` pour afficher uniquement le residuel brut qui n alimente encore aucun champ canonique, ce qui transforme l audit en backlog de taxonomie exploitable.
- Done (slice 33): la taxonomie canonique derivee couvre maintenant aussi `events` et `daily_ops` pour `fallout_wiki`, et ces champs remontent dans la policy de merge/reporting comme les autres flags d acquisition.
- Note: le lookup public Nukacrypt direct par `form_id` via `esmRecord` reste non fiable depuis l app (HTTP 500 / corps vide). Un `curl` navigateur colle manuellement dans le shell du conteneur `app` peut repondre, mais le meme cas n est pas encore reproducible via le runtime PHP (`HttpClient`/probe). On garde donc Nukacrypt en outil opportuniste d arbitrage, pas en source serveur robuste.
- Remaining: capitaliser sur l UI admin pour confirmer les prochains conflits reels en environnement deploye, puis seulement si besoin enrichir les vues de merge avec davantage d aide a l arbitrage Nukacrypt.

## Termine recemment
- F76-152: Catalog items multi-source (core item + source metadata table) (done).
- F76-147: upload roadmap OCR via backoffice (done).
- F76-151: OCR roadmap upload en asynchrone (messenger + statut admin snapshot) (done).
- F76-148: fallback import manuel JSON roadmap (done).
- F76-149: Roadmap OCR image preprocessing pipeline (layout-first, no new dependency) (done).
- F76-146: roadmap saison (modele season + merge scoped + saison active front/admin) (done).
- F76-087: BOOK lists en modele absolu 1..24 (done).
- F76-002: Rotation Minerva (socle localisation/date/listes) (done).
- Stabilisation UX backoffice/front apres F76-138 (retours fonctionnels + micro-polish) (done).
- Stabilisation front post-refacto (perf queries + ajustements UX mineurs) (done, commits `d6af2a0`, `58491be`, `c53463c`).
- F76-142: Minerva incident playbook and override lifecycle (done).
- F76-145: Fondations roadmap OCR (provider chain + fallback qualite) (done).
- F76-144: UX final polish front/admin consistency (done).
- F76-143: monitoring thresholds + smoke suite ops/app + triage runbook (done).
- F76-141: check fiabilite Minerva en JSON + statut coherence visible en admin (done).
- F76-140: clarification gouvernance source Minerva (admin-only) + docs ops alignees (done).
- F76-139: Minerva polish UI + extraction lecture resume refresh vers service applicatif audit (done).
- F76-138: auto-refresh Minerva (service + commande + action backoffice + bloc fraicheur) (done).
- F76-137: PHPat guardrails sur le naming des ports (no *Interface en Application/Domain/UI + *Port interface en UI) (done).
- F76-136: Progression ports sans suffixe `Interface` (ItemReadRepository, PlayerReadRepository, owned-player read port) (done).
- F76-135: Catalog import ports sans suffixe `Interface` (ItemImport*) + propagation complete (done).
- F76-134: Identity ports sans suffixe `Interface` + corrections collisions de noms implementation/port (done).
- F76-133: Support ports sans suffixe `Interface` (admin user/audit/contact) + propagation complete (done).
- F76-132: Audit retention automation (commande unifiee + targets Make + runbook cron) (done).
- F76-131: Account security UX polish (compteurs sections + badges level + lien admin timeline) (done).
- F76-130: Auth audit log retention command (`app:auth:audit:purge` + port purge + tests unitaires) (done).
- F76-129: Admin user auth events export CSV (route dediee + filtres conserves + tests cibles) (done).
- F76-128: Admin auth events filters (level + query) + functional cible (done).
- F76-127: Account security recent auth events (front user view) + functional cible (done).
- F76-126: Auth rate-limit hardening (`register`/`forgot`/`resend` quotas dedies + tests cibles) (done).
- F76-125: Admin user auth events page + DB persistence auth events (`auth_audit_log`) + lien depuis users admin (done).
- F76-124: Account active sessions (liste sessions + revoke other sessions + invalidation enforcement) (done).
- F76-123: Account security unlink Google (self-service avec CSRF + garde-fou mot de passe local + tests unit/functional cibles) (done).
- F76-122: Front account security page (`/account-security`) + header account dropdown link + coverage fonctionnelle ciblee (done).
- Historique plus ancien: voir `docs/backlog/tickets/` (slices DDD et tickets precedents archives par fichier).

## Ensuite
- F76-153: pipeline de sync multi-sources + preparation Nukacrypt (apres F76-152).
