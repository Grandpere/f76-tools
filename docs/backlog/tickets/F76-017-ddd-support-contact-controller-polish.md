# F76-017 - DDD Slice 7: Support Contact Controller Polish

## Contexte
Le flux contact applique deja les protections (CSRF, honeypot, captcha, rate-limit), mais le controller conserve encore beaucoup de logique repetitive.
Le but est d aligner le niveau de "thin controller" avec les slices Identity.

## Scope
- Reutiliser les composants UI/guard deja extraits (`IdentityEmailFlowGuard`, `IdentityFlashResponder`) pour le flux contact.
- Centraliser la policy contact (scope/flash/route/rate-limit) via `IdentityEmailFlow`.
- Conserver le comportement metier existant (validation payload, persistance message, tentative d envoi email, audit logs).

## Avancement
- [x] Ajouter le flow `CONTACT` dans `IdentityEmailFlow`.
- [x] Brancher `ContactController` sur `IdentityEmailFlowGuard` + `IdentityFlashResponder`.
- [x] Retirer la logique guard inline (csrf/honeypot/captcha/rate-limit) du controller.
- [x] Conserver les logs et messages flash existants.
- [x] Extraire la sanitation/validation du payload dans `ContactSubmissionInput`.
- [x] Extraire l envoi email contact via le port `ContactMessageEmailSenderInterface`.
- [x] Extraire l orchestration soumission contact (`persist + delivery`) dans `ContactSubmissionApplicationService`.
- [x] Extraire le mapping status->logs/flash/redirect dans `ContactSubmissionResponder`.
- [x] Router l echec guard via `ContactSubmissionResponder` pour retirer la derniere branche UI du controller.

## Criteres d acceptance
- Aucun changement de routes ni de messages utilisateur.
- Controller plus court et plus lisible.
- Couverture existante conservee sans regression.

## Tests
- Unit: adaptions ciblant le flow contact si necessaire.
- Functional: `ContactControllerTest` (formulaire + protections + rate limit).

## Risques / rollback
- Risque: divergence de message flash guard sur contact.
- Mitigation: reutilisation du meme responder guard + tests fonctionnels.
