# Roadmap Auth + Players + Item Knowledge

## Objectif
Permettre a un utilisateur authentifie de:
- se connecter (avec inscription publique),
- creer ses personnages (`Player`),
- marquer pour chaque `Player` si un `Item` est appris ou non (`BOOK` Minerva et `MISC` Legendary Mods).

## Documentation ops
- Runbook exploitation: `docs/ops-runbook.md`.

## Etat actuel (base)
- Import `Item` en place via `app:items:import`.
- Traductions generees en `translations/items.en.yaml` et `translations/items.de.yaml`.
- `BOOK` supporte plusieurs listes via `item_book_list`.
- Regle `rank` enforcee pour `MISC` (et interdite pour `BOOK`).

## Phase 1 - Authentification
- [x] Creer `UserEntity` (email unique, password hash, roles, isActive, createdAt/updatedAt).
- [x] Configurer `security.yaml` (provider Doctrine, firewall main, login form, logout).
- [x] Ajouter pages/routes:
  - [x] `GET/POST /login`
  - [x] `GET /logout`
- [x] Creer commande console:
  - [x] `app:user:create <email> --password=... [--role=ROLE_USER]`
  - [x] Option `--update-password`.

## Phase 2 - Players
- [x] Creer `PlayerEntity`:
  - [x] relation `ManyToOne User`
  - [x] `name` (non vide)
  - [x] `createdAt`, `updatedAt`
- [x] Ajouter contrainte d'unicite (au choix):
  - [x] unique `(user_id, name)` pour eviter doublons de nom par user.
- [x] Routes/controller:
  - [x] `GET /api/players`
  - [x] `POST /api/players`
  - [x] `GET /api/players/{id}`
  - [x] `PATCH /api/players/{id}` (optionnel)
  - [x] `DELETE /api/players/{id}` (optionnel)
- [x] Ajouter controle d'acces ownership (Voter ou check service).

## Phase 3 - Knowledge par Player
- [x] Creer `PlayerItemKnowledgeEntity`:
  - [x] relation `ManyToOne Player`
  - [x] relation `ManyToOne Item`
  - [x] modele presence/absence (une ligne = learned)
  - [x] `learnedAt`
  - [x] timestamps (`learnedAt`)
- [x] Contrainte unique `(player_id, item_id)`.
- [x] Index de perf:
  - [x] `(player_id, item_id)`
  - [x] `(item_id)` si necessaire pour stats.
- [x] API JSON:
  - [x] `PUT /api/players/{playerId}/items/{itemId}/learned` (set true)
  - [x] `DELETE /api/players/{playerId}/items/{itemId}/learned` (set false)
  - [x] `GET /api/players/{playerId}/items?type=...` (liste avec statut learned)

## Phase 4 - Front Twig + UX
- [x] Ecran catalogue items:
  - [x] filtres type (`MISC`/`BOOK`) + recherche texte (`q`)
  - [x] affichage split en 2 blocs: `Legendary Mods` (par rank) et `Minerva` (par liste)
  - [x] badge/rank/lists selon type
  - [x] statut appris pour player courant
- [x] Actions front:
  - [x] toggle learned via checkbox Stimulus + fetch API
  - [x] creation player via formulaire dashboard
  - [x] sync learned inter-listes pour un meme item Minerva (meme id)
  - [x] retour visuel (loading, succes, erreur)
- [x] Selection du player actif (query param).
- [x] Bloc de statistiques dashboard:
  - [x] endpoint `GET /api/players/{id}/stats`,
  - [x] KPI global/type + detail par rank/liste,
  - [x] rafraichissement live apres toggle learned.

## Phase 5 - Qualite et tests
- [x] Tests unitaires:
  - [x] service de toggle learned
  - [x] regles ownership
- [x] Tests fonctionnels:
  - [x] login/logout
  - [x] creation player
  - [x] marquage learned/unlearned
  - [x] interdiction acces cross-user
  - [x] rendu dashboard catalogue auth
  - [x] backoffice traductions items (acces + sauvegarde)
- [ ] Garder `make phpstan` et tests verts a chaque phase.

## Phase 6 - Backoffice traductions
- [x] Route backoffice `GET/POST /admin/translations/items`.
- [x] Filtre par locale (defaut `fr`) et recherche texte.
- [x] Edition des cles `item.misc.*` et `item.book.*` basee sur `items.en.yaml`.
- [x] Sauvegarde dans `translations/items.<locale>.yaml` via `TranslationCatalogWriter`.
- [x] Locale applicative activee via `?locale=` (en/de/fr), conservee en session.
- [x] Fallback traducteur vers `en` quand la locale cible n'est pas complete.
- [x] Textes UI localises via `translations/messages.{fr,en,de}.yaml` (Twig + messages JS).
- [x] Ajouter pagination UI si la grille devient trop lourde.

## Phase 7 - Comptes utilisateurs
- [x] Inscription publique `GET/POST /register` (email + mot de passe + confirmation + CSRF).
- [x] Backoffice utilisateurs `GET /admin/users` (ROLE_ADMIN):
  - [x] toggle actif,
  - [x] toggle role admin,
  - [x] generation lien de reset mot de passe (token temporaire).
- [x] Decision securite: pas de creation d utilisateur via backoffice admin (creation via inscription publique ou commandes console).
- [x] Protection acces `/admin/*` reservee ROLE_ADMIN.
- [x] Journal d audit minimal des actions admin sensibles (toggle actif, toggle admin, generation lien reset).
- [x] Page admin de consultation des logs d audit (`GET /admin/audit-logs`) avec filtres/pagination.
- [x] Cooldown serveur sur generation de lien reset (anti-spam).
- [x] Limite globale de generation de liens reset par admin sur fenetre courte.
- [x] Export CSV des logs d audit avec filtres.
- [x] Commande console de purge des logs d audit anciens.

## Risques / Decisions a figer
- [x] Decider le modele exact learned:
  - [ ] `isLearned` bool (historique simple),
  - [x] ou presence/absence en table pivot.
- [ ] Politique de suppression:
  - [x] supprimer un player supprime son knowledge (`ON DELETE CASCADE`).
- [ ] Pagination catalogue pour eviter surcharge front.

## Definition of Done (MVP)
- [ ] Un admin/dev peut creer un user via commande.
- [ ] Le user se connecte.
- [ ] Le user cree au moins un player.
- [ ] Le user marque learned/unlearned sur items.
- [ ] Le statut est persiste et reaffiche correctement.
- [x] Les traductions FR peuvent etre ajoutees/maj sans re-import JSON.
