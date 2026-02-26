# F76-015 - DDD Slice 5: Identity Request Guards

## Contexte
Les controllers Identity sont devenus plus fins, mais les gardes HTTP repetitives (csrf, honeypot, captcha, rate-limit) restent dupliquees dans plusieurs endpoints.
Il faut factoriser ces gardes sans perdre la lisibilite ni la robustesse securite.

## Scope
- Introduire un service applicatif/anti-corruption pour les gardes de requete Identity.
- Centraliser la decision `allow/deny` + raison metier securite.
- Reducer la duplication dans `Registration`, `ForgotPassword`, `ResendVerification`.

## Avancement
- [x] Port `IdentityRequestGuardInterface` ajoute.
- [x] Resultat metier `IdentityRequestGuardResult` ajoute.
- [x] Adaptateur infra `IdentityRequestGuard` (csrf + honeypot + captcha + rate-limit) ajoute.
- [x] Ports `IdentityCaptchaVerifierInterface` et `IdentityRateLimiterInterface` ajoutes pour eviter le couplage aux services finaux.
- [x] Adaptateurs infra `TurnstileIdentityCaptchaVerifier` et `AuthRequestThrottlerRateLimiter` ajoutes.
- [x] `RegistrationController`, `ForgotPasswordController`, `ResendVerificationController` deleguent les gardes au service.
- [x] `IdentityGuardFailureResponder` ajoute pour centraliser log + choix du message flash selon le resultat guard.
- [x] Controllers Identity reutilisent `IdentityGuardFailureResponder` pour les refus guard.
- [x] Port `IdentityRequestGuardInterface` lie dans `services.yaml`.
- [x] Test unitaire `IdentityRequestGuard` ajoute.
- [x] Test unitaire `IdentityGuardFailureResponder` ajoute.

## Criteres d acceptance
- Duplication de gardes reduite de facon visible.
- Messages flash et logs securite restent coherents avec le comportement actuel.
- Aucune regression fonctionnelle observable.

## Tests
- Unit: service de guard decision.
- Functional: valider les flows security existants.

## Risques / rollback
- Risque: erreur de mapping entre raison guard et message flash.
- Mitigation: tests unitaires sur mapping + functional sur cas de refus.
