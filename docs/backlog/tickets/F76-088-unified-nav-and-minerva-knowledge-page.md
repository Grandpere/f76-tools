# F76-088 - Unified Nav And Minerva Knowledge On Rotation Page

## Contexte
Le header et la navigation divergeaient entre dashboard et rotation Minerva, et le suivi des plans Minerva devait etre disponible directement sur la page rotation.

## Scope
- Introduire un composant de navigation primaire reutilisable entre pages applicatives.
- Aligner les headers dashboard/minerva.
- Ajouter un bloc de suivi BOOK (plans Minerva) sur `/minerva-rotation` avec:
  - selection joueur,
  - recherche,
  - filtres source BOOK,
  - checkbox appris/non appris.

## Criteres d acceptance
- La navigation principale est coherente sur dashboard et minerva.
- Un joueur peut cocher/decocher ses plans Minerva depuis `/minerva-rotation`.
- Le changement de joueur est partage via la meme cle storage que le dashboard.

