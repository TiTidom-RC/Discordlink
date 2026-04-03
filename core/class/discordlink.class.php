<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * *************************** Includes ********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class discordlink extends eqLogic {
	/*     * ************************* Attributes ****************************** */

	const DEFAULT_COLOR = '#ff0000';
	const SOCKET_PORT = 3466;
	private static $_daemonBaseURL = null;

	public static function getInfo() {
		$file = __DIR__ . '/../../plugin_info/info.json';
		if (file_exists($file)) {
			try {
				$data = json_decode(file_get_contents($file), true);
				return is_array($data) ? $data : array();
			} catch (Exception $e) {
				log::add('discordlink', 'error', 'Erreur lors de la lecture du fichier info.json : ' . $e->getMessage());
				return array();
			}
		}
		return array();
	}

	public static function templateWidget() {
		$return['action']['message']['message'] =    array(
			'template' => 'message',
			'replace' => array("#_desktop_width_#" => "100", "#_mobile_width_#" => "50", "#title_disable#" => "1", "#message_disable#" => "0")
		);
		$return['action']['message']['embed'] =    array(
			'template' => 'embed',
			'replace' => array("#_desktop_width_#" => "100", "#_mobile_width_#" => "50", "#title_disable#" => "1", "#message_disable#" => "0")
		);
		return $return;
	}

	public static function mcpMetadata() {
		return [
			'sendEmbed' => [
				// Hint actionnable : aide le LLM à choisir sendEmbed vs sendMsg
				'hint' => 'Préférer cette commande à sendMsg pour des notifications visuelles structurées : génère un bloc Discord coloré avec titre, corps, footer, URL et champs dynamiques.',
				'options' => [
					// title : inclus explicitement — identique au champ standard message (idempotent),
					// mais nécessaire pour que tout développeur puisse le déclarer sans connaissance implicite.
					'title'       => ['required' => false, 'type' => 'string', 'hint' => 'Titre principal du bloc embed (affiché en gras en haut du bloc Discord)'],
					// description : nom réel du champ PHP ($_options['description']).
					// PHP accepte aussi "message" en fallback, mais description est le champ prioritaire pour sendEmbed.
					'description' => ['required' => false, 'type' => 'string', 'hint' => 'Corps du bloc embed — champ prioritaire pour sendEmbed (le champ standard "message" est aussi accepté en fallback)'],
					'footer'      => ['required' => false, 'type' => 'string', 'hint' => 'Texte de pied de page du bloc embed'],
					'url'         => ['required' => false, 'type' => 'string', 'hint' => 'URL rendue cliquable sur le titre de l\'embed'],
					'color'       => ['required' => false, 'type' => 'string', 'example' => '#ff0000', 'hint' => 'Couleur de la barre latérale du bloc (format hexadécimal, ex : #ff0000)'],
					'files'       => ['required' => false, 'type' => 'string', 'hint' => 'Chemins absolus de fichiers à joindre, séparés par une virgule (max 4)'],
					'field'       => ['required' => false, 'type' => 'json',   'hint' => 'Champs supplémentaires : tableau JSON [{"name":"Label","value":"Contenu","inline":true}]'],
					'quickaction' => ['required' => false, 'type' => 'string', 'hint' => 'Identifiant(s) d\'action(s) rapide(s) préalablement configurés dans l\'UI du plugin, séparés par une virgule'],
				],
			],
			'sendFile' => [
				'hint' => 'Envoie un ou plusieurs fichiers dans un canal Discord, avec un message textuel optionnel.',
				'options' => [
					'files'   => ['required' => false, 'type' => 'string', 'hint' => 'Chemins absolus de fichiers à envoyer, séparés par une virgule (max 4)'],
					'message' => ['required' => false, 'type' => 'string', 'hint' => 'Message textuel optionnel accompagnant les fichiers'],
				],
			],
		];
	}

	public static function testPlugin($_pluginId) {
		$plugin = plugin::byId($_pluginId);
		return (is_object($plugin) && $plugin->isActive());
	}

	public static function getDaemonBaseURL() {
		if (self::$_daemonBaseURL === null) {
			$host = '127.0.0.1';
			if (jeedom::getHardwareName() == 'docker') {
				$internalAddr = config::byKey('internalAddr');
				if (!empty($internalAddr)) {
					$host = $internalAddr;
				}
			}
			self::$_daemonBaseURL = 'http://' . $host . ':' . config::byKey('socketport', 'discordlink', self::SOCKET_PORT);
		}
		return self::$_daemonBaseURL;
	}

	public static function reloadQuickAction() {
		try {
			log::add('discordlink', 'info', 'Rechargement de la configuration QuickAction...');
			$requestHttp = new com_http(self::getDaemonBaseURL() . '/reloadQuickAction');
			$requestHttp->setNoReportError(true);
			$response = $requestHttp->exec(5);
			$httpCode = $requestHttp->getHttpCode();
			if ($httpCode == 200) {
				log::add('discordlink', 'info', 'Configuration QuickAction rechargée avec succès');
			} else {
				log::add('discordlink', 'warning', 'Rechargement QuickAction : code HTTP inattendu ' . $httpCode);
			}
		} catch (Exception $e) {
			log::add('discordlink', 'warning', 'Impossible de recharger la configuration QuickAction : ' . $e->getMessage());
		}
	}

	public static function getChannels($maxRetries = 5, $delayMs = 2000) {
		$attempt = 0;
		while ($attempt < $maxRetries) {
			try {
				$requestHttp = new com_http(self::getDaemonBaseURL() . '/getchannels');
				$requestHttp->setNoReportError(true);
				$response = $requestHttp->exec(10, 2);
				$httpCode = $requestHttp->getHttpCode();

				if ($httpCode == 200) {
					if ($response !== false && !empty($response)) {
						$channels = json_decode($response, true);
						if (is_array($channels)) {
							log::add('discordlink', 'debug', 'Channels récupérés (' . count($channels) . ') après ' . ($attempt + 1) . ' tentative(s)');
							return $channels;
						}
					}
				} elseif ($httpCode == 503) {
					log::add('discordlink', 'debug', 'Tentative ' . ($attempt + 1) . '/' . $maxRetries . ' : Le démon n\'est pas encore prêt (503 Service Unavailable)');
				} else {
					log::add('discordlink', 'debug', 'Tentative ' . ($attempt + 1) . '/' . $maxRetries . ' : Code HTTP inattendu ' . $httpCode);
				}
			} catch (Exception $e) {
				log::add('discordlink', 'debug', 'Tentative ' . ($attempt + 1) . '/' . $maxRetries . ' échouée: ' . $e->getMessage());
			}

			if ($attempt < $maxRetries - 1) {
				// Backoff progressif : on augmente le délai à chaque tentative
				usleep($delayMs * 1000);
				$delayMs += 2000; // +2s à chaque échec (2s, 4s, 6s, 8s, 10s = 30s total)
			}
			$attempt++;
		}

		log::add('discordlink', 'error', 'Impossible de récupérer les channels depuis le daemon après ' . $maxRetries . ' tentatives');
		return array();
	}

	public static function syncChannels() {
		$channels = static::getChannels();
		if (empty($channels)) return;

		array_walk($channels, function (&$channel) {
			$channel['name'] = discordlink::removeEmoji($channel['name']);
		});

		config::save('channels', $channels, 'discordlink');
	}

	private static function removeEmoji($text) {
		// Remplacement manuel des symboles spéciaux courants qui ont une équivalence texte
		$replacements = array(
			'©' => 'c',
			'®' => 'r',
			'™' => 'tm',
			'‼' => '!!',
			'⁉' => '!?',
		);
		$text = strtr($text, $replacements);

		// Supprime les caractères de liaison ZWJ (U+200D) et les sélecteurs de variation (U+FE0F) des emoji composés
		$text = preg_replace('/[\x{200D}\x{FE0F}]/u', '', $text);

		// Supprime les emoji Unicode tout en préservant les caractères ASCII (0-9, #, *)
		$text = preg_replace('/(?:(?![0-9#*])\p{Emoji})+/u', '', $text);

		// Normalise les espaces multiples (résidu d'emoji en milieu de nom) et retire les bords
		return trim(preg_replace('/ {2,}/', ' ', $text));
	}

	public static function setEmoji($reset = 0) {
		$default = array(
			'motion' => ':walking:',
			'door' => ':door:',
			'windows' => ':framed_picture:',
			'light' => ':bulb:',
			'outlet' => ':electric_plug:',
			'temperature' => ':thermometer:',
			'humidity' => ':droplet:',
			'luminosity' => ':sunny:',
			'power' => ':cloud_with_lightning:',
			'security' => ':rotating_light:',
			'shutter' => ':beginner:',
			'deamon_ok' => ':green_circle:',
			'deamon_nok' => ':red_circle:',
			'dep_ok' => ':green_circle:',
			'dep_progress' => ':orange_circle:',
			'dep_nok' => ':red_circle:',
			'batterie_ok' => ':green_circle:',
			'batterie_progress' => ':orange_circle:',
			'batterie_nok' => ':red_circle:',
			'lastUser_warning' => ':warning:',
			'lastUser_mag_right' => ':mag_right:',
			'lastUser_mag' => ':mag:',
			'lastUser_check' => ':white_check_mark:',
			'lastUser_internet' => ':globe_with_meridians:',
			'lastUser_connected' => ':green_circle:',
			'lastUser_disconnected' => ':red_circle:',
			'lastUser_icon' => ':busts_in_silhouette:'
		);

		if ($reset == 1) {
			// Reset complet : on force les valeurs par défaut
			$emojiArray = $default;
		} else {
			// Récupération des emojis existants et fusion avec les nouveaux
			$existing = config::byKey('emoji', 'discordlink', array());
			$emojiArray = array_merge($default, is_array($existing) ? $existing : array());
		}

		config::save('emoji', $emojiArray, 'discordlink');
	}

	public static function emojiConvert($_text): string {
		$_returnText = '';
		$textParts = explode(" ", $_text);
		foreach ($textParts as $value) {
			if (substr($value, 0, 4) === "emo_") {
				// getIcon() inclut déjà un espace séparateur, pas besoin d'en rajouter
				$_returnText .= discordlink::getIcon(str_replace("emo_", "", $value));
			} else {
				$_returnText .= $value . " ";
			}
		}
		return rtrim($_returnText);
	}

	private static function executeCronIfDue($eqLogic, $cronExpr, $cmdLogicId, $debugLabel, $_options) {
		if (empty($cronExpr)) {
			log::add('discordlink', 'warning', $debugLabel . ' pour ' . $eqLogic->getName() . ' : activé mais aucun cron configuré');
			return;
		}

		if (cronIsDue($cronExpr)) {
			log::add('discordlink', 'info', $debugLabel . ' pour ' . $eqLogic->getName() . ' (cron: ' . $cronExpr . ') - Exécution');
			$cmd = $eqLogic->getCmd('action', $cmdLogicId);
			if (is_object($cmd)) {
				$cmd->execCmd($_options);
			} else {
				log::add('discordlink', 'warning', $debugLabel . ' pour ' . $eqLogic->getName() . ' : commande ' . $cmdLogicId . ' introuvable');
			}
		} else {
			log::add('discordlink', 'debug', $debugLabel . ' pour ' . $eqLogic->getName() . ' (cron: ' . $cronExpr . ') - Non dû à cette date');
		}
	}

	/**
	 * Exécute les vérifications planifiées pour tous les équipements
	 * Vérifie les crons personnalisés (démons, dépendances, connexions)
	 * et exécute les notifications Discord si les conditions sont remplies
	 */
	public static function runScheduledChecks() {
		$eqLogics = eqLogic::byType('discordlink');
		if (empty($eqLogics)) {
			return;
		}

		// Sans démon actif, impossible d'envoyer des messages Discord — on abandonne silencieusement
		if (static::deamon_info()['state'] !== 'ok') {
			log::add('discordlink', 'debug', 'runScheduledChecks() : démon non actif, vérifications planifiées ignorées');
			return;
		}

		$options = ['cron' => true];

		foreach ($eqLogics as $eqLogic) {
			if (!$eqLogic->getIsEnable()) continue;

			// Vérification démon
			if ((bool)$eqLogic->getConfiguration('daemonCheck', 0)) {
				static::executeCronIfDue($eqLogic, $eqLogic->getConfiguration('autoRefreshDaemon'), 'daemonInfo', 'DaemonCheck', $options);
			}

			// Vérification dépendances
			if ((bool)$eqLogic->getConfiguration('dependencyCheck', 0)) {
				static::executeCronIfDue($eqLogic, $eqLogic->getConfiguration('autoRefreshDependency'), 'dependencyInfo', 'DependencyCheck', $options);
			}

			// Vérification connexions utilisateurs
			if ((bool)$eqLogic->getConfiguration('connectionCheck', 0)) {
				$cmd = $eqLogic->getCmd('action', 'lastUser');
				if (is_object($cmd)) {
					$cmd->execCmd($options);
				} else {
					log::add('discordlink', 'warning', 'runScheduledChecks() : ' . $eqLogic->getName() . ' - commande lastUser introuvable');
				}
			}
		}
	}
	/*     * *********************** Static Methods *************************** */

	/*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
	 */
	public static function cron() {
		static::runScheduledChecks();
	}

	/*
     * Fonction exécutée automatiquement tous les jours par Jeedom*/
	public static function cronDaily() {
		$eqLogics = eqLogic::byType('discordlink');
		foreach ($eqLogics as $eqLogic) {
			if (!$eqLogic->getIsEnable()) continue;
			if (!(bool)$eqLogic->getConfiguration('clearChannel', 0)) continue;

			$cmd = $eqLogic->getCmd('action', 'deleteMessage');
			if (!is_object($cmd)) continue;

			try {
				log::add('discordlink', 'info', 'Nettoyage quotidien du channel pour ' . $eqLogic->getName());
				$cmd->execCmd();
			} catch (Exception $e) {
				log::add('discordlink', 'error', 'Erreur lors du nettoyage quotidien pour ' . $eqLogic->getName() . ': ' . $e->getMessage());
			}
		}
	}

	/*     * ********************* Instance Methods ************************* */

	// Dépendances gérées nativement via packages.json (Node.js géré par Jeedom Core)


	public static function deamon_info() {
		$return = array(
			'log' => 'discordlink_node',
			'state' => 'nok',
			'launchable' => 'nok'
		);

		// Vérifier si le serveur HTTP répond sur le port configuré
		try {
			$requestHttp = new com_http(self::getDaemonBaseURL() . '/heartbeat');
			$requestHttp->setNoReportError(true);
			$response = $requestHttp->exec(2, 1); // Timeout rapide: 2s
			if ($response !== false) {
				$return['state'] = 'ok';
				$json = json_decode($response, true);
				if (!is_array($json) || !isset($json['status']) || $json['status'] != 'ok') {
					log::add('discordlink', 'warning', 'Heartbeat réponse inattendue : ' . $response);
				}
			}
		} catch (Exception $e) {
			// Le serveur ne répond pas, daemon non actif
			$return['state'] = 'nok';
		}

		$token = config::byKey('Token', 'discordlink');
		if (!empty($token) && $token !== 'null') {
			$return['launchable'] = 'ok';
		} else {
			$return['launchable_message'] = 'TOKEN DISCORD ABSENT';
		}

		return $return;
	}

	public static function deamon_start($_debug = false) {
		static::deamon_stop();

		if (static::deamon_info()['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}

		log::add('discordlink', 'info', 'Lancement du démon Discord Link');

		$apiKey = jeedom::getApiKey('discordlink');
		$cmd = sprintf(
			'nice -n 19 node %s/discordlink.js %s %s %s %s %s %s %s %s',
			escapeshellarg(realpath(dirname(__FILE__) . '/../../resources')),
			escapeshellarg(network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp')),
			escapeshellarg(config::byKey('Token', 'discordlink')),
			escapeshellarg(log::getLogLevel('discordlink')),
			escapeshellarg(network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/discordlink/core/api/jeeDiscordlink.php?apikey=' . $apiKey),
			escapeshellarg($apiKey),
			escapeshellarg(config::byKey('joueA', 'discordlink', 'Travailler main dans la main avec votre Jeedom')),
			escapeshellarg(config::byKey('socketport', 'discordlink', self::SOCKET_PORT)),
			escapeshellarg(network::getNetworkAccess('external'))
		);

		log::add('discordlink', 'debug', 'Commande du démon Discord Link : ' . $cmd);

		$fullCmd = sprintf(
			'NODE_ENV=production nohup %s >> %s 2>&1 & echo $!',
			$cmd,
			escapeshellarg(log::getPathToLog('discordlink_node'))
		);

		$pid = trim(shell_exec($fullCmd));
		if (empty($pid) || !is_numeric($pid)) {
			log::add('discordlink', 'error', 'Échec du lancement du démon (PID invalide)');
			return false;
		}

		log::add('discordlink', 'debug', 'Démon lancé avec PID : ' . $pid);

		for ($i = 0; $i < 30; $i++) {
			if (static::deamon_info()['state'] == 'ok') {
				message::removeAll('discordlink', 'unableStartDeamon');
				log::add('discordlink', 'info', 'Démon Discord Link lancé');
				static::updateObject();
				static::syncChannels();
				return true;
			}
			sleep(1);
		}

		log::add('discordlink', 'error', 'Impossible de lancer le démon Discord Link, vérifiez le port', 'unableStartDeamon');
		return false;
	}

	public static function deamon_stop() {
		log::add('discordlink', 'info', 'Arrêt du démon Discord Link');

		// Arrêt gracieux via API HTTP
		try {
			$requestHttp = new com_http(self::getDaemonBaseURL() . '/stop');
			$requestHttp->setNoReportError(true);
			$requestHttp->setAllowEmptyReponse(true);
			$requestHttp->exec(1, 1);
		} catch (Exception $e) {
			log::add('discordlink', 'error', 'Erreur arrêt du démon : ' . $e->getMessage());
		}

		// Attente dynamique de l'arrêt du processus (max 3s)
		// On précise "node" et on utilise l'astuce [s] pour que pgrep ne matche pas sa propre commande
		$processPattern = escapeshellarg('node .*discordlink.j[s]');
		for ($i = 0; $i < 30; $i++) {
			$pidCount = (int)trim(shell_exec('pgrep -f ' . $processPattern . ' 2>/dev/null | wc -l'));
			if ($pidCount == 0) {
				log::add('discordlink', 'info', 'Démon arrêté avec succès');
				break;
			}
			usleep(100000); // Pause de 0.1s
		}

		// Vérification et kill forcé si le processus est toujours actif
		if ($pidCount > 0) {
			log::add('discordlink', 'warning', 'Le Démon est toujours actif, envoi de SIGTERM');
			// SIGTERM d'abord
			exec('pkill -f ' . $processPattern . ' 2>&1');

			// Attente dynamique de l'effet du SIGTERM (max 1s)
			for ($i = 0; $i < 10; $i++) {
				$pidCount = (int)trim(shell_exec('pgrep -f ' . $processPattern . ' 2>/dev/null | wc -l'));
				if ($pidCount == 0) {
					log::add('discordlink', 'info', 'Démon arrêté suite au SIGTERM');
					break;
				}
				usleep(100000); // Pause de 0.1s
			}

			// Vérifier si toujours actif, puis SIGKILL
			if ($pidCount > 0) {
				log::add('discordlink', 'warning', 'Le Démon résiste au SIGTERM, envoi de SIGKILL');
				exec('pkill -9 -f ' . $processPattern . ' 2>&1');
			}
		}
	}

	public function preInsert() {
		$this->setConfiguration('defaultColor', self::DEFAULT_COLOR);
		$this->setConfiguration('daysToKeep', 2);
		$this->setIsEnable(1);
	}

	public function postInsert() {
	}

	public function preSave() {
		$channel = $this->getConfiguration('channelId');
		if (!empty($channel) && $channel != 'null') {
			$this->setLogicalId($channel);
			log::add('discordlink', 'debug', 'preSave - setLogicalId: ' . $channel);
		} else {
			log::add('discordlink', 'debug', 'preSave - channelId vide, logicalId conservé : ' . $this->getLogicalId());
		}
	}

	public static function getIcon($_icon) {
		$emojiArray = config::byKey('emoji', 'discordlink', array());
		$icon = isset($emojiArray[$_icon]) && !empty($emojiArray[$_icon]) ? $emojiArray[$_icon] : static::addEmoji($_icon);
		return $icon . ' ';
	}

	public static function addEmoji($_icon, $_emoji = null) {
		$emojiArray = config::byKey('emoji', 'discordlink', array());
		$emojiArray[$_icon] = $_emoji ?? ':interrobang:';
		config::save('emoji', $emojiArray, 'discordlink');
		return $emojiArray[$_icon];
	}

	public static function createCmd() {

		$eqLogics = eqLogic::byType('discordlink');
		foreach ($eqLogics as $eqLogic) {

			$commandsConfig = array(
				'sendMsg' => array('requiredPlugin' => '0', 'label' => 'Envoi message', 'type' => 'action', 'subType' => 'message', 'request' => 'sendMsg', 'visible' => 1, 'template' => 'discordlink::message'),
				'sendMsgTTS' => array('requiredPlugin' => '0', 'label' => 'Envoi message TTS', 'type' => 'action', 'subType' => 'message', 'request' => 'sendMsgTTS', 'visible' => 1, 'template' => 'discordlink::message'),
				'sendEmbed' => array('requiredPlugin' => '0', 'label' => 'Envoi message évolué', 'type' => 'action', 'subType' => 'message', 'request' => 'sendEmbed', 'visible' => 1, 'template' => 'discordlink::embed'),
				'sendFile' => array('requiredPlugin' => '0', 'label' => 'Envoi fichier', 'type' => 'action', 'subType' => 'message', 'request' => 'sendFile', 'visible' => 0),
				'deleteMessage' => array('requiredPlugin' => '0', 'label' => 'Supprime les messages du channel', 'type' => 'action', 'subType' => 'other', 'request' => 'deleteMessage', 'visible' => 0),
				'daemonInfo' => array('requiredPlugin' => '0', 'label' => 'Etat des démons', 'type' => 'action', 'subType' => 'other', 'request' => 'daemonInfo', 'visible' => 1),
				'dependencyInfo' => array('requiredPlugin' => '0', 'label' => 'Etat des dépendances', 'type' => 'action', 'subType' => 'other', 'request' => 'dependencyInfo', 'visible' => 1),
				'globalSummary' => array('requiredPlugin' => '0', 'label' => 'Résumé général', 'type' => 'action', 'subType' => 'other', 'request' => 'globalSummary', 'visible' => 1),
				'batteryInfo' => array('requiredPlugin' => '0', 'label' => 'Résumé des batteries', 'type' => 'action', 'subType' => 'other', 'request' => 'batteryInfo', 'visible' => 1),
				'messageCenter' => array('requiredPlugin' => '0', 'label' => 'Centre de messages', 'type' => 'action', 'subType' => 'other', 'request' => 'messageCenter', 'visible' => 1),
				'lastUser' => array('requiredPlugin' => '0', 'label' => 'Dernière Connexion utilisateur', 'type' => 'action', 'subType' => 'other', 'request' => 'lastUser', 'visible' => 1),
				'objectSummary' => array('requiredPlugin' => '0', 'label' => 'Résumé par objet', 'type' => 'action', 'subType' => 'select', 'request' => 'objectSummary', 'visible' => 1),
				'lastMessage' => array('requiredPlugin' => '0', 'label' => 'Dernier message', 'type' => 'info', 'subType' => 'string', 'visible' => 1),
				'previousMessage1' => array('requiredPlugin' => '0', 'label' => 'Avant dernier message', 'type' => 'info', 'subType' => 'string', 'visible' => 1),
				'previousMessage2' => array('requiredPlugin' => '0', 'label' => 'Avant avant dernier message', 'type' => 'info', 'subType' => 'string', 'visible' => 1)
			);
			$order = 0;
			foreach ($commandsConfig as $cmdKey => $cmdConfig) {
				// Vérifier si le plugin requis est actif ("0" = pas de dépendance)
				if ($cmdConfig['requiredPlugin'] == "0" || discordlink::testPlugin($cmdConfig['requiredPlugin'])) {
					$cmd = $eqLogic->getCmd(null, $cmdKey);
					if (!is_object($cmd)) {
						$cmd = new discordlinkCmd();
						$cmd->setName($cmdConfig['label']);
						$cmd->setType($cmdConfig['type']);
						$cmd->setSubType($cmdConfig['subType']);
						$cmd->setIsVisible($cmdConfig['visible']);
					}
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId($cmdKey);
					if ($cmdConfig['type'] == "action") {
						$cmd->setConfiguration('request', $cmdConfig['request']);
						$cmd->setConfiguration('value', '');
					}

					$cmd->setDisplay('generic_type', 'GENERIC_INFO');
					if (!empty($cmdConfig['template'])) {
						$cmd->setTemplate("dashboard", $cmdConfig['template']);
						$cmd->setTemplate("mobile", $cmdConfig['template']);
					}
					$cmd->setOrder($order);
					$cmd->setDisplay('message_placeholder', 'Message à envoyer sur Discord');
					if (in_array($cmdKey, array('lastMessage', 'previousMessage1', 'previousMessage2'))) {
						$cmd->setDisplay('forceReturnLineBefore', true);
					}
					try {
						$cmd->save();
					} catch (\Throwable $th) {
						log::add('discordlink', 'error', 'Erreur lors de la sauvegarde de la commande ' . $cmdConfig['label'] . ' (' . $cmdKey . ') pour ' . $eqLogic->getName() . ' : ' . $th->getMessage());
					}
					$order++;
				}
			}
		}
	}

	public function getDefaultColor() {
		return $this->getConfiguration('defaultColor', self::DEFAULT_COLOR);
	}

	public function postSave() {
		static::createCmd();
		static::updateObject();
	}

	public function preUpdate() {
	}

	public function postUpdate() {
		self::createCmd();
	}

	public function preRemove() {
	}

	public function postRemove() {
	}

	public static function updateObject() {
		$objects = jeeObject::all();
		if (empty($objects)) return;

		$listValue = implode(';', array_map(function ($object) {
			return $object->getId() . '|' . $object->getName();
		}, $objects));

		$eqLogics = eqLogic::byType('discordlink');
		foreach ($eqLogics as $eqLogic) {
			$cmd = $eqLogic->getCmd(null, 'objectSummary');
			if (is_object($cmd)) {
				$cmd->setConfiguration('listValue', $listValue);
				$cmd->save();
			}
		}
	}

	public static function getLastUserConnections() {
		$message = "";
		$onlineCount = 0;
		$daysBeforeUserRemoval = 61;
		$hasCronActivity = false;
		$cronInterval = 65;
		$timeNow = date("Y-m-d H:i:s");
		$maxLine = log::getConfig('maxLineLog');
		// Récupération du niveau de log du log Connection :: 100 = debug | 200 = info | 300 = warning | 400 = error (default) | 1000 = none
		$level = log::getLogLevel('connection');
		$levelName = log::convertLogLevel($level);

		// Récupération des emojis
		$emojiWarning = discordlink::getIcon("lastUser_warning");
		$emojiMagRight = discordlink::getIcon("lastUser_mag_right");
		$emojiMag = discordlink::getIcon("lastUser_mag");
		$emojiCheck = discordlink::getIcon("lastUser_check");
		$emojiInternet = discordlink::getIcon("lastUser_internet");
		$emojiConnected = discordlink::getIcon("lastUser_connected");
		$emojiDisconnected = discordlink::getIcon("lastUser_disconnected");
		$emojiIcon = discordlink::getIcon("lastUser_icon");


		if ($level > 200) {
			$logLevelWarning = "\n" . "\n" . $emojiWarning . "Plus d'informations ? " . $emojiWarning . "\n" . "veuillez mettre le log **connection** sur **info** dans *Configuration/Logs* (niveau actuel : **" . $levelName . "**)";
		} else {
			$logLevelWarning = "";
		}
		$offlineDelay = 10;
		$timestampNow = strtotime($timeNow);
		$connectedUserNames = array();
		$connectedUserDates = array();
		$connectedUserStatuses = array();

		foreach (user::all() as $user) {
			$lastDate = $user->getOptions('lastConnection');
			if (empty($lastDate)) {
				$lastDate = "1970-01-01 00:00:00";
			}

			$status = 'hors ligne';
			if (($timestampNow - strtotime($lastDate)) < ($offlineDelay * 60)) {
				$status = 'en ligne';
			}

			// Stockage dans les tableaux pour usage ultérieur
			$connectedUserNames[] = $user->getLogin();
			$connectedUserDates[] = $lastDate;
			$connectedUserStatuses[] = $status;
		}

		// Récupération des lignes du log Connection
		$delta = log::getDelta('connection', 0, '', false, false, 0, $maxLine);
		$connectionLogs = array();
		if (isset($delta['logText']) && !empty($delta['logText'])) {
			$lines = explode("\n", $delta['logText']);
			$connectionLogs = array_reverse(array_filter($lines));
		}

		$logUserIndex = 0;
		$lastLogConnectionName = '';
		if (is_array($connectionLogs)) {
			foreach ($connectionLogs as $value) {
				// Format attendu: [2026-01-16 18:46:50] INFO  Connexion de l'utilisateur par clef : admin
				if (preg_match('/^\[(.*?)\]\s+INFO\s+(.*)\s:\s(.*)$/', $value, $matches)) {
					$currentLogDate = $matches[1];
					$currentLogMsg = $matches[2];
					$currentLogUser = strtolower(trim($matches[3]));

					// Vérification de la date
					if (strtotime($timeNow) - strtotime($currentLogDate) > $cronInterval) {
						if ($logUserIndex == 0) {
							$message = "\n" . "**Pas de connexion** ces **" . $cronInterval . "** dernières secondes !";
						}
						break;
					}

					$logUserIndex++;
					$connectionLogDates[$logUserIndex] = $currentLogDate;
					$connectionLogNames[$logUserIndex] = $currentLogUser;

					// Détermination du type de connexion
					if (strpos($currentLogMsg, 'clef') !== false) {
						$connectionLogTypes[$logUserIndex] = 'clef';
					} elseif (strpos($currentLogMsg, 'API') !== false) {
						$connectionLogTypes[$logUserIndex] = 'api';
					} else {
						$connectionLogTypes[$logUserIndex] = 'navigateur';
					}

					if ($logUserIndex == 1) {
						$message .= "\n" . $emojiMagRight . "__Récapitulatif de ces " . $cronInterval . " dernières secondes :__ " . $emojiMag;
					}

					$onlineCount++; // Comptage d'activité : événement de connexion dans l'intervalle cron
					$message .= "\n" . $emojiCheck . "**" . $connectionLogNames[$logUserIndex] . "** s'est connecté par **" . $connectionLogTypes[$logUserIndex] . "** à **" . date("H", strtotime($connectionLogDates[$logUserIndex])) . "h" . date("i", strtotime($connectionLogDates[$logUserIndex])) . "**";

					$hasCronActivity = true;

					// Évite les doublons consécutifs dans le log pour le même utilisateur
					if ($lastLogConnectionName === $connectionLogNames[$logUserIndex]) {
						continue;
					}
					$lastLogConnectionName = $connectionLogNames[$logUserIndex];

					// Mise à jour du statut des utilisateurs
					$foundCount = 0;
					foreach ($connectedUserNames as $key => $userName) {
						if ($connectionLogNames[$logUserIndex] == $userName) {
							$foundCount++;
							if ($connectedUserStatuses[$key] == 'hors ligne') {
								$connectedUserDates[$key] = $connectionLogDates[$logUserIndex];
								$connectedUserStatuses[$key] = 'en ligne';
							}
						}
					}

					// Ajout nouvel utilisateur si non trouvé
					if ($foundCount == 0) {
						$connectedUserNames[] = $connectionLogNames[$logUserIndex];
						$connectedUserDates[] = $connectionLogDates[$logUserIndex];
						$connectedUserStatuses[] = 'en ligne';
						$connectedUserIPs[] = ''; // Initialiser IP pour éviter warning plus tard
					}
				}
			}
		}

		$sessions = listSession();

		$message .= "\n" . "\n" . $emojiMagRight . "__Récapitulatif des sessions actuelles :__ " . $emojiMag;
		// Parcours des sessions pour vérifier le statut et le nombre de sessions
		// Note : $connectedUserStatuses et $connectedUserIPs sont réinitialisés à chaque itération :
		// les sessions actives font autorité sur les données BDD/logs pour l'affichage final
		foreach ($connectedUserNames as $userIndex => $userName) {
			$foundCount = 0;
			$connectedUserStatuses[$userIndex] = 'hors ligne';
			$connectedUserIPs[$userIndex] = '';

			foreach ($sessions as $id => $session) {
				$userDelay = strtotime(date("Y-m-d H:i:s")) - strtotime($session['datetime']);

				if ($userName == $session['login']) {
					if ($userDelay < $offlineDelay * 60) {
						$foundCount++;
						$onlineCount++; // Comptage d'activité : session active trouvée
						$connectedUserStatuses[$userIndex] = 'en ligne';
						$connectedUserIPs[$userIndex] .= "\n" . "-> " . $emojiInternet . " IP : " . $session['ip'];
					}
				}
			}
			$connectTimestamp = strtotime($connectedUserDates[$userIndex]);
			if (date("Y-m-d", $connectTimestamp) == date("Y-m-d", $timestampNow)) {
				$date = date("H\hi", $connectTimestamp);
			} else {
				$date = date_fr(date("l d F Y", $connectTimestamp)) . "** à **" . date("H\hi", $connectTimestamp);
			}
			if ($foundCount > 0) {
				$message .= "\n" . $emojiConnected . " **" . $userName . "** est **en ligne** depuis **" . $date . "**";
				$message .= $connectedUserIPs[$userIndex];
			} else {
				if (strtotime($timeNow) - strtotime($connectedUserDates[$userIndex]) < ($daysBeforeUserRemoval * 24 * 60 * 60)) {
					$message .= "\n" . $emojiDisconnected . " **" . $userName . "** est **hors ligne** (dernière connexion **" . $date . "**)";
				}
			}
		}

		// Préparation des tags de notification
		$title = $emojiIcon . 'CONNEXIONS ' . $emojiIcon;
		return array(
			'title' => $title,
			'message' => $message . $logLevelWarning,
			'nbEnLigne' => $onlineCount,
			'cronOk' => $hasCronActivity
		);
	}

	public static function createQuickActionFile() {
		$path = dirname(__FILE__) . '/../../data/quickaction.json';
		file_put_contents($path, '{}');
	}


	public static function getConfigForCommunity() {

		$pluginType = self::isBeta(true);
		$info = discordlink::getInfo();

		$pluginInfo = '<b>Version </b> : ' . $info['pluginVersion'] . ' (' . $pluginType  . ')<br/>';

		$pluginInfo .= '<b>Version OS</b> : ' .  system::getDistrib() . ' ' . system::getOsVersion() . '<br/>';

		$pluginInfo .= '<b>Version PHP</b> : ' . phpversion() . '<br/>';

		$pluginInfo = '<br/>```<br/>' . str_replace(array('<b>', '</b>', '&nbsp;'), array('', '', ' '), $pluginInfo) . '<br/>```<br/>';

		return $pluginInfo;
	}

	public static function isBeta($text = false) {
		$plugin = plugin::byId(__CLASS__);
		$update = $plugin->getUpdate();
		$isBeta = false;
		if (is_object($update)) {
			$version = $update->getConfiguration('version');
			$isBeta = ($version && $version != 'stable');
		}

		if ($text) {
			return $isBeta ? 'beta' : 'stable';
		}
		return $isBeta;
	}

	public static function getQuickActionFileContent() {
		$quickActionFile = dirname(__FILE__) . '/../../data/quickaction.json';
		$quickActionData = array();
		if (file_exists($quickActionFile)) {
			$quickActionData = json_decode(file_get_contents($quickActionFile), true);
		}
		return $quickActionData;
	}

	public static function getQuickActionOptions() {
		$quickActionData = self::getQuickActionFileContent();
		$options = array();
		if (is_array($quickActionData)) {
			foreach ($quickActionData as $item) {
				if (isset($item['label']) && isset($item['key'])) {
					$options[] = array('id' => $item['key'], 'name' => $item['label']);
				}
			}
		}
		return $options;
	}

	/*     * ********************** Getter Setter *************************** */
}
class discordlinkCmd extends cmd {

	/*     * ************************* Attributes ****************************** */

	/*     * *********************** Static Methods *************************** */


	/*     * ********************* Instance Methods ************************* */

	/*
		 * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
		  public function dontRemoveCmd() {
		  return true;
		  }
		 */

	public function execute($_options = null) {
		if ($this->getLogicalId() == 'refresh') {
			$this->getEqLogic()->refresh();
			return;
		}

		$requestData = $this->buildRequest($_options);

		if (!is_array($requestData)) {
			return true;
		}

		// Vérification rapide avant d'engager le timeout HTTP
		if (discordlink::deamon_info()['state'] !== 'ok') {
			log::add('discordlink', 'warning', '[' . $this->getEqLogic()->getName() . '][' . $this->getLogicalId() . '] Commande ignorée : le démon n\'est pas actif.');
			return true;
		}

		$endpoint = $requestData['endpoint'];
		$payload = $requestData['payload'];
		$method = $requestData['method'] ?? 'POST';

		$url = discordlink::getDaemonBaseURL() . $endpoint;
		log::add('discordlink', 'debug', '[' . $this->getEqLogic()->getName() . '][' . $this->getLogicalId() . '] Envoi requête ' . $method . ' : ' . $url);

		$request_http = new com_http($url);
		$request_http->setAllowEmptyReponse(true);

		if ($method === 'POST') {
			$request_http->setPost(json_encode($payload));
			$request_http->setHeader(array('Content-Type: application/json'));
		}

		$result = $request_http->exec(6, 1);
		if (!$result) {
			log::add('discordlink', 'error', '[' . $this->getEqLogic()->getName() . '][' . $this->getLogicalId() . '] Le démon ne répond pas. Vérifiez son état.');
			return true;
		}
		return true;
	}

	private function buildRequest($_options = array()) {
		if ($this->getType() != 'action') return null;

		$command = $this->getLogicalId();

		$commandMap = array(
			'sendMsg' => 'buildMessageRequest',
			'sendMsgTTS' => 'buildMessageRequest',
			'sendEmbed' => 'buildEmbedRequest',
			'sendFile' => 'buildFileRequest',
			'daemonInfo' => 'buildDaemonInfo',
			'dependencyInfo' => 'buildDependencyInfo',
			'globalSummary' => 'buildGlobalSummary',
			'batteryInfo' => 'buildGlobalBattery',
			'objectSummary' => 'buildObjectSummary',
			'messageCenter' => 'buildMessageCenter',
			'lastUser' => 'buildLastUser',
			'deleteMessage' => 'buildClearChannelRequest'
		);

		if (isset($commandMap[$command])) {
			if (method_exists($this, $commandMap[$command])) {
				return $this->{$commandMap[$command]}($_options);
			}
		}

		return null;
	}

	/**
	 * Traitement unifié du texte pour les messages et embeds Discord.
	 *
	 * Pipeline :
	 * 1. Remplacement des tags Jeedom (#[...]#)
	 * 2. Décodage du texte aléatoire ({...})
	 * 3. Conversion des emojis personnalisés (emo_...) — uniquement si $_supportMarkdown est vrai
	 * 4. Remplacement des sauts de ligne (| -> \n)
	 *
	 * @param string $_text Le texte à traiter
	 * @param bool $_supportMarkdown Si le champ cible supporte le Markdown/les emojis personnalisés (défaut : false)
	 * @return string
	 */
	private function formatDiscordText($_text, $_supportMarkdown = false) {
		if (empty($_text)) return $_text;

		// 1. Remplacement des tags
		$text = scenarioExpression::setTags($_text);

		// 2. Décodage du texte aléatoire
		$text = self::decodeRandomText($text);

		// 3. Conversion des emojis (uniquement si markdown supporté)
		if ($_supportMarkdown) {
			$text = discordlink::emojiConvert($text);
		}

		// 4. Remplacement des sauts de ligne
		$text = str_replace('|', "\n", $text);

		return $text;
	}

	private function buildMessageRequest($_options = array(), $default = "Une erreur est survenue") {
		$message = isset($_options['message']) && $_options['message'] != '' ? $_options['message'] : $default;

		$message = $this->formatDiscordText($message, true);

		$channelID = $this->getEqLogic()->getConfiguration('channelId');

		$logicalId = $this->getLogicalId();
		$endpoint = ($logicalId === 'sendMsgTTS') ? '/sendMsgTTS' : '/sendMsg';

		return array(
			'endpoint' => $endpoint,
			'method' => 'POST',
			'payload' => array(
				'channelID' => $channelID,
				'message' => $message
			)
		);
	}

	private function buildFileRequest($_options = array(), $default = "Chemin du fichier non spécifié") {
		$channelID = $this->getEqLogic()->getConfiguration('channelId');

		// Traitement des fichiers en entrée
		$rawFiles = "";
		if (isset($_options['files'])) {
			$rawFiles = is_array($_options['files']) ? implode(',', $_options['files']) : (string)$_options['files'];
			$rawFiles = scenarioExpression::setTags($rawFiles);
		}

		$message = isset($_options['message']) ? $_options['message'] : "";

		$message = $this->formatDiscordText($message, true);

		if (empty($rawFiles) && empty($message)) {
			log::add('discordlink', 'warning', 'sendFile : Aucun fichier ni message spécifié.');
			$message = $default;
		} elseif (empty($rawFiles)) {
			log::add('discordlink', 'info', 'sendFile : Aucun fichier spécifié, envoi du message seul.');
		}

		// Construction du tableau de fichiers
		$filesArray = [];
		if (!empty($rawFiles)) {
			$splits = explode(',', $rawFiles);
			foreach ($splits as $f) {
				$cleanPath = trim($f);
				if (!empty($cleanPath)) {
					$filesArray[] = $cleanPath;
				}
			}
		}

		return array(
			'endpoint' => '/sendFile',
			'method' => 'POST',
			'payload' => array(
				'channelID' => $channelID,
				'message' => $message,
				'files' => $filesArray
			)
		);
	}

	private function buildEmbedRequest($_options = array(), $default = "Une erreur est survenue") {

		// Initialisation de toutes les variables
		$title = "";
		$url = "";
		$description = "";
		$footer = "";
		$color = "";
		$fields = [];
		$timeout = 0;
		$answerCount = "";
		$quickaction = [];
		$files = [];

		/** @var discordlink $eqLogic */
		$eqLogic = $this->getEqLogic();
		$defaultColor = $eqLogic->getDefaultColor();

		if (isset($_options['answer'])) {
			if (("" != ($_options['title']))) $title = $_options['title'];
			$color = $defaultColor;

			// Ajout du Footer pour indiquer une requête Jeedom Ask
			$footer = 'Jeedom Ask';

			if (isset($_options['answer'][0]) && $_options['answer'][0] != "") {
				$answer = $_options['answer'];
				$timeout = $_options['timeout'];
				$description = "";

				$choices = [
					":regional_indicator_a:",
					":regional_indicator_b:",
					":regional_indicator_c:",
					":regional_indicator_d:",
					":regional_indicator_e:",
					":regional_indicator_f:",
					":regional_indicator_g:",
					":regional_indicator_h:",
					":regional_indicator_i:",
					":regional_indicator_j:",
					":regional_indicator_k:",
					":regional_indicator_l:",
					":regional_indicator_m:",
					":regional_indicator_n:",
					":regional_indicator_o:",
					":regional_indicator_p:",
					":regional_indicator_q:",
					":regional_indicator_r:",
					":regional_indicator_s:",
					":regional_indicator_t:",
					":regional_indicator_u:",
					":regional_indicator_v:",
					":regional_indicator_w:",
					":regional_indicator_x:",
					":regional_indicator_y:",
					":regional_indicator_z:"
				];

				$urlList = [];
				for ($a = 0; $a < count($answer); $a++) {
					$description .= $choices[$a] . " : " . $answer[$a] . "\n";
					$urlList[] = $answer[$a];
				}
				// Tableau passé directement au démon Node.js (mode ASK)
				$url = $urlList;
				$answerCount = count($answer);
			} else {
				$timeout = $_options['timeout'];
				$answerCount = 0;
				$description = "Votre prochain message sera la réponse.";
				$url = "text";
			}
		} else {
			if (!empty($_options['title'])) $title = $_options['title'];
			if (!empty($_options['url'])) $url = $_options['url'];
			if (!empty($_options['description'])) $description = $_options['description'];

			// Support du champ 'message' comme alias de 'description'
			if (!empty($_options['message']) && empty($description)) $description = $_options['message'];

			if (!empty($_options['footer'])) $footer = $_options['footer'];
			// TODO: Retirer la clé 'colors' (shim BC) une fois tous les scénarios existants migrés vers 'color'
			$color = $_options['color'] ?? $_options['colors'] ?? '';

			// Fields handling
			if (!empty($_options['field'])) {
				if (is_array($_options['field'])) {
					$fields = $_options['field'];
				} elseif (is_string($_options['field'])) {
					$decoded = json_decode($_options['field'], true);
					if (is_array($decoded)) $fields = $decoded;
				}
			}

			// Validate and process fields
			if (!empty($fields)) {
				foreach ($fields as &$field) {
					if (isset($field['name'])) {
						$field['name'] = $this->formatDiscordText($field['name'], false); // Pas de Markdown dans le nom de champ
					}
					if (isset($field['value'])) {
						$field['value'] = $this->formatDiscordText($field['value'], true); // Markdown OK dans la valeur de champ
					}
				}
			}

			// Quickaction handling
			if (!empty($_options['quickaction'])) {
				if (is_array($_options['quickaction'])) {
					$quickaction = $_options['quickaction'];
				} else {
					$splits = explode(',', (string)$_options['quickaction']);
					foreach ($splits as $q) {
						$clean = trim($q);
						if (!empty($clean)) $quickaction[] = $clean;
					}
				}
			}

			// Files handling
			if (!empty($_options['files'])) {
				if (is_array($_options['files'])) {
					$files = $_options['files'];
				} else {
					$filesRaw = scenarioExpression::setTags($_options['files']);
					$splits = explode(',', $filesRaw);
					foreach ($splits as $f) {
						$clean = trim($f);
						if (!empty($clean)) $files[] = $clean;
					}
				}
			}
		}

		// Traitement de la mise en forme du texte
		$title = $this->formatDiscordText($title, false); // Pas de Markdown dans le titre
		$description = $this->formatDiscordText($description, true); // Markdown OK dans la description
		$footer = $this->formatDiscordText($footer, false); // Pas de Markdown dans le pied de page

		// Traitement de l'URL uniquement si c'est une chaîne (embed standard) ; si c'est un tableau (mode ASK), on laisse tel quel
		if (is_string($url)) {
			// Remplacement des tags uniquement, sans emojis
			$url = $this->formatDiscordText($url, false);
		}

		// Si aucune couleur n'est définie, utiliser la couleur par défaut
		if (empty($color)) {
			$color = $defaultColor;
		}

		$channelID = $this->getEqLogic()->getConfiguration('channelId');

		return array(
			'endpoint' => '/sendEmbed',
			'method' => 'POST',
			'payload' => array(
				'channelID' => $channelID,
				'title' => $title,
				'description' => $description,
				'url' => $url,
				'footer' => $footer,
				'color' => $color,
				'defaultColor' => $defaultColor,
				'fields' => $fields,
				'quickaction' => $quickaction,
				'files' => $files,
				'answerCount' => $answerCount,
				'timeout' => $timeout
			)
		);
	}

	private function buildClearChannelRequest($_options = array()) {
		$daysToKeep = $this->getEqLogic()->getConfiguration('daysToKeep', 2);
		$channelID = $this->getEqLogic()->getConfiguration('channelId');

		return array(
			'endpoint' => '/clearChannel',
			'method' => 'POST',
			'payload' => array(
				'channelID' => $channelID,
				'daysToKeep' => ($daysToKeep >= -1 ? $daysToKeep : 2)
			)
		);
	}

	public static function decodeRandomText($_text) {
		$return = $_text;
		if (strpos($_text, '|') !== false && strpos($_text, '[') !== false && strpos($_text, ']') !== false) {
			$replies = interactDef::generateTextVariant($_text);
			$random = rand(0, count($replies) - 1);
			$return = $replies[$random];
		}
		preg_match_all('/{\((.*?)\) \?(.*?):(.*?)}/', $return, $matches, PREG_SET_ORDER, 0);
		$replace = array();
		if (is_array($matches) && count($matches) > 0) {
			foreach ($matches as $match) {
				if (count($match) != 4) {
					continue;
				}
				$replace[$match[0]] = (jeedom::evaluateExpression($match[1])) ? trim($match[2]) : trim($match[3]);
			}
		}
		return str_replace(array_keys($replace), $replace, $return);
	}

	public function buildDaemonInfo($_options = array()) {
		$message = '';
		$color = '#00ff08';

		foreach (plugin::listPlugin(true) as $plugin) {
			if ($plugin->getHasOwnDeamon() && config::byKey('deamonAutoMode', $plugin->getId(), 1) == 1) {
				$daemonInfo = $plugin->deamon_info();
				if ($daemonInfo['state'] != 'ok') {
					$message .= '|' . discordlink::getIcon("deamon_nok") . $plugin->getName() . ' (' . $plugin->getId() . ')';
					if ($color != '#ff0000') $color = '#ff0000';
				} else {
					$message .= '|' . discordlink::getIcon("deamon_ok") . $plugin->getName() . ' (' . $plugin->getId() . ')';
				}
			}
		}

		if (isset($_options['cron']) && $color == '#00ff08') {
			log::add('discordlink', 'debug', 'Vérification démons pour ' . $this->getEqLogic()->getName() . ' : Tous les démons sont OK, pas de notification Discord');
			return null;
		}
		$message = str_replace("|", "\n", $message);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => 'Etat des démons', 'description' => $message, 'color' => $color, 'footer' => 'DiscordLink');
		$cmd->execCmd($_options);
		return null;
	}

	public function buildDependencyInfo($_options = array()) {
		$message = '';
		$color = '#00ff08';

		foreach (plugin::listPlugin(true) as $plugin) {
			if ($plugin->getHasDependency()) {
				$dependencyInfo = $plugin->dependancy_info();
				if ($dependencyInfo['state'] == 'ok') {
					$message .= '|' . discordlink::getIcon("dep_ok") . $plugin->getName() . ' (' . $plugin->getId() . ')';
				} elseif ($dependencyInfo['state'] == 'in_progress') {
					$message .= '|' . discordlink::getIcon("dep_progress") . $plugin->getName() . ' (' . $plugin->getId() . ')';
					if ($color == '#00ff08') $color = '#ffae00';
				} else {
					$message .= '|' . discordlink::getIcon("dep_nok") . $plugin->getName() . ' (' . $plugin->getId() . ')';
					if ($color != '#ff0000') $color = '#ff0000';
				}
			}
		}

		if (isset($_options['cron']) && $color == '#00ff08') {
			log::add('discordlink', 'debug', 'Vérification dépendances pour ' . $this->getEqLogic()->getName() . ' : Toutes les dépendances sont OK, pas de notification Discord');
			return null;
		}
		$message = str_replace("|", "\n", $message);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => 'Etat des dépendances', 'description' => $message, 'color' => $color, 'footer' => 'DiscordLink');
		$cmd->execCmd($_options);
		return null;
	}

	public function buildGlobalSummary($_options = array()) {

		$objects = jeeObject::all();
		$def = config::byKey('object:summary');
		if (!is_array($def)) {
			log::add('discordlink', 'error', 'Configuration object:summary invalide ou non définie');
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('title' => 'Erreur', 'description' => '⚠️ Configuration des résumés non initialisée. Veuillez vérifier votre configuration Jeedom.', 'color' => '#ff0000');
			$cmd->execCmd($_options);
			return null;
		}
		$message = '';
		foreach ($def as $key => $value) {
			$result = '';
			$result = jeeObject::getGlobalSummary($key);
			if ($result == '') continue;
			$message .= '|' . discordlink::getIcon($key) . ' *** ' . $result . ' ' . $def[$key]['unit'] . ' ***		(' . $def[$key]['name'] . ')';
		}
		$message = str_replace("|", "\n", $message);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => 'Résumé général', 'description' => $message, 'color' => '#0033ff', 'footer' => 'DiscordLink');
		$cmd->execCmd($_options);

		return null;
	}

	public function buildGlobalBattery($_options = array()) {
		$color = '#00ff08';
		$alertThreshold = config::byKey('battery::warning', 'core', 30);
		$criticalThreshold = config::byKey('battery::danger', 'core', 10);
		$alertCount = 0;
		$criticalCount = 0;

		$eqLogics = eqLogic::all(true);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');

		$batteryList = array();
		foreach ($eqLogics as $eqLogic) {
			$battery = $eqLogic->getStatus('battery');
			if (is_numeric($battery)) {
				if ($battery <= $alertThreshold) {
					if ($battery <= $criticalThreshold) {
						$icon = "batterie_nok";
						$criticalCount++;
						if ($color != '#ff0000') $color = '#ff0000';
					} else {
						$icon = "batterie_progress";
						$alertCount++;
						if ($color == '#00ff08') $color = '#ffae00';
					}
				} else {
					$icon = "batterie_ok";
				}

				$obj = $eqLogic->getObject();
				$objName = is_object($obj) ? $obj->getName() : '';
				// TODO: Utiliser le nullsafe operator quand PHP 8 sera le minimum requis pour Jeedom
				// $batteryList[] = discordlink::getIcon($icon) . $eqLogic->getObject()?->getName() . '/' . $eqLogic->getName() . ' => __***' . $battery . "%***__";
				$batteryList[] = discordlink::getIcon($icon) . $objName . '/' . $eqLogic->getName() . ' => __***' . $battery . "%***__";
			}
		}

		$groupedMessages = self::groupMessagesByCountAndLength($batteryList, 20);

		$index = 1;
		foreach ($groupedMessages as $msg) {
			$message = str_replace("|", "\n", $msg);
			$_options = array(
				'title' => 'Résumé Batteries : (' . $index . '/' . count($groupedMessages) . ')',
				'description' => $message,
				'color' => $color,
				'footer' => 'DiscordLink'
			);
			$cmd->execCmd($_options);
			$index++;
		}

		$message2 = "Batterie en alerte : __***" . $alertCount . "***__\n Batterie critique : __***" . $criticalCount . "***__";

		$_options2 = array(
			'title' => 'Résumé Batterie',
			'description' => $message2,
			'color' => $color,
			'footer' => 'DiscordLink'
		);
		$cmd->execCmd($_options2);

		return null;
	}

	public function buildObjectSummary($_options = array()) {
		$objectId = $_options['select'];
		log::add('discordlink', 'debug', 'objectId : ' . $objectId);
		$object = jeeObject::byId($objectId);
		$def = config::byKey('object:summary');
		if (!is_array($def)) {
			log::add('discordlink', 'error', 'Configuration object:summary invalide ou non définie');
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('title' => 'Erreur', 'description' => '⚠️ Configuration des résumés non initialisée. Veuillez vérifier votre configuration Jeedom.', 'color' => '#ff0000');
			$cmd->execCmd($_options);
			return null;
		}
		$message = '';
		foreach ($def as $key => $value) {
			$result = '';
			$result = $object->getSummary($key);
			if ($result == '') continue;
			$message .= '|' . discordlink::getIcon($key) . ' *** ' . $result . ' ' . $def[$key]['unit'] . ' ***		(' . $def[$key]['name'] . ')';
		}
		$message = str_replace("|", "\n", $message);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => 'Résumé : ' . $object->getName(), 'description' => $message, 'color' => '#0033ff', 'footer' => 'DiscordLink');
		$cmd->execCmd($_options);

		return null;
	}

	public function buildMessageCenter($_options = array()) {
		// Parcours de tous les Updates
		$updateList = "";
		$updateCount = 0;
		$blockedMsg = "";
		$blockedUpdateCount = 0;
		foreach (update::all() as $update) {
			$updateName = $update->getName();
			$statusUpdate = strtolower($update->getStatus());
			$updateConfig = $update->getConfiguration('doNotUpdate');

			if ($updateConfig == 1) {
				$updateConfig = " **(MaJ bloquée)**";
				$blockedUpdateCount++;
			} else {
				$updateConfig = "";
			}

			if ($statusUpdate == "update") {
				$updateCount++;
				$updateList .= ($updateList == "" ? "" : "\n") . $updateCount . "- " . $updateName . $updateConfig;
			}
		}

		// Message de blocage
		$blockedMsg = $blockedUpdateCount == 0 ? "" : " (dont **" . $blockedUpdateCount . "** bloquée" . ($blockedUpdateCount > 1 ? "s" : "") . ")";

		// Message selon le nombre de mises à jour
		if ($updateCount == 0) {
			$msg = "*Vous n'avez pas de mises à jour en attente !*";
		} else {
			$pluriel = $updateCount > 1 ? "s" : "";
			$msg = "*Vous avez **" . $updateCount . "** mise" . $pluriel . " à jour en attente" . $blockedMsg . " :*\n" . $updateList;
		}

		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => ':gear: CENTRE DE MISES A JOUR :gear:', 'description' => $msg, 'color' => '#ff0000', 'footer' => 'DiscordLink');
		$cmd->execCmd($_options);

		// -------------------------------------------------------------------------------------- //
		$msgArray = array();
		$messageList = message::all();
		foreach ($messageList as $message) {
			$msgBloc = "[" . $message->getDate() . "] (" . $message->getPlugin() . ") :\n";
			$msgBloc .= " " . $message->getMessage() . "\n\n";
			$msgArray[] = html_entity_decode($msgBloc, ENT_QUOTES | ENT_HTML5);
		}

		if (count($msgArray) == 0) {
			$_options = array(
				'title' => ':clipboard: CENTRE DE MESSAGES :clipboard:',
				'description' => "*Le centre de message est vide !*",
				'color' => '#ff8040',
				'footer' => 'DiscordLink'
			);
			$cmd->execCmd($_options);
		} else {

			$groupedMessages = self::groupMessagesByCountAndLength($msgArray, 5);

			$index = 1;
			foreach ($groupedMessages as $msg) {
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array(
					'title' => ':clipboard: CENTRE DE MESSAGES ' . ($index) . '/' . count($groupedMessages) . ' :clipboard:',
					'description' => $msg,
					'color' => '#ff8040',
					'footer' => 'DiscordLink'
				);
				$cmd->execCmd($_options);
				$index++;
			}
		}

		return null;
	}

	public function buildLastUser($_options = array()) {
		$result = discordlink::getLastUserConnections();
		if (isset($_options['cron']) && !$result['cronOk']) return null;

		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => $result['title'], 'description' => str_replace("|", "\n", $result['message']), 'color' => '#ff00ff', 'footer' => 'DiscordLink');
		$cmd->execCmd($_options);
		return null;
	}

	public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {
		if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);

		// si on est sur un scenario
		list($command,) = explode('?', $this->getConfiguration('request'), 2);

		// objectSummary utilise le template select générique Jeedom ; listValue est maintenu par updateObject().
		if ($command === 'objectSummary') {
			return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
		}

		$templateFilename =  'cmd.' . $command;

		$replace = [
			'#uid#' => 'cmd' . $this->getId() . eqLogic::UIDDELIMITER . mt_rand() . eqLogic::UIDDELIMITER,
		];

		if ($command === 'sendEmbed') {
			/** @var discordlink $eqLogic */
			$eqLogic = $this->getEqLogic();
			$quickActionOptionsHtml = '';
			foreach (discordlink::getQuickActionOptions() as $option) {
				$quickActionOptionsHtml .= '<option value="' . $option['id'] . '">' . $option['name'] . '</option>';
			}
			$replace += [
				'#defaultColor#' => $eqLogic->getDefaultColor(),
				'#defaultTitle#' => '',
				'#defaultUrl#' => '',
				'#defaultDescription#' => '',
				'#defaultFooter#' => '',
				'#quickActionOptions#' => $quickActionOptionsHtml,
			];
		} elseif ($command === 'sendFile') {
			$replace += [
				'#defaultPath#' => '',
			];
		}

		$html = template_replace($replace, getTemplate('core', 'scenario', $templateFilename, 'discordlink'));
		$html = translate::exec($html, 'plugins/discordlink/core/template/scenario/' . $templateFilename . '.html');

		if (!is_null($html) && !is_array($html)) {
			return array('template' => $html, 'isCoreWidget' => false);
		}

		return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
	}

	/**
	 * Regroupe les messages par blocs de X messages max et Y caractères max.
	 * @param array $messages Tableau de messages (chaînes)
	 * @param int $maxMsg Nombre max de messages par bloc
	 * @param int $maxChars Nombre max de caractères par bloc
	 * @return array Tableau de blocs concaténés
	 */
	private static function groupMessagesByCountAndLength(array $messages, int $maxMsg, int $maxChars = 4096) {
		$result = [];
		$currentBlock = [];
		$currentLength = 0;

		foreach ($messages as $msg) {
			$msgLength = mb_strlen($msg);

			// Si ajouter ce message dépasse une des limites, on commence un nouveau bloc
			if (
				count($currentBlock) >= $maxMsg ||
				($currentLength > 0 && $currentLength + $msgLength + 1 > $maxChars) // +1 pour le \n
			) {
				$result[] = implode("\n", $currentBlock);
				$currentBlock = [];
				$currentLength = 0;
			}

			$currentBlock[] = $msg;
			$currentLength += $msgLength + ($currentLength > 0 ? 1 : 0); // +1 pour le \n si pas premier
		}

		// Ajoute le dernier bloc s'il reste des messages
		if (!empty($currentBlock)) {
			$result[] = implode("\n", $currentBlock);
		}

		return $result;
	}
	/*     * ********************** Getter Setter *************************** */
}
