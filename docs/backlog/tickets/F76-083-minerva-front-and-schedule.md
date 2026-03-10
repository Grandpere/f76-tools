# F76-083 - Minerva Front And Schedule

## Contexte
La base DDD est stabilisee. Le prochain besoin produit est d'ameliorer l'experience Minerva sur le front pour afficher clairement la situation courante (localisation, dates, liste) et les prochaines rotations.

## Scope
- Enrichir la page front Minerva (`/minerva-rotation`) avec un bloc de synthese:
  - fenetre actuelle (si active),
  - prochaines fenetres a venir.
- Afficher pour chaque fenetre: localisation, numero de liste, debut/fin.
- Conserver la timeline detaillee existante.
- Ajouter/mettre a jour la couverture fonctionnelle associee.

## Criteres d acceptance
- Un utilisateur connecte voit, en haut de page:
  - soit la fenetre active, soit un etat vide explicite,
  - les prochaines fenetres a venir.
- Les informations de synthese sont coherentes avec les donnees de rotation stockees en base.
- La table historique/complete reste disponible et inchangee fonctionnellement.

## Tests
- Unit:
  - service timeline Minerva: calcul du bloc `current` et `upcoming`.
- Functional:
  - page front Minerva: rendu du bloc de synthese + timeline.

## Risques / rollback
- Risque: regressions de rendu Twig si cles de traduction manquantes.
- Rollback: revert du commit ticket sans impact schema/migration.

## Statut
- Done - 2026-03-10
