# F76-150 - Roadmap OCR microservice Python (optionnel)

## Status
`Todo (Optional)`

## Contexte
- Le pipeline OCR roadmap peut devenir difficile a maintenir en PHP seul (layout analysis, detection fine, scoring).
- Une extraction dans un service dedie Python (OpenCV/Paddle detector) peut faciliter les iterations si le besoin se confirme.

## Objectif
Ajouter un provider OCR Python optionnel, activable par feature flag, qui complete la chaine actuelle sans casser le flux admin OCR async.

## Contrat HTTP cible
### Endpoint
- `POST /ocr/roadmap/scan`

### Request
- `multipart/form-data`
  - `image`: fichier image (png/jpg/webp)
  - `locale`: `fr|en|de`
  - `preprocess`: `none|grayscale|bw|strong-bw|layout-bw`
  - `request_id` (optionnel): id de correlation

### Response
- `200 application/json`
  - `provider`: `python.ocr`
  - `confidence`: `float` (0..1)
  - `text`: texte concatene
  - `lines`: liste des lignes OCR
  - `meta`:
    - `mode`
    - `input_width`, `input_height`
    - `output_width`, `output_height`
    - `zone_count`
    - `zones[]` (`x`,`y`,`w`,`h`,`confidence`,`line_count`)
    - `duration_ms`
  - `errors[]` (optionnel)

### Health
- `GET /healthz` -> `200 {"status":"ok"}`

## Hors scope
- dependances payantes OCR.
- activation par defaut du provider Python.
- remplacement du parseur metier roadmap existant.

## Integration Symfony (cible)
### Feature flags / env
- `ROADMAP_PY_OCR_ENABLED=0|1`
- `ROADMAP_PY_OCR_BASE_URL=http://roadmap-ocr:8081`
- `ROADMAP_PY_OCR_TIMEOUT_MS=5000`

### Nouveau provider
- ajouter `PythonRoadmapOcrProvider` (impl `OcrProvider`)
- comportement:
  - si `ROADMAP_PY_OCR_ENABLED=0`: lever `OcrProviderUnavailableException`
  - timeout/5xx: lever `OcrProviderUnavailableException`
  - reponse invalide: lever `RuntimeException` avec cause

### Chaine OCR
- ordre recommande:
  1. `python.ocr` (si enabled)
  2. `ocr.space`
  3. `tesseract`
- conserver la logique actuelle:
  - meilleur resultat de fallback par confidence si aucun provider n est acceptable.

### Debug
- inclure `meta` Python dans `ocrAttemptsSummary` quand disponible.
- ne pas modifier le schema DB (utiliser la colonne summary existante).

## Docker / ops
### Compose
- nouveau service `roadmap-ocr` (profile `ocr` ou `observability` selon convention projet)
- dependances Python minimales (FastAPI + moteur OCR retenu)
- healthcheck `GET /healthz`

### Runbook
- demarrage: `docker compose up -d roadmap-ocr`
- verification: `curl http://localhost:<port>/healthz`
- fallback garanti si service down (provider marked unavailable)

## Criteres d acceptance
- provider Python integrable sans impact quand desactive.
- upload admin roadmap fonctionne toujours si service Python indisponible.
- benchmark local possible avec `app:roadmap:benchmark-ocr-providers`.
- output debug exploitable (meta zones + duree).
- decision go/no-go documentee apres benchmark 10 images fr/en/de.

## Tests
- Unit:
  - mapping JSON -> `OcrResult`
  - gestion timeout / invalid payload / service disabled
- Integration:
  - chaine OCR avec provider Python indisponible => fallback ocr.space/tesseract
  - provider Python OK => attempts resume contient meta
- Functional:
  - upload admin avec `ROADMAP_PY_OCR_ENABLED=0` (comportement inchange)
  - (optionnel) test avec stub HTTP provider Python

## Risques / rollback
- Risque: complexite ops > gain qualite.
- Mitigation: feature flag OFF par defaut + fallback natif existant.
- Rollback: couper `ROADMAP_PY_OCR_ENABLED`, retirer service compose, garder pipeline PHP.

## Plan d execution recommande
1. Slice A: contrat HTTP + provider Symfony stubbe + flags.
2. Slice B: microservice Python minimal (`/healthz`, `/ocr/roadmap/scan`) + docker local.
3. Slice C: branchement provider dans la chaine + tests fallback.
4. Slice D: benchmark dataset reference + decision go/no-go.
