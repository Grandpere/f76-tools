# F76-143 - Tech monitoring, alerting and smoke suite hardening

## Contexte
Le check Minerva JSON est disponible, les audits existent, et la base de tests est large.  
Il manque un cadrage technique final pour la surveillance operationnelle et une suite smoke rapide pour les regressions majeures.

## Objectif
- Transformer les checks existants en signal operationnel exploitable.
- Reduire le temps de detection des regressions critiques.
- Standardiser un minimum de garde-fous CI/ops.

## Scope
- [ ] Definir le format de log/alert attendu a partir de `make minerva-refresh-check-json`.
- [ ] Ajouter une section "monitoring thresholds" dans `docs/ops/ops-runbook.md`.
- [ ] Definir une smoke suite fonctionnelle minimale (routes critiques front/admin/auth/API).
- [ ] Documenter les commandes smoke dans `Makefile` (sans remplacer les suites completes).
- [ ] Formaliser la procedure de triage en cas d alerte Minerva ou auth.

## Criteres d acceptance
- Une alerte Minerva "drift" est detectable automatiquement a partir du JSON.
- Une smoke suite ciblee est documentee et executable rapidement.
- Le runbook contient des seuils et actions immediates associees.

## Tests
- Unit: eventuels tests de parsing/normalisation si nouveau composant technique.
- Functional: couverture smoke ciblee definie et verifiee manuellement.

## Risques / rollback
- Risque: surcharger la CI avec une suite trop large.
- Mitigation: smoke minimale et stable, distincte de la suite fonctionnelle complete.

