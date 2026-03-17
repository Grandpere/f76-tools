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
- Note: les requetes item GraphQL Nukacrypt (`esmRecord` / `esmRecords`) repondent actuellement en HTTP 500 cote serveur, alors que l introspection et `nukeCodes` fonctionnent.
- Remaining: source de sync Nukacrypt read-only (bloquee tant que l endpoint item renvoie 500) + branchement progressif de la consolidation cross-source dans les lectures metier si la politique actuelle se confirme.

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
