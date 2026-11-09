# Discordlink Plugin - AI Coding Instructions

You are working on the **Discordlink** plugin for **Jeedom**. This project bridges Jeedom (PHP) with Discord (Node.js) to enable bi-directional communication.

## 🏗️ Architecture & Core Components

This is a hybrid **PHP/Node.js** application. Understanding the split is crucial:

1.  **Plugin Core (PHP)**
    *   **Main Class**: `core/class/discordlink.class.php`. Handles Jeedom integration, equipment (`eqLogic`), and commands (`cmd`).
    *   **Equipment Model**: Each `eqLogic` object typically represents a **Discord Channel**.
    *   **Webhook Handler**: `core/php/jeediscordlink.php`. Receives HTTP events from the Node.js daemon (incoming messages, interaction responses).
    *   **AJAX Handler**: `core/ajax/discordlink.ajax.php`. Handles UI requests (often for configuration).

2.  **Daemon (Node.js)**
    *   **Entry Point**: `resources/discordlink.js`. Long-running process using `discord.js` v14.
    *   **API**: Exposes a local HTTP server (default port `3466`) to receive commands from PHP.
    *   **Communication**:
        *   **PHP -> Node**: `discordlink.class.php` makes HTTP requests to `http://127.0.0.1:3466`.
        *   **Node -> PHP**: Daemon sends POST requests to `core/php/jeediscordlink.php?apikey=...&name=event`.

## 📂 Key Directories & Files

*   `core/class/discordlink.class.php`: **PRIMARY PHP FILE**. Logic for commands, daemon management, and message sending.
*   `resources/discordlink.js`: **PRIMARY JS FILE**. All Discord interaction logic (Bot, events, gateway).
*   `core/php/jeediscordlink.php`: Entry point for daemon usage reporting back to Jeedom.
*   `plugin_info/info.json`: Plugin metadata (version, ID, requirements).
*   `desktop/php/discordlink.php`: The plugin configuration UI.

## 🧩 Development Patterns & Conventions

### Development Environment (Local)
*   **OS**: Windows 11.
*   **Constraints**: Do **NOT** use Linux-specific terminal commands (like `grep`, `ls`, `cat`, `sudo`) in generated instructions or tool use.
*   **Alternatives**: Use PowerShell equivalents (`Select-String`, `Get-ChildItem`, `Get-Content`) or standard Windows CMD syntax.
*   **Note**: The target runtime is Debian (Jeedom), but the coding/debugging happens on Windows.

### Jeedom Integration (PHP)
*   **Logging**: ALWAYS use `log::add('discordlink', $level, $message)`. Levels: `debug`, `info`, `warning`, `error`.
*   **Database**: Use `eqLogic::byLogicalId` or `config::byKey` to retrieve settings/objects.
*   **Daemon Management**: The class implements `deamon_info`, `deamon_start`, `deamon_stop` (Note: Jeedom legacy typo `deamon` is often used in method names, check context).

### Discord.js Service (Node.js)
*   **Version**: Uses **discord.js v14**. Ensure all code (Bitfields, Intents, Partials) is v14 compatible.
*   **Arguments**: Process arguments: `node discordlink.js <jeedomURL> <token> <logLevel> ...`.
*   **Feedback Loop**: When an event occurs (e.g., message received), use the internal `httpPost` helper to send data back to Jeedom.

### Communication Protocol
*   **PHP to Node**: Use the `com_http` class (Jeedom wrapper) for requests to the daemon.
    ```php
    $requestHttp = new com_http(self::getDaemonBaseURL() . '/endpoint');
    $response = $requestHttp->exec($timeout);
    ```
*   **Node to PHP**: Construction of the return URL:
    ```javascript
    let url = jeedomURL + "/plugins/discordlink/core/php/jeediscordlink.php?apikey=" + pluginKey + "&name=" + name;
    ```

## 🛠️ Common Tasks

*   **Adding a new Bot Command**:
    1.  Implement logic in `resources/discordlink.js` (e.g., `client.on('messageCreate')`).
    2.  If it needs to trigger Jeedom, add a case in `core/php/jeediscordlink.php`.
    3.  If it triggers a scenario, ensure the variables are passed correctly.

*   **Updating Daemon Dependencies**:
    *   Edit `resources/package.json`.
    *   Dependencies are installed during the "dependencies" phase of the plugin installation (handled by `plugin_info/install.php` or Jeedom core).

*   **Debugging**:
    *   **PHP Logs**: View in Jeedom under `discordlink`.
    *   **Daemon Logs**: View in Jeedom under `discordlink_node`. The daemon logs to stdout/stderr which Jeedom captures.
