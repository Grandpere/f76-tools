# Current Focus

## Priorite active
- Stabilisation front post-refacto (headers/nav/blocs front) + corrections UX mineures.
- F76-002: Evolutions produit Minerva (localisation/date/listes) selon la roadmap metier.
- Stabilisation post-migration DDD (monitoring regressions + hygiene continue).

## Termine recemment
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
- Stabilisation UX backoffice/front apres F76-138 (retours fonctionnels + micro-polish).
