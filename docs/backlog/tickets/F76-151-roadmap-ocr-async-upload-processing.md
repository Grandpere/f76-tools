# F76-151 - Roadmap OCR async upload processing

## Status
`Todo`

## Contexte
- Le scan OCR roadmap (preprocess + providers + parsing) devient de plus en plus long.
- En backoffice, attendre en requete synchrone degrade l UX (timeouts, attente non visible, retours tardifs).

## Objectif
Basculer le traitement OCR roadmap en asynchrone apres upload image:
- upload rapide,
- traitement en arriere-plan,
- suivi clair du statut en admin.

## Scope
- Ajouter un message Messenger dedie (ex: `ProcessRoadmapOcrUploadMessage`).
- Stocker un statut de traitement snapshot:
  - `queued`, `processing`, `done`, `failed`.
- Modifier l action upload admin:
  - sauvegarde image + creation snapshot initial,
  - dispatch message async,
  - retour immediat avec flash "traitement en cours".
- Handler async:
  - preprocess image,
  - OCR chain,
  - persistence raw text/confidence/attempts,
  - extraction saison,
  - gestion erreurs + message explicite.
- UI admin:
  - badge de statut par snapshot,
  - bouton "rafraichir" (ou auto-refresh leger) pour voir fin de traitement.

## Hors scope
- Queue distribuee complexe (un seul transport existant suffit).
- Refonte complete UX roadmap.

## Criteres d acceptance
- L upload retourne rapidement sans faire OCR en requete HTTP.
- Le statut evolue correctement (`queued -> processing -> done|failed`).
- En cas d echec OCR, le snapshot reste consultable avec erreur visible.
- Aucun impact regressif sur le flux JSON manuel.

## Tests
- Unit:
  - mapping statuts,
  - handler async success/failure.
- Integration:
  - dispatch message et execution handler.
- Functional:
  - upload admin cree un snapshot `queued`,
  - apres traitement, snapshot complete et visible.

## Risques / rollback
- Risque: complexite de suivi et etats intermediaires.
- Mitigation: etats simples, transitions explicites, logs admin.
- Rollback: feature flag pour revenir temporairement au mode synchrone.

