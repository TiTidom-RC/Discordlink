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

	public static function getChannel() {
		try {
			$requestHttp = new com_http(self::getDaemonBaseURL() . '/getchannel');
			$response = $requestHttp->exec(10, 2);
			if ($response === false || empty($response)) {
				log::add('discordlink', 'error', 'Impossible de récupérer les channels depuis le daemon');
				return array();
			}
			$channels = json_decode($response, true);
			return is_array($channels) ? $channels : array();
		} catch (Exception $e) {
			log::add('discordlink', 'error', 'Erreur getchannel: ' . $e->getMessage());
			return array();
		}
	}

	public static function setChannel() {
		$channels = static::getChannel();
		if (empty($channels)) return;

		array_walk($channels, function (&$channel) {
			$channel['name'] = discordlink::removeEmoji($channel['name']);
		});

		config::save('channels', $channels, 'discordlink');
	}

	private static function removeEmoji($text) {
		// Supprime tous les emoji Unicode (PHP 7.4+) - Property Emoji couvre tous les emoji modernes
		// Inclut: emoticons, symboles, drapeaux, ZWJ sequences, variations, skin tones
		return preg_replace('/\p{Emoji}/u', '', $text);
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

	public static function updateInfo() {
		sleep(2);
		static::updateObject();
		static::setChannel();
	}

	public static function emojiConvert($_text): string {
		$_returnText = '';
		$textParts = explode(" ", $_text);
		foreach ($textParts as $value) {
			if (substr($value, 0, 4) === "emo_") {
				$emoji = discordlink::getIcon(str_replace("emo_", "", $value));
				$_returnText .= $emoji;
			} else {
				$_returnText .= $value;
			}
			$_returnText .= " ";
		}
		return $_returnText;
	}

	private static function executeCronIfDue($eqLogic, $cronExpr, $cmdLogicId, $debugLabel, $dateRun, $_options) {
		if (empty($cronExpr)) return;

		try {
			$c = new Cron\CronExpression($cronExpr, new Cron\FieldFactory);
			if ($c->isDue($dateRun)) {
				log::add('discordlink', 'debug', $debugLabel);
				$cmd = $eqLogic->getCmd('action', $cmdLogicId);
				if (is_object($cmd)) {
					$cmd->execCmd($_options);
				}
			}
		} catch (Exception $exc) {
			log::add('discordlink', 'error', __('Expression cron non valide pour ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $cronExpr);
		}
	}

	public static function checkAll() {
		$eqLogics = eqLogic::byType('discordlink');
		if (empty($eqLogics)) {
			return;
		}

		$dateRun = new DateTime();
		$options = ['cron' => true];

		foreach ($eqLogics as $eqLogic) {
			// Vérification démon
			if ($eqLogic->getConfiguration('daemonCheck', 0) === 1) {
				static::executeCronIfDue($eqLogic, $eqLogic->getConfiguration('autoRefreshDaemon'), 'daemonInfo', 'DaemonCheck', $dateRun, $options);
			}

			// Vérification dépendances
			if ($eqLogic->getConfiguration('dependencyCheck', 0) === 1) {
				static::executeCronIfDue($eqLogic, $eqLogic->getConfiguration('autoRefreshDependency'), 'dependencyInfo', 'DependencyCheck', $dateRun, $options);
			}

			// Vérification connexions utilisateurs
			if ($eqLogic->getConfiguration('connectionCheck', 0) === 1) {
				$cmd = $eqLogic->getCmd('action', 'lastUser');
				if (is_object($cmd)) {
					log::add('discordlink', 'debug', 'Vérification connexion utilisateur pour ' . $eqLogic->getName());
					$cmd->execCmd($options);
				}
			}
		}
	}
	/*     * *********************** Static Methods *************************** */

	/*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
	 */
	public static function cron() {
		static::checkAll();
	}

	/*
     * Fonction exécutée automatiquement toutes les heures par Jeedom*/
	public static function cronHourly() {
		static::updateInfo();
	}

	/*
     * Fonction exécutée automatiquement tous les jours par Jeedom*/
	public static function cronDaily() {
		$eqLogics = eqLogic::byType('discordlink');
		foreach ($eqLogics as $eqLogic) {
			if ($eqLogic->getConfiguration('clearChannel', 0) != 1) continue;

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
				if (is_array($json) && isset($json['status']) && $json['status'] == 'ok') {
					log::add('discordlink', 'debug', 'Heartbeat OK (Uptime: ' . round($json['uptime'], 2) . 's)');
				} else {
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

		log::add('discordlink', 'info', 'Lancement du bot');

		$apiKey = jeedom::getApiKey('discordlink');
		$cmd = sprintf(
			'nice -n 19 node %s/discordlink.js %s %s %s %s %s %s %s',
			escapeshellarg(realpath(dirname(__FILE__) . '/../../resources')),
			escapeshellarg(network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp')),
			escapeshellarg(config::byKey('Token', 'discordlink')),
			escapeshellarg(log::getLogLevel('discordlink')),
			escapeshellarg(network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/discordlink/core/api/jeeDiscordlink.php?apikey=' . $apiKey),
			escapeshellarg($apiKey),
			escapeshellarg(config::byKey('joueA', 'discordlink', 'Travailler main dans la main avec votre Jeedom')),
			escapeshellarg(config::byKey('socketport', 'discordlink', self::SOCKET_PORT))
		);

		log::add('discordlink', 'debug', 'Lancement démon discordlink : ' . $cmd);

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
				log::add('discordlink', 'info', 'Démon discordlink lancé');
				static::updateInfo();
				return true;
			}
			sleep(1);
		}

		log::add('discordlink', 'error', 'Impossible de lancer le démon discordlink, vérifiez le port', 'unableStartDeamon');
		return false;
	}

	public static function deamon_stop() {
		log::add('discordlink', 'info', 'Arrêt du démon discordlink');

		// Arrêt gracieux via API HTTP
		try {
			$requestHttp = new com_http(self::getDaemonBaseURL() . '/stop');
			$requestHttp->setNoReportError(true);
			$requestHttp->setAllowEmptyReponse(true);
			$requestHttp->exec(1, 1);
		} catch (Exception $e) {
			log::add('discordlink', 'error', 'Erreur Arrêt du Démon :: ' . $e->getMessage());
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
		$this->setIsEnable(1);
	}

	public function postInsert() {
	}

	public function preSave() {
		$channel = $this->getConfiguration('channelId');
		if (!empty($channel) && $channel != 'null') {
			$this->setLogicalId($channel);
			log::add('discordlink', 'debug', 'setLogicalId : ' . $channel);
		} else {
			$this->setConfiguration('channelId', $this->getLogicalId());
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
				'sendMsg' => array('requiredPlugin' => '0', 'label' => 'Envoi message', 'type' => 'action', 'subType' => 'message', 'request' => 'sendMsg?message=#message#', 'visible' => 1, 'template' => 'discordlink::message'),
				'sendMsgTTS' => array('requiredPlugin' => '0', 'label' => 'Envoi message TTS', 'type' => 'action', 'subType' => 'message', 'request' => 'sendMsgTTS?message=#message#', 'visible' => 1, 'template' => 'discordlink::message'),
				'sendEmbed' => array('requiredPlugin' => '0', 'label' => 'Envoi message évolué', 'type' => 'action', 'subType' => 'message', 'request' => 'sendEmbed?color=#color#&title=#title#&url=#url#&description=#description#&field=#field#&countanswer=#countanswer#&footer=#footer#&timeout=#timeout#&quickreply=#quickreply#', 'visible' => 1, 'template' => 'discordlink::embed'),
				'sendFile' => array('requiredPlugin' => '0', 'label' => 'Envoi fichier', 'type' => 'action', 'subType' => 'message', 'request' => 'sendFile?patch=#patch#&name=#name#&message=#message#', 'visible' => 0),
				'deleteMessage' => array('requiredPlugin' => '0', 'label' => 'Supprime les messages du channel', 'type' => 'action', 'subType' => 'other', 'request' => 'deleteMessage?null', 'visible' => 0),
				'daemonInfo' => array('requiredPlugin' => '0', 'label' => 'Etat des démons', 'type' => 'action', 'subType' => 'other', 'request' => 'daemonInfo?null', 'visible' => 1),
				'dependencyInfo' => array('requiredPlugin' => '0', 'label' => 'Etat des dépendances', 'type' => 'action', 'subType' => 'other', 'request' => 'dependencyInfo?null', 'visible' => 1),
				'globalSummary' => array('requiredPlugin' => '0', 'label' => 'Résumé général', 'type' => 'action', 'subType' => 'other', 'request' => 'globalSummary?null', 'visible' => 1),
				'batteryInfo' => array('requiredPlugin' => '0', 'label' => 'Résumé des batteries', 'type' => 'action', 'subType' => 'other', 'request' => 'batteryInfo?null', 'visible' => 1),
				'messageCenter' => array('requiredPlugin' => '0', 'label' => 'Centre de messages', 'type' => 'action', 'subType' => 'other', 'request' => 'messageCenter?null', 'visible' => 1),
				'lastUser' => array('requiredPlugin' => '0', 'label' => 'Dernière Connexion utilisateur', 'type' => 'action', 'subType' => 'other', 'request' => 'lastUser?null', 'visible' => 1),
				'objectSummary' => array('requiredPlugin' => '0', 'label' => 'Résumé par objet', 'type' => 'action', 'subType' => 'select', 'request' => 'objectSummary?null', 'visible' => 1),
				'lastMessage' => array('requiredPlugin' => '0', 'label' => 'Dernier message', 'type' => 'info', 'subType' => 'string', 'visible' => 1),
				'previousMessage1' => array('requiredPlugin' => '0', 'label' => 'Avant dernier message', 'type' => 'info', 'subType' => 'string', 'visible' => 1),
				'previousMessage2' => array('requiredPlugin' => '0', 'label' => 'Avant Avant dernier message', 'type' => 'info', 'subType' => 'string', 'visible' => 1)
			);
			$order = 0;
			foreach ($commandsConfig as $cmdKey => $cmdConfig) {
				// Vérifier si le plugin requis est actif ("0" = pas de dépendance)
				if ($cmdConfig['requiredPlugin'] == "0" || discordlink::testPlugin($cmdConfig['requiredPlugin'])) {
					$cmd = $eqLogic->getCmd(null, $cmdKey);
					if (!is_object($cmd)) {
						$cmd = new discordlinkCmd();
						$cmd->setName($cmdConfig['label']);
						$cmd->setIsVisible($cmdConfig['visible']);
						$cmd->setType($cmdConfig['type']);
						$cmd->setSubType($cmdConfig['subType']);
					}
					$cmd->setEqLogic_id($eqLogic->getId());
					$cmd->setLogicalId($cmdKey);
					if ($cmdConfig['type'] == "action" && $cmdKey != "daemonInfo") {
						$cmd->setConfiguration('request', $cmdConfig['request']);
						$cmd->setConfiguration('value', discordlink::getDaemonBaseURL() . '/' . $cmdConfig['request'] . "&channelID=" . $eqLogic->getConfiguration('channelId'));
					}
					if ($cmdConfig['type'] == "action" && $cmdKey == "daemonInfo") {
						$cmd->setConfiguration('request', $cmdConfig['request']);
						$cmd->setConfiguration('value', $cmdConfig['request']);
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
					$cmd->save();
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
		discordlink::createCmd();
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
		$connectedUserListNew = '';
		$connectedUserList = '';
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
		$connectedUserListParts = array();

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

			// Construction de la liste
			$connectedUserListParts[] = $user->getLogin() . ';' . $lastDate . ';' . $status;
		}
		$connectedUserList = implode('|', $connectedUserListParts);

		$connectedUserListNew = '';
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

					$onlineCount++;
					$message .= "\n" . $emojiCheck . "**" . $connectionLogNames[$logUserIndex] . "** s'est connecté par **" . $connectionLogTypes[$logUserIndex] . "** à **" . date("H", strtotime($connectionLogDates[$logUserIndex])) . "h" . date("i", strtotime($connectionLogDates[$logUserIndex])) . "**";

					$hasCronActivity = true;

					// Évite les doublons consécutifs dans le log pour le même utilisateur
					if ($lastLogConnectionName === $connectionLogNames[$logUserIndex]) {
						continue;
					}
					$lastLogConnectionName = $connectionLogNames[$logUserIndex];

					// Mise à jour du statut des utilisateurs
					$userIndex = 0;
					$foundCount = 0;
					foreach ($connectedUserNames as $key => $userName) {
						$userIndex++;
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

			// Reconstruction propre de la liste finale une seule fois
			$connectedUserListNew = '';
			foreach ($connectedUserNames as $key => $name) {
				if ($connectedUserListNew != '') {
					$connectedUserListNew .= '|';
				}
				// Gérer les index qui peuvent être numériques ou string selon l'initialisation précédente
				$date = isset($connectedUserDates[$key]) ? $connectedUserDates[$key] : '';
				$status = isset($connectedUserStatuses[$key]) ? $connectedUserStatuses[$key] : 'hors ligne';
				$connectedUserListNew .= $name . ';' . $date . ';' . $status;
			}
			$connectedUserList = $connectedUserListNew;
		}

		$sessions = listSession();
		$sessionCount = count($sessions);												//nombre d'utilisateur en session actuellement

		$message .= "\n" . "\n" . $emojiMagRight . "__Récapitulatif des sessions actuelles :__ " . $emojiMag;
		// Parcours des sessions pour vérifier le statut et le nombre de sessions
		$userIndex = 0;
		$connectedUserListNew = '';
		foreach ($connectedUserNames as $value) {
			$userIndex++;
			$sessionIndex = 0;
			$foundCount = 0;
			$connectedUserStatuses[$userIndex] = 'hors ligne';
			$connectedUserIPs[$userIndex] = '';

			foreach ($sessions as $id => $session) {
				$sessionIndex++;

				$userDelay = strtotime(date("Y-m-d H:i:s")) - strtotime($session['datetime']);

				if ($connectedUserNames[$userIndex] == $session['login']) {
					if ($userDelay < $offlineDelay * 60) {
						$foundCount++;
						$onlineCount++;
						$connectedUserStatuses[$userIndex] = 'en ligne';
						$connectedUserIPs[$userIndex] .= "\n" . "-> " . $emojiInternet . " IP : " . $session['ip'];
					} else {
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
				$message .= "\n" . $emojiConnected . " **" . $connectedUserNames[$userIndex] . "** est **en ligne** depuis **" . $date . "**";
				$message .= $connectedUserIPs[$userIndex];
			} else {
				if (strtotime($timeNow) - strtotime($connectedUserDates[$userIndex]) < ($daysBeforeUserRemoval * 24 * 60 * 60)) {
					$message .= "\n" . $emojiDisconnected . " **" . $connectedUserNames[$userIndex] . "** est **hors ligne** (dernière connexion **" . $date . "**)";
				}
			}
			if ($connectedUserListNew != '') {
				$connectedUserListNew = $connectedUserListNew . '|';
			}
			$connectedUserListNew .= $connectedUserNames[$userIndex] . ';' . $connectedUserDates[$userIndex] . ';' . $connectedUserStatuses[$userIndex];
			$connectedUserList = $connectedUserListNew;
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

		$deamon = discordlink::deamon_info();
		if ($deamon['state'] == 'ok') {
			$request = $this->buildRequest($_options);
			if ($request != 'requestHandledInternally') {
				log::add('discordlink', 'debug', 'Envoi de ' . $request);
				$request_http = new com_http($request);
				$request_http->setAllowEmptyReponse(true); //Autorise les réponses vides
				if ($this->getConfiguration('noSslCheck') == 1) $request_http->setNoSslCheck(true);
				if ($this->getConfiguration('doNotReportHttpError') == 1) $request_http->setNoReportError(true);
				if (isset($_options['speedAndNoErrorReport']) && $_options['speedAndNoErrorReport'] == true) { // option non activée
					$request_http->setNoReportError(true);
					$request_http->exec(0.1, 1);
					return;
				}
				$result = $request_http->exec($this->getConfiguration('timeout', 6), $this->getConfiguration('maxHttpRetry', 1)); //Time out à 3s 3 essais
				if (!$result) throw new Exception(__('Serveur injoignable', __FILE__));
				return true;
			} else {
				return true;
			}
		}
		return false;
	}

	private function buildRequest($_options = array()) {
		if ($this->getType() != 'action') return $this->getConfiguration('request');

		$cmdAndArg = explode('?', $this->getConfiguration('request'), 2);
		$command = $cmdAndArg[0];

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
			'deleteMessage' => 'clearChannel?'
		);

		if (isset($commandMap[$command])) {
			$request = is_callable(array($this, $commandMap[$command]))
				? $this->{$commandMap[$command]}($_options)
				: $commandMap[$command];
		} else {
			$request = '';
		}

		if ($request == 'requestHandledInternally') return $request;

		$request = scenarioExpression::setTags($request);
		if (trim($request) == '') {
			throw new Exception(__('Commande inconnue ou requête vide : ', __FILE__) . print_r($this, true));
		}

		$channelID = str_replace('_player', '', $this->getEqLogic()->getConfiguration('channelId'));
		return discordlink::getDaemonBaseURL() . '/' . $request . '&channelID=' . $channelID;
	}

	private function buildMessageRequest($_options = array(), $default = "Une erreur est survenue") {
		$message = isset($_options['message']) && $_options['message'] != '' ? $_options['message'] : $default;
		$message = str_replace('|', "\n", $message);
		$request = str_replace('#message#', urlencode(self::decodeRandomText($message)), $this->getConfiguration('request'));
		log::add('discordlink', 'info', 'Final Request :: ' . $request);
		return $request;
	}

	private function buildFileRequest($_options = array(), $default = "Une erreur est survenu") {
		$patch = "null";
		$fileName = "null";
		$message = "null";

		$request = $this->getConfiguration('request');
		if ((isset($_options['path'])) && ($_options['path'] == "")) $_options['path'] = $default;
		if (!(isset($_options['path']))) $_options['path'] = "";

		if (isset($_options['files']) && is_array($_options['files'])) {
			foreach ($_options['files'] as $file) {
				if (version_compare(phpversion(), '5.5.0', '>=')) {
					$filePath = $file;
					$files = new CurlFile($file);
					$fileNameParts = explode('.', $files->getFilename());
					log::add('discordlink', 'info', $_options['title'] . ' taille : ' . $fileNameParts[sizeof($fileNameParts) - 1]);
					$fileDisplay = (isset($_options['title']) ? $_options['title'] . '.' . $fileNameParts[sizeof($fileNameParts) - 1] : $files->getFilename());
				}
			}
			$message = $_options['message'];
		} else {
			$filePath = $_options['path'];
			$fileDisplay = $_options['displayName'];
		}

		$request = str_replace(
			array('#message#'),
			array(urlencode(self::decodeRandomText($message))),
			$request
		);
		$request = str_replace(
			array('#name#'),
			array(urlencode(self::decodeRandomText($fileDisplay))),
			$request
		);
		$request = str_replace(
			array('#patch#'),
			array(urlencode(self::decodeRandomText($filePath))),
			$request
		);

		log::add('discordlink', 'info', 'Final Request :: ' . $request);
		return $request;
	}

	private function buildEmbedRequest($_options = array(), $default = "Une erreur est survenue") {

		$request = $this->getConfiguration('request');

		// Initialisation de toutes les variables à chaîne vide
		$title = "";
		$url = "";
		$description = "";
		$footer = "";
		$colors = "";
		$field = "";
		$timeout = "";
		$countanswer = "";
		$quickreply = "";

		/** @var discordlink $eqLogic */
		$eqLogic = $this->getEqLogic();
		$defaultColor = $eqLogic->getDefaultColor();

		if (isset($_options['answer'])) {
			if (("" != ($_options['title']))) $title = $_options['title'];
			$colors = "#1100FF";

			if ($_options['answer'][0] != "") {
				$answer = $_options['answer'];
				$timeout = $_options['timeout'];
				$description = "";

				$a = 0;
				$url = "[";
				$choices = [":regional_indicator_a:", ":regional_indicator_b:", ":regional_indicator_c:", ":regional_indicator_d:", ":regional_indicator_e:", ":regional_indicator_f:", ":regional_indicator_g:", ":regional_indicator_h:", ":regional_indicator_i:", ":regional_indicator_j:", ":regional_indicator_k:", ":regional_indicator_l:", ":regional_indicator_m:", ":regional_indicator_n:", ":regional_indicator_o:", ":regional_indicator_p:", ":regional_indicator_q:", ":regional_indicator_r:", ":regional_indicator_s:", ":regional_indicator_t:", ":regional_indicator_u:", ":regional_indicator_v:", ":regional_indicator_w:", ":regional_indicator_x:", ":regional_indicator_y:", ":regional_indicator_z:"];
				while ($a < count($answer)) {
					$description .= $choices[$a] . " : " . $answer[$a];
					$description .= "
";
					$url .= '"' . $answer[$a] . '",';
					$a++;
				}
				$url = rtrim($url, ',');
				$url .= ']';
				$countanswer = count($answer);
			} else {
				$timeout = $_options['timeout'];
				$countanswer = 0;
				$description = "Votre prochain message sera la réponse.";
				$url = "text";
			}
		} else {
			if (!empty($_options['title'])) $title = $_options['title'];
			if (!empty($_options['url'])) $url = $_options['url'];
			if (!empty($_options['description'])) $description = $_options['description'];
			// Support du champ 'message' comme alias de 'description' (pour le bouton test dashboard)
			if (!empty($_options['message']) && empty($description)) $description = $_options['message'];
			if (!empty($_options['footer'])) $footer = $_options['footer'];
			if (!empty($_options['colors'])) $colors = $_options['colors'];
			if (!empty($_options['field'])) $field = json_encode($_options['field']);
			if (!empty($_options['quickreply'])) $quickreply = $_options['quickreply'];
		}

		$description = discordlink::emojiConvert($description);
		log::add('discordlink', 'debug', 'description : ' . $description);
		$description = str_replace('|', "\n", $description);

		// Si aucune couleur n'est définie, utiliser la couleur par défaut
		if (empty($colors)) {
			$colors = $defaultColor;
		}

		// Remplacement des variables
		$replacements = array(
			'#title#' => $title,
			'#url#' => $url,
			'#description#' => $description,
			'#footer#' => $footer,
			'#countanswer#' => $countanswer,
			'#field#' => $field,
			'#color#' => $colors,
			'#timeout#' => $timeout,
			'#quickreply#' => $quickreply
		);

		foreach ($replacements as $key => $value) {
			$request = str_replace($key, urlencode(self::decodeRandomText($value)), $request);
		}

		log::add('discordlink', 'info', 'Final Request :: ' . $request);
		return $request;
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
		$colors = '#00ff08';

		foreach (plugin::listPlugin(true) as $plugin) {
			if ($plugin->getHasOwnDeamon() && config::byKey('deamonAutoMode', $plugin->getId(), 1) == 1) {
				$daemonInfo = $plugin->deamon_info();
				if ($daemonInfo['state'] != 'ok') {
					$message .= '|' . discordlink::getIcon("deamon_nok") . $plugin->getName() . ' (' . $plugin->getId() . ')';
					if ($colors != '#ff0000') $colors = '#ff0000';
				} else {
					$message .= '|' . discordlink::getIcon("deamon_ok") . $plugin->getName() . ' (' . $plugin->getId() . ')';
				}
			}
		}

		if (isset($_options['cron']) and $colors == '#00ff08') return 'requestHandledInternally';
		$message = str_replace("|", "\n", $message);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => 'Etat des démons', 'description' => $message, 'colors' => $colors, 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options);
		return 'requestHandledInternally';
	}

	public function buildDependencyInfo($_options = array()) {
		$message = '';
		$colors = '#00ff08';

		foreach (plugin::listPlugin(true) as $plugin) {
			if ($plugin->getHasDependency()) {
				$dependencyInfo = $plugin->dependancy_info();
				if ($dependencyInfo['state'] == 'ok') {
					$message .= '|' . discordlink::getIcon("dep_ok") . $plugin->getName() . ' (' . $plugin->getId() . ')';
				} elseif ($dependencyInfo['state'] == 'in_progress') {
					$message .= '|' . discordlink::getIcon("dep_progress") . $plugin->getName() . ' (' . $plugin->getId() . ')';
					if ($colors == '#00ff08') $colors = '#ffae00';
				} else {
					$message .= '|' . discordlink::getIcon("dep_nok") . ' (' . $plugin->getId() . ')';
					if ($colors != '#ff0000') $colors = '#ff0000';
				}
			}
		}

		if (isset($_options['cron']) && $colors == '#00ff08') return 'requestHandledInternally';
		$message = str_replace("|", "\n", $message);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => 'Etat des dépendances', 'description' => $message, 'colors' => $colors, 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options);
		return 'requestHandledInternally';
	}

	public function buildGlobalSummary($_options = array()) {

		$objects = jeeObject::all();
		$def = config::byKey('object:summary');
		if (!is_array($def)) {
			log::add('discordlink', 'error', 'Configuration object:summary invalide ou non définie');
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('title' => 'Erreur', 'description' => '⚠️ Configuration des résumés non initialisée. Veuillez vérifier votre configuration Jeedom.', 'colors' => '#ff0000');
			$cmd->execCmd($_options);
			return 'requestHandledInternally';
		}
		$values = array();
		$message = '';
		foreach ($def as $key => $value) {
			$result = '';
			$result = jeeObject::getGlobalSummary($key);
			if ($result == '') continue;
			$message .= '|' . discordlink::getIcon($key) . ' *** ' . $result . ' ' . $def[$key]['unit'] . ' ***		(' . $def[$key]['name'] . ')';
		}
		$message = str_replace("|", "\n", $message);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => 'Résumé général', 'description' => $message, 'colors' => '#0033ff', 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options);

		return 'requestHandledInternally';
	}

	public function buildGlobalBattery($_options = array()) {
		$message = 'null';
		$colors = '#00ff08';
		$alertThreshold = 30;
		$criticalThreshold = 10;
		$alertCount = 0;
		$criticalCount = 0;
		$batteryCount = 0;
		$totalCount = 0;
		$maxLength = 1800; // Limite Discord ~2000, on garde une marge

		$eqLogics = eqLogic::all(true);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');

		$batteryList = '';
		foreach ($eqLogics as $eqLogic) {
			$totalCount++;
			$battery = $eqLogic->getStatus('battery');

			if (!is_numeric($battery)) continue;

			$batteryCount++;
			$name = substr($eqLogic->getHumanName(), strrpos($eqLogic->getHumanName(), '[', -1) + 1, -1);

			if ($battery <= $criticalThreshold) {
				$line = "\n" . discordlink::getIcon("batterie_nok") . $name . ' => __***' . $battery . "%***__";
				$criticalCount++;
				$colors = '#ff0000';
			} elseif ($battery <= $alertThreshold) {
				$line = "\n" . discordlink::getIcon("batterie_progress") . $name . ' =>  __***' . $battery . "%***__";
				$alertCount++;
				if ($colors == '#00ff08') $colors = '#ffae00';
			} else {
				$line = "\n" . discordlink::getIcon("batterie_ok") . $name . ' =>  __***' . $battery . "%***__";
			}

			// Vérifier si l'ajout de cette ligne dépasserait la limite
			if (strlen($batteryList . $line) > $maxLength) {
				$_options = array('title' => 'Résumé Batteries : ', 'description' => str_replace("|", "\n", $batteryList), 'colors' => $colors, 'footer' => 'By DiscordLink');
				$cmd->execCmd($_options);
				$batteryList = '';
			}

			$batteryList .= $line;
		}

		$_options = array('title' => 'Résumé Batteries : ', 'description' => str_replace("|", "\n", $batteryList), 'colors' => $colors, 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options);

		$message2 = "Batterie en alerte : __***" . $alertCount . "***__\n Batterie critique : __***" . $criticalCount . "***__";
		$_options2 = array('title' => 'Résumé Batterie', 'description' => str_replace("|", "\n", $message2), 'colors' => $colors, 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options2);

		return 'requestHandledInternally';
	}

	public function buildObjectSummary($_options = array()) {
		$objectId = $_options['select'];
		log::add('discordlink', 'debug', 'objectId : ' . $objectId);
		$object = jeeObject::byId($objectId);
		$def = config::byKey('object:summary');
		if (!is_array($def)) {
			log::add('discordlink', 'error', 'Configuration object:summary invalide ou non définie');
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('title' => 'Erreur', 'description' => '⚠️ Configuration des résumés non initialisée. Veuillez vérifier votre configuration Jeedom.', 'colors' => '#ff0000');
			$cmd->execCmd($_options);
			return 'requestHandledInternally';
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
		$_options = array('title' => 'Résumé : ' . $object->getName(), 'description' => $message, 'colors' => '#0033ff', 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options);

		return 'requestHandledInternally';
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
		$_options = array('title' => ':gear: CENTRE DE MISES A JOUR :gear:', 'description' => $msg, 'colors' => '#ff0000', 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options);

		// -------------------------------------------------------------------------------------- //
		$msg = array();
		$messageCount = 0;
		$maxMessagesPerBatch = 5;		//Nombre de messages par bloc de notification
		$batchNumber = 1;
		$messageList = message::all();
		foreach ($messageList as $message) {
			$messageCount++;
			if (!($messageCount <= $maxMessagesPerBatch)) {
				$messageCount = 1;
				$batchNumber = $batchNumber + 1;
			}

			$msg[$batchNumber] .= "[" . $message->getDate() . "]";
			$msg[$batchNumber] .= " (" . $message->getPlugin() . ") :";
			$msg[$batchNumber] .= "\n";
			($message->getAction() != "") ? $msg[$batchNumber] .= " (Action : " . $message->getAction() . ")" : null;
			$msg[$batchNumber] .= " " . $message->getMessage() . "\n";
			$msg[$batchNumber] .= "\n";
			$msg[$batchNumber] = html_entity_decode($msg[$batchNumber], ENT_QUOTES | ENT_HTML5);
		}

		if ($messageCount == 0) {
			$_options = array('title' => ':clipboard: CENTRE DE MESSAGES :clipboard:', 'description' => "*Le centre de message est vide !*", 'colors' => '#ff8040', 'footer' => 'By DiscordLink');
			$cmd->execCmd($_options);
		} else {
			$i = 0;
			foreach ($msg as $value) {
				$i++;
				$_options = array('title' => ':clipboard: CENTRE DE MESSAGES ' . $i . '/' . count($msg) . ' :clipboard:', 'description' => $value, 'colors' => '#ff8040', 'footer' => 'By DiscordLink');
				$cmd->execCmd($_options);
			}
		}

		return 'requestHandledInternally';
	}

	public function buildLastUser($_options = array()) {
		$result = discordlink::getLastUserConnections();
		if (isset($_options['cron']) && !$result['cronOk']) return 'requestHandledInternally';

		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title' => $result['title'], 'description' => str_replace("|", "\n", $result['message']), 'colors' => '#ff00ff', 'footer' => 'By DiscordLink');
		$cmd->execCmd($_options);
		return 'requestHandledInternally';
	}

	public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {
		if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);

		list($command,) = explode('?', $this->getConfiguration('request'), 2);
		$data = '';
		if ($command == 'sendMsg')
			$data = getTemplate('core', 'scenario', 'cmd.sendMsg', 'discordlink');
		if ($command == 'sendMsgTTS')
			$data = getTemplate('core', 'scenario', 'cmd.sendMsgtts', 'discordlink');
		if ($command == 'sendEmbed')
			$data = getTemplate('core', 'scenario', 'cmd.sendEmbed', 'discordlink');
		if ($command == 'sendFile')
			$data = getTemplate('core', 'scenario', 'cmd.sendFile', 'discordlink');

		if (preg_match_all('/{{(.*?)}}/', $data, $matches)) {
			foreach ($matches[1] as $match) {
				$data = str_replace('{{' . $match . '}}', __($match, __FILE__), $data);
			}
		}

		/** @var discordlink $eqLogic */
		$eqLogic = $this->getEqLogic();
		$defaultColor = $eqLogic->getDefaultColor();
		$replace = [
			'#defaultColor#' => $defaultColor,
			'#defaultTitle#' => '',
			'#defaultUrl#' => '',
			'#defaultDescription#' => '',
			'#defaultFooter#' => '',
			'#defaultPath#' => '',
			'#defaultDisplayName#' => '',
		];
		$data = str_replace(array_keys($replace), array_values($replace), $data);

		if (!is_null($data)) {
			if (!is_array($data)) return array('template' => $data, 'isCoreWidget' => false);
		}
		return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
	}
	/*     * ********************** Getter Setter *************************** */
}
