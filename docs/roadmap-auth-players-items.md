# Roadmap Auth + Players + Item Knowledge

## Objectif
Permettre a un utilisateur authentifie de:
- se connecter (sans inscription publique),
- creer ses personnages (`Player`),
- marquer pour chaque `Player` si un `Item` est appris ou non (`BOOK` Minerva et `MISC` Legendary Mods).

## Etat actuel (base)
- Import `Item` en place via `app:items:import`.
- Traductions generees en `translations/items.en.yaml` et `translations/items.de.yaml`.
- `BOOK` supporte plusieurs listes via `item_book_list`.
- Regle `rank` enforcee pour `MISC` (et interdite pour `BOOK`).

## Phase 1 - Authentification
- [x] Creer `UserEntity` (email unique, password hash, roles, isActive, createdAt/updatedAt).
- [x] Configurer `security.yaml` (provider Doctrine, firewall main, login form, logout).
- [ ] Ajouter pages/routes:
  - [x] `GET/POST /login`
  - [x] `GET /logout`
- [x] Creer commande console:
  - [x] `app:user:create <email> --password=... [--role=ROLE_USER]`
  - [ ] Option potentielle `--update-password`.

## Phase 2 - Players
- [x] Creer `PlayerEntity`:
  - [x] relation `ManyToOne User`
  - [x] `name` (non vide)
  - [x] `createdAt`, `updatedAt`
- [ ] Ajouter contrainte d'unicite (au choix):
  - [x] unique `(user_id, name)` pour eviter doublons de nom par user.
- [ ] Routes/controller:
  - [x] `GET /api/players`
  - [x] `POST /api/players`
  - [x] `GET /api/players/{id}`
  - [x] `PATCH /api/players/{id}` (optionnel)
  - [x] `DELETE /api/players/{id}` (optionnel)
- [x] Ajouter controle d'acces ownership (Voter ou check service).

## Phase 3 - Knowledge par Player
- [ ] Creer `PlayerItemKnowledgeEntity`:
  - [x] relation `ManyToOne Player`
  - [x] relation `ManyToOne Item`
  - [x] modele presence/absence (une ligne = learned)
  - [x] `learnedAt`
  - [ ] timestamps
- [x] Contrainte unique `(player_id, item_id)`.
- [ ] Index de perf:
  - [x] `(player_id, item_id)`
  - [x] `(item_id)` si necessaire pour stats.
- [ ] API JSON:
  - [x] `PUT /api/players/{playerId}/items/{itemId}/learned` (set true)
  - [x] `DELETE /api/players/{playerId}/items/{itemId}/learned` (set false)
  - [x] `GET /api/players/{playerId}/items?type=...` (liste avec statut learned)

## Phase 4 - Front Twig + UX
- [ ] Ecran catalogue items:
  - [ ] filtres type (`MISC`/`BOOK`), recherche texte
  - [ ] badge/rank/lists selon type
  - [ ] statut appris pour player courant
- [ ] Actions front:
  - [ ] toggle learned via Stimulus + fetch API
  - [ ] retour visuel (loading, succes, erreur)
- [ ] Selection du player actif (session ou query param).

## Phase 5 - Qualite et tests
- [ ] Tests unitaires:
  - [ ] service de toggle learned
  - [ ] regles ownership
- [ ] Tests fonctionnels:
  - [ ] login/logout
  - [x] creation player
  - [x] marquage learned/unlearned
  - [x] interdiction acces cross-user
- [ ] Garder `make phpstan` et tests verts a chaque phase.

## Risques / Decisions a figer
- [ ] Decider le modele exact learned:
  - [ ] `isLearned` bool (historique simple),
  - [ ] ou presence/absence en table pivot.
- [ ] Politique de suppression:
  - [ ] supprimer un player supprime son knowledge (`ON DELETE CASCADE`).
- [ ] Pagination catalogue pour eviter surcharge front.

## Definition of Done (MVP)
- [ ] Un admin/dev peut creer un user via commande.
- [ ] Le user se connecte.
- [ ] Le user cree au moins un player.
- [ ] Le user marque learned/unlearned sur items.
- [ ] Le statut est persiste et reaffiche correctement.
