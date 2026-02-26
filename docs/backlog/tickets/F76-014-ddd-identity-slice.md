# F76-014 - DDD Slice 4: Identity Context

## Contexte
Le contexte Identity (register, verify email, forgot/reset password, login security) fonctionne mais reste encore partiellement porte par des controllers/services techniques.
Il faut poursuivre la migration DDD en isolant les use-cases et les policies de securite.

## Scope
- Extraire les use-cases Identity en services applicatifs (`RegisterUser`, `VerifyEmail`, `RequestPasswordReset`, `ResetPassword`).
- Introduire des ports pour les effets externes (mail dispatch, token persistence, rate-limit policy access).
- Amincir les controllers security: validation HTTP + delegation.

## Avancement
- [x] Use-case `VerifyEmailApplicationService` extrait.
- [x] Ports `VerifyEmailUserRepositoryInterface` et `IdentityWritePersistenceInterface` ajoutes.
- [x] `VerifyEmailController` delegue la verification au service applicatif.
- [x] Test unitaire `VerifyEmailApplicationService` ajoute.

## Criteres d acceptance
- Controllers Identity deviennent thin (pas de logique metier).
- Policies metier de securite/expiration centralisees et testees en unit.
- Flux existants gardent le meme comportement observable.

## Tests
- Unit: use-cases + policies.
- Integration: persistence token/password reset.
- Functional: login/register/verify/forgot/reset (a faire valider cote user).

## Risques / rollback
- Risque: regressions sur tokens d email verification / reset password.
- Mitigation: couverture functional existante conservee + tests unitaires sur policies temporelles.
