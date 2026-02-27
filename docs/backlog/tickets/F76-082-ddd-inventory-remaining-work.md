# F76-082 - DDD Inventory Remaining Work (Useful + Optional)

## Contexte
Le projet a fortement progresse sur les slices DDD. Avant de continuer, un inventaire explicite des changements restants est necessaire pour prioriser proprement.

## Inventaire - Reste utile (a faire en priorite)
- [ ] Reorganiser les commandes Symfony hors `src/Command` vers des namespaces de contexte:
  - `Catalog/UI/Console` (`ImportItemsCommand`, `GenerateMinervaRotationCommand`)
  - `Identity/UI/Console` (`CreateUserCommand`, `PromoteUserAdminCommand`)
  - `Support/UI/Console` (`PurgeAdminAuditLogsCommand`)
- [ ] Harmoniser les petits helpers `optionalString` / `optionalIntOrString` dupliques dans les controllers admin (`AuditLogController`, `ContactMessageController`, `ItemTranslationController`) via un composant partage.
- [ ] Finaliser le durcissement d'entrees admin pour pagination/query dans `ItemTranslationController` (alignement avec le pattern query object deja applique a Audit/Contact).
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
