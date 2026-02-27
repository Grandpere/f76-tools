# F76-087 - BOOK Lists As Absolute 1..24

## Contexte
Le modele actuel compresse les plans Minerva en 4 groupes (`list_number 1..4`) alors que le metier manipule des listes absolues 1..24.
Ce decalage cree une progression trompeuse et des ambiguities entre "liste" et "cycle".

## Scope
- Passer `item_book_list.list_number` en contrainte `1..24`.
- Import Minerva:
  - mapper `minerva_61..84` vers listes absolues `1..24`,
  - conserver la logique de liste speciale sur chaque 4e liste,
  - reconstruire les liaisons BOOK a chaque import pour eviter les residus historiques `1..4`.
- Front dashboard BOOK: supprimer le hardcode 1..4 et afficher les groupes selon les listes presentes.
- Progression Minerva front: consommer prioritairement les listes absolues, avec fallback temporaire.

## Criteres d acceptance
- Les stats `bookByList` peuvent exposer des listes au-dela de 4 (jusqu a 24 selon donnees importees).
- Le dashboard affiche les plans Minerva par listes reelles importees (plus de hardcode 4 listes).
- La colonne progression de la page rotation Minerva affiche des valeurs coherentes sur toutes les listes.

## Migration/Runbook
- Executer migration DB.
- Relancer un import complet JSON avec reset des liaisons BOOK.
- Verifier visuellement dashboard + rotation Minerva.

