# F76-078 - Contact Status Update Typed Request

## Contexte
`ContactMessageStatusUpdateApplicationService` recevait encore un `mixed` pour le statut et faisait sa propre sanitation.

## Scope
- Introduire un objet de requete typé `ContactMessageStatusUpdateRequest`.
- Migrer le service de mise a jour de statut.
- Migrer `ContactMessageController`.
- Adapter la couverture unitaire associee.
- Verifier phpstan/unit/integration.

## Avancement
- [x] Ajouter `ContactMessageStatusUpdateRequest`.
- [x] Migrer `ContactMessageStatusUpdateApplicationService`.
- [x] Migrer `ContactMessageController`.
- [x] Adapter les tests unitaires associes.
- [x] Verifier phpstan/unit/integration.
