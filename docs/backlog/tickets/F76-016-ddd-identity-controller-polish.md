# F76-016 - DDD Slice 6: Identity Controller Polish

## Contexte
Les use-cases Identity sont extraits, mais certains controllers conservent encore du code de mapping HTTP repetitif (status -> flash key, redirect target).
Un dernier polish peut encore reduire le bruit UI sans changer le comportement.

## Scope
- Introduire des petits mappers UI dedies (par flux) pour convertir resultats applicatifs en feedback HTTP.
- Garder les controllers concentres sur orchestration minimale.
- Eviter toute rupture de routes/messages existants.

## Avancement
- [x] `RegistrationFeedbackMapper` ajoute (mapping `RegisterUserStatus -> flash warning`).
- [x] `RegistrationController` utilise le mapper UI pour le feedback d echec register.
- [x] `ResetPasswordFeedbackMapper` ajoute (mapping `ResetPasswordResult -> flash + policy redirect`).
- [x] `ResetPasswordController` utilise le mapper UI pour centraliser le feedback reset.
- [x] `IdentityGuardFailureResponder` ajoute pour centraliser feedback des refus de guards.
- [x] `Registration/Forgot/Resend` reutilisent `IdentityGuardFailureResponder`.
- [x] Tests unitaires des mappers UI ajoutes.
- [x] `IdentityIssuedTokenNotifier` ajoute pour mutualiser envoi email + audit log des tokens emis.
- [x] `Registration/Forgot/Resend` reutilisent `IdentityIssuedTokenNotifier`.
- [x] `IdentitySignedTokenFailureResolver` ajoute pour mutualiser la validation URL signee + token.
- [x] `ResetPassword/VerifyEmail` reutilisent `IdentitySignedTokenFailureResolver`.
- [x] `IdentityEmailFormPayloadExtractor` ajoute pour normaliser le payload formulaire des flows email.
- [x] `Registration/Forgot/Resend` reutilisent `IdentityEmailFormPayloadExtractor`.
- [x] `IdentityEmailFlowGuard` ajoute pour mutualiser `extract payload + guard + resolve failure`.
- [x] `Registration/Forgot/Resend` reutilisent `IdentityEmailFlowGuard`.

## Criteres d acceptance
- Duplication de mapping reduite.
- Messages flash existants conserves.
- Aucune regression fonctionnelle.

## Tests
- Unit: mappers UI.
- Functional: login/register/verify/forgot/reset/resend.

## Risques / rollback
- Risque: divergence entre status metier et flash key.
- Mitigation: tests unitaires exhaustifs sur mapping.
