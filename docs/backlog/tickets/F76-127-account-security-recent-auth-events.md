# F76-127 - Account security recent auth events

## Contexte
La page profil securite affichait les statuts et sessions actives, mais pas l historique recent des evenements d authentification du compte.

## Scope
- Afficher les derniers evenements auth sur `/account-security`.
- Reutiliser la lecture `AuthAuditLogReader`.
- Ajouter un test fonctionnel cible sur la presence d un evenement recent.

## Criteres d acceptance
- Un utilisateur connecte voit les derniers evenements auth de son compte.
- En absence d evenements, un etat vide clair est affiche.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
