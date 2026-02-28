# F76-131 - Account security UX polish

## Contexte
La page `/account-security` etait fonctionnelle mais manquait de signaux visuels rapides pour lire les volumes (sessions/evenements) et lier vers l investigation admin.

## Scope
- Ajouter compteurs visibles sur sections sessions et evenements de securite.
- Ajouter badges de niveau (`info`/`warning`) sur les evenements.
- Ajouter lien rapide vers la timeline admin des evenements pour les utilisateurs admin.
- Ajouter couverture fonctionnelle ciblee sur la presence du lien admin.

## Criteres d acceptance
- Un utilisateur voit des sections plus lisibles avec compteurs.
- Un admin voit un lien vers sa timeline auth admin depuis `/account-security`.
- La page conserve les flows existants (unlink Google, revoke sessions, change password).

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)

