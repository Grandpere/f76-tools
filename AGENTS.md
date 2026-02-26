# AGENTS

## Documentation workflow
- Maintenir `docs/backlog/current-focus.md` a jour.
- Creer un ticket dans `docs/backlog/tickets/` pour toute nouvelle feature.
- Reporter decisions/corrections dans `docs/ai/memory.md`.
- Mettre a jour `docs/security/*` pour tout changement securite.

## Engineering rules
- Ajouter des tests dans le meme lot que le code.
- Lancer `make phpstan` et `make phpunit-unit` avant commit.
- Ne pas lancer automatiquement les tests fonctionnels: proposer la commande a l utilisateur.

## Dependencies
- Toujours demander accord avant ajout de dependance.
- Expliquer pourquoi la dependance est necessaire et l impact si non ajoutee.
