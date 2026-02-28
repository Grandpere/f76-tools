# F76-089 - Minerva Admin Datepicker And Timeline Polish

## Contexte
Le calendrier natif des champs date/datetime dans l'admin Minerva ne pouvait pas etre stylise de facon coherente avec le theme, et plusieurs details de presentation Minerva (ordre/largeurs colonnes, synchronisation progression) restaient fragiles.

## Scope
- Integrer `flatpickr` via importmap sur les formulaires admin Minerva (regeneration + override).
- Harmoniser le theme visuel du popup calendrier avec le design system existant.
- Aligner l'ordre des colonnes de la timeline admin sur le front (`Liste`, `Localisation`, `Debut`, `Fin`, `Source`, `Statut`).
- Ajuster les largeurs de colonnes (liste compacte, localisation prioritaire).
- Stabiliser la progression Minerva cote front:
  - suppression du fallback legacy modulo-4,
  - rafraichissement de la progression quand le joueur actif change.
- Ajouter/mettre a jour la couverture fonctionnelle associee.

## Criteres d acceptance
- Les formulaires admin Minerva utilisent `minerva-admin-datepicker` et ouvrent un datepicker stylise.
- La timeline admin Minerva affiche les colonnes dans l'ordre cible et avec des proportions lisibles.
- La progression de timeline front Minerva suit strictement les listes 1..24.
- Changer de joueur sur la page Minerva met a jour la progression sans rechargement.
- Les tests fonctionnels Minerva (front/admin) couvrent l'ordre de colonnes et les hooks de controller critiques.

## Statut
- Done
