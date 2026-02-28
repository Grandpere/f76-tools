# F76-134 - Identity ports without Interface suffix

## Contexte
La convention projet retire le suffixe `Interface` pour les ports applicatifs.
Le contexte `Identity` conservait encore plusieurs ports suffixes.

## Scope
- Renommer les ports `Identity` suivants sans suffixe:
  - `IdentityPasswordHasherInterface` -> `IdentityPasswordHasher`
  - `IdentityPasswordVerifierInterface` -> `IdentityPasswordVerifier`
  - `IdentityWritePersistenceInterface` -> `IdentityWritePersistence`
  - `ForgotPasswordUserRepositoryInterface` -> `ForgotPasswordUserRepository`
  - `IdentityCaptchaSiteKeyProviderInterface` -> `IdentityCaptchaSiteKeyProvider`
  - `IdentityCaptchaVerifierInterface` -> `IdentityCaptchaVerifier`
  - `IdentityRateLimiterInterface` -> `IdentityRateLimiter`
  - `IdentityRequestGuardInterface` -> `IdentityRequestGuard`
  - `IdentityLinkEmailSenderInterface` -> `IdentityLinkEmailSender`
  - `IdentitySignedLinkGeneratorInterface` -> `IdentitySignedLinkGenerator`
  - `RegistrationUserRepositoryInterface` -> `RegistrationUserRepository`
  - `ResendVerificationUserRepositoryInterface` -> `ResendVerificationUserRepository`
  - `ResetPasswordUserRepositoryInterface` -> `ResetPasswordUserRepository`
  - `IdentityClockInterface` -> `IdentityClock`
  - `VerifyEmailUserRepositoryInterface` -> `VerifyEmailUserRepository`
- Propager ces changements dans services, infra, UI, tests et DI.
- Corriger les collisions de nom implementation/port (`IdentityRequestGuard`, `IdentityLinkEmailSender`, `IdentitySignedLinkGenerator`).

## Criteres d acceptance
- Plus aucun type `*Interface` pour les ports `Identity` listés.
- Wiring `config/services.yaml` fonctionnel.
- Suites unit/integration + phpstan + cs-check passent.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

