# F76-122 - Front account security page

## Contexte
Le compte utilisateur avait deja des actions de securite (mot de passe, Google OIDC), mais aucun ecran dedie pour visualiser clairement l etat de securite du compte.

## Scope
- Ajouter une page front `GET /account-security` (utilisateur connecte requis).
- Afficher un recapitulatif:
  - email,
  - email verifie,
  - mot de passe local actif,
  - Google lie + date de liaison.
- Ajouter un acces depuis le menu compte du header.
- Ajouter les traductions FR/EN/DE associees.
- Ajouter une couverture fonctionnelle ciblee:
  - redirection si anonyme,
  - rendu des informations pour utilisateur connecte,
  - affichage de l etat Google lie.

## Criteres d acceptance
- Un utilisateur connecte accede a une page claire de profil securite.
- Un anonyme est redirige vers login.
- Le statut Google et les autres indicateurs de securite sont visibles.

## Tests
- `make phpstan`
- `make phpunit-unit`
- `make phpunit-integration`
- `make php-cs-fixer-check`
- `make phpunit-functional` (execute manuellement par l utilisateur)
