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
- [x] Use-case `ResetPasswordApplicationService` extrait (+ `ResetPasswordResult`).
- [x] Ports `ResetPasswordUserRepositoryInterface` et `IdentityPasswordHasherInterface` ajoutes.
- [x] `ResetPasswordController` delegue la logique metier au service applicatif.
- [x] Ports Identity communs centralises dans `Identity/Application/Common`.
- [x] Test unitaire `ResetPasswordApplicationService` ajoute.
- [x] Use-case `ForgotPasswordRequestApplicationService` extrait.
- [x] Port `ForgotPasswordUserRepositoryInterface` ajoute.
- [x] `ForgotPasswordController` delegue la logique de cooldown + emission token.
- [x] Test unitaire `ForgotPasswordRequestApplicationService` ajoute.
- [x] Use-case `RegisterUserApplicationService` extrait (+ resultat metier).
- [x] Port `RegistrationUserRepositoryInterface` ajoute.
- [x] `RegistrationController` delegue creation utilisateur + token verification.
- [x] Test unitaire `RegisterUserApplicationService` ajoute.
- [x] Use-case `ResendVerificationRequestApplicationService` extrait.
- [x] Port `ResendVerificationUserRepositoryInterface` ajoute.
- [x] `ResendVerificationController` delegue la logique de cooldown + emission token verification.
- [x] Test unitaire `ResendVerificationRequestApplicationService` ajoute.
- [x] Port `IdentityLinkEmailSenderInterface` ajoute pour les emails de liens Identity.
- [x] Adaptateur infra `IdentityLinkEmailSender` (Signed URL + mailer + traduction) ajoute.
- [x] Controllers `Registration/Forgot/Resend` deleguent l envoi mail au port applicatif.

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
