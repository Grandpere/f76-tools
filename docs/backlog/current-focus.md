# Current Focus

## Priorite active
- F76-152: Catalog items multi-source (core item + source metadata table) - `Todo`.
- F76-150: OCR microservice Python optionnel - `Deferred` (remplace par flux JSON manuel pour roadmap).

## Termine recemment
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
