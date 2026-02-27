# F76-082 - DDD Inventory Remaining Work (Useful + Optional)

## Contexte
Le projet a fortement progresse sur les slices DDD. Avant de continuer, un inventaire explicite des changements restants est necessaire pour prioriser proprement.

## Inventaire - Reste utile (a faire en priorite)
- [x] Reorganiser la racine `src` vers les namespaces de contexte et supprimer les dossiers legacy globaux encore presents:
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
- [x] Verifier que tous les controllers admin exposant des actions sensibles utilisent:
  - garde admin partagee,
  - validation CSRF partagee,
  - contexte user explicite.

## Inventaire - Slices optionnels (a faire ensuite)
- [x] Pousser le nettoyage `mixed` residuel dans les query objects (`fromRaw`) vers des DTO HTTP explicites en amont.
- [x] Introduire des request objects equivalents pour les flows Identity (`register/forgot/resend`) pour uniformiser les contrats.
- [x] Revoir les docblocks `array<string, mixed>` quand un type structure plus strict est possible (payloads admin/audit).
- [x] Etendre la meme logique DDD de typage et d'isolation aux zones import Catalog (`ItemImport*`) qui restent tres permissives.
- [x] Ajouter/renforcer des tests d'architecture (PHPat/PHPStan rules) pour figer les frontieres `UI -> Application -> Domain`.

## Avancement
- [x] Inventaire initial redige et versionne.
- [x] Inventaire valide avec l'utilisateur: dossiers legacy `src` confirms et suppression de `src/Translation` vide gardee dans le scope utile.
- [x] Migration effectuee: commandes `src/Command` -> contexts `*/UI/Console`.
- [x] Migration effectuee: interfaces `src/Contract` -> contexts applicatifs + renommage sans suffixe `Interface`.
- [x] Slice effectuee: trait admin partage pour sanitization des inputs (`optionalString`, `optionalIntOrString`, `sanitizePositiveInt`).
- [x] Slice effectuee: `ItemTranslationListQuery::fromRaw(...)` pour unifier et durcir les entrees `target/q/page/perPage`.
- [x] Slice effectuee: validation CSRF ajoutee au POST `ItemTranslationController` + champ `_token` template + couverture fonctionnelle.
- [x] Slice effectuee: dossiers legacy `src/Security` et `src/Service` migres vers contexts (`Identity/*`, `Progression/*`) avec mise a jour des usages/tests.
- [x] Slice effectuee: migration des elements `src/Domain` et `src/EventSubscriber` vers contexts (`Catalog/Domain`, `Support/Domain`, `Support/Infrastructure/Http`) + nettoyage des aliases `App\\Contract\\*` restants dans `services.yaml`.
- [x] Slice effectuee: migration des repositories Doctrine hors `src/Repository` vers les contexts d'infrastructure (`Catalog/Identity/Progression/Support`) + mise a jour des `repositoryClass` d'entites et aliases DI.
- [x] Slice effectuee: controllers admin migres de `src/Controller/Admin` vers `src/Support/UI/Admin/Controller`.
- [x] Slice effectuee: ports additionnels pour respecter PHPat (`MinervaRotationRegenerationRepository`, `AdminUserManagementReadRepositoryInterface`) et eliminer les dependances `Application/UI -> Infrastructure`.
- [x] Slice effectuee: controllers web migres hors `src/Controller` vers contexts (`Identity/UI/Security/Controller`, `Progression/UI/Web`, `Catalog/UI/Web`) avec remplacement des dependances infra directes via ports applicatifs (`IdentityCaptchaSiteKeyProviderInterface`, `PlayerReadApplicationService`).
- [x] Slice effectuee: controllers API progression migres hors `src/Controller/Api` vers `src/Progression/UI/Api` (incluant `ProgressionAuthenticatedUserControllerTrait`) et suppression du dossier legacy `src/Controller`.
- [x] Slice effectuee: dossier legacy `src/Entity` supprime; entites migrees vers `*/Domain/Entity` par contexte et mapping Doctrine aligne (`prefix: App`, `dir: src`).
- [x] Verification effectuee: controllers admin sensibles alignes sur garde admin partagee + CSRF partage + contexte user explicite (`ContactMessageController`, `ItemTranslationController`, `MinervaRotationController`, `UserManagementController`).
- [x] Slice effectuee: request objects applicatifs Identity ajoutes (`RegisterUserRequest`, `ForgotPasswordRequest`, `ResendVerificationRequest`) et usages controllers/services/tests alignes.
- [x] Slice effectuee: durcissement des query inputs admin (pagination `?int` cote `fromRaw`) avec sanitization explicite en UI (`optionalPositiveInt`) et suppression du flux `int|string|null` pour les pages/perPage.
- [x] Slice effectuee: renforcement PHPat avec une regle explicite interdisant les dependances vers les namespaces legacy racine (`App\\Controller`, `App\\Entity`, `App\\Service`, etc.).
- [x] Slice effectuee: import Catalog durci avec value objects dedies (`ItemImportFileContext`, `ItemImportContextApplyResult`, `ItemImportTranslationCatalog`) pour remplacer les tableaux de shape implicite.
- [x] Slice effectuee: docblocks payloads resserres sur admin/audit et import (`list<mixed>` pour les lignes JSON de source reader, context audit explicite `bool|int|string|null`).
