# F76-118 - User self-service change password

## Contexte
Un utilisateur authentifie ne dispose pas encore d un ecran dedie pour changer son mot de passe depuis son compte (hors flow forgot/reset).

## Scope
- Ajouter une page securisee `GET/POST` pour changer le mot de passe utilisateur connecte.
- Formulaire:
  - mot de passe actuel,
  - nouveau mot de passe,
  - confirmation du nouveau mot de passe.
- Verifications serveur:
  - mot de passe actuel correct,
  - nouveau mot de passe conforme aux regles de securite,
  - confirmation identique.
- Mise a jour du hash password en base.
- Invalidation des tokens de reset/password temporaires existants.
- Message flash de succes/erreur localise.
- Journalisation d un evenement de securite.

## Critere d acceptance
- Un utilisateur connecte peut changer son mot de passe avec son mot de passe actuel.
- En cas de mot de passe actuel invalide, le changement est refuse avec message clair.
- Les validations du nouveau mot de passe sont appliquees.
- Le mot de passe est effectivement remplace en base.

## Tests
- Unit:
  - validation mot de passe actuel,
  - application service change password (success/failure).
- Functional:
  - acces protege (redirige si anon),
  - changement reussi,
  - changement refuse (ancien mot de passe incorrect).

## Risques / rollback
- Risque: regression sur les flows login/reset existants.
- Mitigation: couverture fonctionnelle login + reset conservee et executee.
