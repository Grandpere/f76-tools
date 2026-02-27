# F76-082 - DDD Inventory Remaining Work (Useful + Optional)

## Contexte
Le projet a fortement progresse sur les slices DDD. Avant de continuer, un inventaire explicite des changements restants est necessaire pour prioriser proprement.

## Inventaire - Reste utile (a faire en priorite)
- [ ] Reorganiser la racine `src` vers les namespaces de contexte et supprimer les dossiers legacy globaux encore presents:
  - `Controller`, `Domain`, `Entity`, `EventSubscriber`, `Repository`, `Security`, `Service`
  - deplacer leur contenu dans les contexts associes (`Catalog`, `Identity`, `Progression`, `Support`, etc.)
  - supprimer `src/Translation` (vide). ✅ fait
- [x] Reorganiser les commandes Symfony hors `src/Command` vers des namespaces de contexte:
  - `Catalog/UI/Console` (`ImportItemsCommand`, `GenerateMinervaRotationCommand`)
  - `Identity/UI/Console` (`CreateUserCommand`, `PromoteUserAdminCommand`)
  - `Support/UI/Console` (`PurgeAdminAuditLogsCommand`)
- [x] Supprimer progressivement `src/Contract` en deplacant les interfaces vers les namespaces de contexte (`<Context>/Application` ou `<Context>/Domain` selon le role du port).
- [x] Standardiser le nommage des interfaces sans suffixe `Interface` (alignement avec l'autre projet).
- [x] Harmoniser les petits helpers `optionalString` / `optionalIntOrString` dupliques dans les controllers admin (`AuditLogController`, `ContactMessageController`, `ItemTranslationController`) via un composant partage.
- [x] Finaliser le durcissement d'entrees admin pour pagination/query dans `ItemTranslationController` (alignement avec le pattern query object deja applique a Audit/Contact).
- [ ] Verifier que tous les controllers admin exposant des actions sensibles utilisent:
  - garde admin partagee,
  - validation CSRF partagee,
  - contexte user explicite.

## Inventaire - Slices optionnels (a faire ensuite)
- [ ] Pousser le nettoyage `mixed` residuel dans les query objects (`fromRaw`) vers des DTO HTTP explicites en amont.
- [ ] Introduire des request objects equivalents pour les flows Identity (`register/forgot/resend`) pour uniformiser les contrats.
- [ ] Revoir les docblocks `array<string, mixed>` quand un type structure plus strict est possible (payloads admin/audit).
- [ ] Etendre la meme logique DDD de typage et d'isolation aux zones import Catalog (`ItemImport*`) qui restent tres permissives.
- [ ] Ajouter/renforcer des tests d'architecture (PHPat/PHPStan rules) pour figer les frontieres `UI -> Application -> Domain`.

## Avancement
- [x] Inventaire initial redige et versionne.
- [x] Inventaire valide avec l'utilisateur: dossiers legacy `src` confirms et suppression de `src/Translation` vide gardee dans le scope utile.
- [x] Migration effectuee: commandes `src/Command` -> contexts `*/UI/Console`.
- [x] Migration effectuee: interfaces `src/Contract` -> contexts applicatifs + renommage sans suffixe `Interface`.
- [x] Slice effectuee: trait admin partage pour sanitization des inputs (`optionalString`, `optionalIntOrString`, `sanitizePositiveInt`).
- [x] Slice effectuee: `ItemTranslationListQuery::fromRaw(...)` pour unifier et durcir les entrees `target/q/page/perPage`.
- [x] Slice effectuee: validation CSRF ajoutee au POST `ItemTranslationController` + champ `_token` template + couverture fonctionnelle.
- [x] Slice effectuee: dossiers legacy `src/Security` et `src/Service` migres vers contexts (`Identity/*`, `Progression/*`) avec mise a jour des usages/tests.
