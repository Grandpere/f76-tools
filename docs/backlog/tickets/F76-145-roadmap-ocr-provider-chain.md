# F76-145 - Roadmap OCR provider chain (Tesseract -> fallback)

## Contexte
La roadmap saison est surtout disponible sous forme d image. L objectif est d automatiser l extraction des evenements sans saisie manuelle, avec validation admin avant publication.

## Scope
- [ ] Poser un contrat OCR applicatif (provider + resultat structure).
- [ ] Ajouter une chaine de providers OCR avec fallback (ordre prioritaire: Tesseract puis provider secondaire).
- [ ] Ajouter des regles de qualite (seuil confiance + contenu minimum) pour decider le fallback.
- [ ] Ajouter les tests unitaires de la chaine et des regles de qualite.
- [ ] Documenter le besoin de validation explicite avant ajout de dependances OCR systeme.

## Criteres d acceptance
- Le moteur OCR peut tenter plusieurs providers dans un ordre defini.
- Si la qualite est insuffisante sur le provider primaire, le secondaire est tente.
- Les tentatives sont tracables (provider, succes/echec, raisons qualite).
- Les tests couvrent: succes premier provider, fallback, provider en erreur, absence de provider.

## Tests
- Unit: `tests/Unit/Catalog/Roadmap/Ocr/OcrProviderChainTest.php`
- Functional: N/A (slice architecture)

## Risques / rollback
- Risque: chainage trop permissif qui masque des OCR mediocres.
- Mitigation: seuils explicites + review humaine obligatoire avant publication.
