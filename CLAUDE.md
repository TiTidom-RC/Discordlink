# CLAUDE.md

> Pendant de `.github/copilot-instructions.md` (instructions Copilot) : toute règle commune ajoutée ou modifiée ici est à reporter là-bas, et inversement.

## Contexte

Plugin Jeedom **Discord Link** (`discordlink`) : communication bidirectionnelle entre Jeedom et Discord. Stack hybride PHP + démon Node.js. Chaque équipement (`eqLogic`) représente en général un **salon Discord**.

- `core/class/discordlink.class.php` : fichier PHP principal (commandes, envoi de messages, gestion du démon `deamon_info` / `deamon_start` / `deamon_stop`).
- `resources/discordlink.js` : démon Node.js (`discord.js` v14 + `express`), toute la logique Discord (bot, événements, gateway).
- `core/php/jeediscordlink.php` : reçoit les événements envoyés par le démon (messages reçus, réponses aux interactions).
- `core/ajax/discordlink.ajax.php` : requêtes de l'interface. Interface : `desktop/php/discordlink.php`, `desktop/js/` ; modales emoji et actions rapides dans `desktop/modal/`.
- `core/template/` : widgets et templates d'actions de scénario.

### Communication

- **PHP → Node** : HTTP vers le serveur local du démon, `http://127.0.0.1:3466` par défaut (`SOCKET_PORT`), via la classe Core `com_http` :

  ```php
  $requestHttp = new com_http(self::getDaemonBaseURL() . '/endpoint');
  $response = $requestHttp->exec($timeout);
  ```

- **Node → PHP** : POST vers `<jeedomURL>/plugins/discordlink/core/php/jeediscordlink.php?apikey=<pluginKey>&name=<event>`, via le helper `httpPost` du démon.
- Arguments du démon : `node discordlink.js <jeedomURL> <token> <logLevel> <pluginKey> <activityStatus> <port> <jeedomExtURL>`.

## Environnement

- Dev : Windows 11, PowerShell 7. Utiliser l'outil **PowerShell** (pas Bash) pour les commandes ; les outils Grep/Glob pour chercher dans le code. Toute commande proposée à l'utilisateur est en PowerShell.
- Cible : Jeedom sur Debian 11/12/13, Node.js 22 minimum (`engines` de `resources/package.json`). Aucun Jeedom local : valider par `php -l`, `node --check`, la CI et une relecture, et signaler ce qui reste à tester sur une box.
- Logs : `discordlink` pour le PHP, `discordlink_node` pour le démon (stdout/stderr capturés par Jeedom).

## Workflow Git

- **Commits** : faits exclusivement par l'utilisateur via GitHub Desktop. Ne jamais commiter, pousser ni ouvrir de PR soi-même. En fin de tâche, lister les fichiers modifiés. Quand c'est utile pour garder des commits propres et séparés (par exemple avant d'enchaîner sur une autre tâche), signaler que c'est le bon moment pour committer, en précisant le périmètre du commit.
- Branches : `dev` → PR vers `beta` → stable. La CI tourne sur les PR vers `beta`.
- `core/i18n/*.json` est généré par le workflow `translations.yml` : ne jamais l'éditer à la main.

## Compatibilité PHP

- Le code doit fonctionner en PHP 7.4 (Debian 11), 8.2 (Debian 12) et 8.4 (Debian 13). Debian 11 est en fin de vie : la compatibilité 7.4 est maintenue par choix, le temps que les utilisateurs migrent leur Jeedom vers Debian 12 minimum.
- Aucune syntaxe ni fonction propre à PHP 8.x : `str_starts_with`/`str_ends_with`/`str_contains` (→ `strpos`/`substr`), `match` (→ `switch`), named arguments, `enum`, `readonly`, `fibers`, union types (→ docblock `@param`). Les arrow functions `fn()` sont autorisées (PHP 7.4).
- La CI (`checkPHPCompat.yml`, PR vers `beta`) analyse `core/`, `desktop/` et `plugin_info/` avec PHPStan niveau 0 en PHP 7.4, 8.2 et 8.4.
- Le poste de dev a PHP 8.4 dans le PATH, pour faire apparaître les régressions et les `Deprecated` (installation : `../Documentation/_docs/php-windows-dev.md`). Contrôle après modification, depuis la racine du repo :

  ```powershell
  Get-ChildItem core, desktop, plugin_info -Recurse -Filter *.php |
      ForEach-Object { php -l $_.FullName 2>&1 } |
      Where-Object { $_ -notmatch '^No syntax errors' }
  ```

## Conventions et patterns

- PHP : `log::add('discordlink', $level, $message)` (`debug`, `info`, `warning`, `error`) ; `eqLogic::byLogicalId`, `config::byKey`… Ne jamais réimplémenter une fonction du Core : chercher d'abord dans `../Jeedom/core`. Traductions : `__('...', __FILE__)`, en français uniquement.
- Node : tout le code doit être compatible `discord.js` v14 (Intents, Partials, bitfields).
- Ajouter une commande du bot : logique dans `resources/discordlink.js` ; si elle doit agir sur Jeedom, ajouter le cas correspondant dans `core/php/jeediscordlink.php` ; si elle lance un scénario, vérifier le passage des variables.
- Dépendances du démon : `resources/package.json`, installées par Jeedom à l'étape « dépendances ». Toute nouvelle dépendance après accord de l'utilisateur.

## Documentation utilisateur (repo voisin `../Documentation`, branche `beta`)

- Changelog stable : `../Documentation/fr_FR/DiscordLink/changelog.md` ; beta : `../Documentation/fr_FR/DiscordLink/beta/changelog.md` ; doc : `../Documentation/fr_FR/DiscordLink/index.md`.
- Format et ton : voir `../Documentation/CLAUDE.md`. Commits séparés de ceux de ce repo.
