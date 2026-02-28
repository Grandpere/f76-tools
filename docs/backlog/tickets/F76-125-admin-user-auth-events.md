# F76-125 - Admin user auth events page

## Contexte
Le backoffice users ne permettait pas de consulter facilement les derniers evenements d authentification/securite par utilisateur (login, sessions, reset, unlink, etc.).

## Scope
- Ajouter une persistance DB des evenements d authentification (`auth_audit_log`) via `AuthEventLogger`.
- Ajouter une route admin `GET /admin/users/{id}/auth-events`.
- Ajouter une page dediee des derniers evenements securite pour un utilisateur.
- Ajouter un lien depuis la fiche utilisateurs admin vers cette page.

## Criteres d acceptance
- Les evenements auth sont persistes en base sans impacter les flux auth.
- Un admin peut consulter les derniers evenements d un utilisateur cible.
- Un non-admin ne peut pas acceder a cette page.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
