# F76-071 - Security Captcha Render Controller Trait

## Contexte
`RegistrationController`, `ForgotPasswordController` et `ResendVerificationController` rendaient chacun un template avec le meme payload `captchaSiteKey`.

## Scope
- Introduire un trait de rendu partage pour ce pattern.
- Migrer les 3 controllers concernes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `IdentityCaptchaRenderControllerTrait`.
- [x] Migrer `RegistrationController`, `ForgotPasswordController`, `ResendVerificationController`.
- [x] Verifier phpstan/unit/integration.
