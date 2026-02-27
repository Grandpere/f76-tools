# F76-084 - Minerva Admin Manual Overrides

## Contexte
La rotation Minerva est generee de facon deterministe. Il faut pouvoir traiter rapidement un ecart ponctuel via un override manuel admin, sans casser le flux de regeneration.

## Scope
- Ajouter une source de rotation (`generated` / `manual`) sur `minerva_rotation`.
- Ajouter un bloc backoffice pour creer/supprimer des overrides manuels.
- Faire en sorte que la regeneration ignore les fenetres generees qui chevauchent un override manuel.
- Afficher la source dans la timeline admin.

## Criteres d acceptance
- Un admin peut creer et supprimer un override manuel via `/admin/minerva-rotation`.
- Une regeneration sur une periode incluant un override manuel conserve cet override et n ajoute pas de fenetre generee chevauchante.
- Le front continue de lire la meme timeline, sans regressions.

## Tests
- Unit:
  - `MinervaRotationRegenerationApplicationService` (skip en cas de chevauchement manuel),
  - `MinervaRotationOverrideApplicationService`.
- Functional:
  - creation/suppression d override admin,
  - regeneration avec override conserve.

## Risques / rollback
- Risque: chevauchements non intentionnels si saisie admin invalide.
- Mitigation: validations strictes + CSRF + flash explicite.
- Rollback: revert du commit ticket (migration incluse via down).

