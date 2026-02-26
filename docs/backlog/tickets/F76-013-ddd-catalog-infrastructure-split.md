# F76-013 - DDD Slice 3: Catalog Infrastructure Split

## Contexte
Apres extraction des use-cases Catalog, certains composants techniques restent melanges dans `Application`.
Il faut isoler les concerns infra (filesystem/import source, I/O externes) sous `Catalog/Infrastructure` tout en gardant le comportement actuel.

## Scope
- Introduire des ports applicatifs pour l import des sources de donnees.
- Deplacer les implementations techniques vers `Catalog/Infrastructure`.
- Garder `ImportItemsCommand` en adaptateur UI et `ItemImportApplicationService` en orchestration metier.

## Avancement
- [x] Port `ItemImportSourceReaderInterface` ajoute en Application.
- [x] Implementation filesystem `FilesystemItemImportSourceReader` ajoutee en Infrastructure.
- [x] `ItemImportApplicationService` depend du port source reader.
- [x] Test unitaire source reader adapte vers implementation Infrastructure.
- [x] Port `ItemImportPersistenceInterface` ajoute en Application.
- [x] Adaptateur Doctrine `DoctrineItemImportPersistence` ajoute en Infrastructure.
- [x] `ItemImportApplicationService` ne depend plus de `EntityManagerInterface`.
- [x] Implementations YAML de catalogues de traduction deplacees vers `Catalog/Infrastructure/Translation`.

## Criteres d acceptance
- Les interfaces Application ne dependent pas de classes techniques concretes.
- Les details filesystem sont confines a Infrastructure.
- Les checks existants restent verts.

## Tests
- Unit: import service + source reader.
- Functional: import command inchangée en comportement.

## Risques / rollback
- Risque: regression detection fichiers JSON.
- Mitigation: tests unitaires dedies + functional import a rejouer si besoin.
