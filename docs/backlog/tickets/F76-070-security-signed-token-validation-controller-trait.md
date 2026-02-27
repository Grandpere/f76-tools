# F76-070 - Security Signed Token Validation Controller Trait

## Contexte
`ResetPasswordController` et `VerifyEmailController` partageaient le meme pattern de validation de token signe via `IdentitySignedTokenFailureResolver`.

## Scope
- Introduire un trait controller partage pour cette validation.
- Migrer les deux controllers concernes.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `IdentitySignedTokenValidationControllerTrait`.
- [x] Migrer `ResetPasswordController` et `VerifyEmailController`.
- [x] Verifier phpstan/unit/integration.
