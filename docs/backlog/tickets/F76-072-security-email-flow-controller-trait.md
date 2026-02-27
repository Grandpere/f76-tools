# F76-072 - Security Email Flow Controller Trait

## Contexte
`RegistrationController`, `ForgotPasswordController` et `ResendVerificationController` repetaient la meme sequence de garde de formulaire email (guard + flash warning + payload).

## Scope
- Introduire un trait controller partage pour ce pattern.
- Migrer les 3 controllers concernes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `IdentityEmailFlowControllerTrait`.
- [x] Migrer `RegistrationController`, `ForgotPasswordController`, `ResendVerificationController`.
- [x] Verifier phpstan/unit/integration.
