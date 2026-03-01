# F76-139 - Minerva polish UI + audit summary read service

## Contexte
Post-F76-138, la page admin Minerva fonctionnait mais conservait encore une lecture Doctrine inline dans le controller pour le resume de refresh.  
Le front Minerva avait aussi des classes de layout redondantes qui rendaient l espacement moins net.

## Objectif
- Stabiliser la couche UI Minerva (micro-polish visuel).
- Renforcer la separation DDD en sortant la lecture du resume de refresh Minerva hors du controller.

## Scope
- [x] Ajouter un service applicatif dedie pour lire le dernier resume de refresh Minerva depuis les audits.
- [x] Etendre le port `AuditLogReadRepository` avec une lecture ciblee `findLatestByActions`.
- [x] Adapter `AdminAuditLogEntityRepository` a ce nouveau contrat.
- [x] Simplifier `MinervaRotationController` (plus de query builder inline pour ce cas).
- [x] Micro-polish Twig/CSS sur Minerva front/admin (classes dediees + lien audit plus lisible).
- [x] Ajouter une couverture unitaire pour le nouveau service applicatif.

## Hors scope
- Aucun changement de regles metier Minerva.
- Aucune modification des routes/API.
- Aucune migration DB.

## Verification
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (manuel utilisateur en fin de lot)

## Risques
- Impact faible: extension de port applicatif shared (`AuditLogReadRepository`) necessite mise a jour des doubles de tests.

