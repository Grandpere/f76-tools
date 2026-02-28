# F76-113 - Admin users unlink Google UI guard

## Contexte
Le backoffice avait deja une garde serveur qui bloque le deliaison Google si l utilisateur n a pas de mot de passe local, mais l action restait cliquable en UI.

## Scope
- Desactiver le bouton `Delier Google` dans la ligne utilisateur quand `hasLocalPassword = false`.
- Ajouter un message d aide visible dans la cellule `Actions`.
- Ajouter un style visuel explicite pour le bouton desactive.
- Ajouter les traductions FR/EN/DE du message.
- Ajouter un test fonctionnel sur la presence du bouton desactive.

## Critere d acceptation
- L admin voit l action unlink Google desactivee pour un user sans mot de passe local.
- Un message explique la raison directement dans la ligne.
- Le comportement serveur existant reste inchangé (defense en profondeur).

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

## Risques / rollback
- Risque: confusion UI si message absent.
- Mitigation: message explicite et fallback serveur deja en place.
