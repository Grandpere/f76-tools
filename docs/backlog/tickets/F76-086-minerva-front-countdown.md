# F76-086 - Minerva Front Countdown

## Contexte
Le front Minerva a besoin d un indicateur temporel dynamique pour savoir rapidement si Minerva est disponible et dans combien de temps elle arrive/repart.

## Scope
- Ajouter un bloc "Where is Minerva today?" sur `/minerva-rotation`.
- Afficher un compte a rebours dynamique (jours/heures/minutes/secondes):
  - vers `endsAt` si Minerva est active,
  - vers `startsAt` de la prochaine fenetre si elle est absente.
- Implementer le compteur avec Stimulus sans dependance externe.
- Garder la source (`generated/manual`) cachee sur le front public.

## Criteres d acceptance
- Le bloc est visible et coherent avec l etat temporel actuel.
- Le compte a rebours decremente en temps reel sans rechargement.
- En absence de fenetre planifiee, un message explicite est affiche sans compteur.

## Tests
- Functional: presence du bloc countdown et des cibles Stimulus sur la page Minerva.

## Statut
- Done - 2026-03-10
