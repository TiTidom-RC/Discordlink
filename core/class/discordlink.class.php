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

/* * ***************************Includes********************************* */
require_once __DIR__ . '/../../../../core/php/core.inc.php';

class discordlink extends eqLogic {
    /*     * *************************Attributs****************************** */

	const DEFAULT_COLOR = '#ff0000';

	public static function templateWidget() {
		$return['action']['message']['message'] =    array(
				'template' => 'message',
				'replace' => array("#_desktop_width_#" => "100","#_mobile_width_#" => "50", "#title_disable#" => "1", "#message_disable#" => "0")
		);
		$return['action']['message']['embed'] =    array(
			'template' => 'embed',
			'replace' => array("#_desktop_width_#" => "100","#_mobile_width_#" => "50", "#title_disable#" => "1", "#message_disable#" => "0")
		);
		return $return;
	}

	public static function testPlugin($_pluginid) {
		$plugin = plugin::byId($_pluginid);
		return (is_object($plugin) && $plugin->isActive());
	}

	public static function getChannel() {
		try {
			$requestHttp = new com_http('http://127.0.0.1:3466/getchannel');
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
		
		array_walk($channels, function(&$channel) {
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
			'motion' => ':person_walking:',
			'door' => ':door:',
			'windows' => ':frame_photo:',
			'light' => ':bulb:',
			'outlet' => ':electric_plug:',
			'temperature' => ':thermometer:',
			'humidity' => ':droplet:',
			'luminosity' => ':sunny:',
			'power' => ':cloud_lightning:',
			'security' => ':rotating_light:',
			'shutter' => ':beginner:',
			'deamon_ok' => ':green_circle:',
			'deamon_nok' => ':red_circle:',
			'dep_ok' => ':green_circle:',
			'dep_progress' => ':orange_circle:',
			'dep_nok' => ':red_circle:',
			'batterie_ok' => ':green_circle:',
			'batterie_progress' => ':orange_circle:',
			'batterie_nok' => ':red_circle:'
		);
		
		$emojiArray = ($reset == 1) ? $default : config::byKey('emoji', 'discordlink', $default);
		config::save('emoji', $emojiArray, 'discordlink');
	}

	public static function updateInfo() {
		sleep(2);
		static::updateObject();
		static::setChannel();
	}

	public static function emojiConvert($_text): string
	{
		$_returntext = '';
		$textsplit = explode(" ", $_text);
		foreach ($textsplit as $value) {
			if (substr($value,0,4) === "emo_") {
				$emoji = discordlink::getIcon(str_replace("emo_","",$value));
				$_returntext .= $emoji;
			} else {
				$_returntext .= $value;
			}
			$_returntext .= " ";
		}
		return $_returntext;
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
			if ($eqLogic->getConfiguration('deamoncheck', 0) === 1) {
				static::executeCronIfDue($eqLogic, $eqLogic->getConfiguration('autorefreshDeamon'), 'deamonInfo', 'DeamonCheck', $dateRun, $options);
			}
			
			// Vérification dépendances
			if ($eqLogic->getConfiguration('depcheck', 0) === 1) {
				static::executeCronIfDue($eqLogic, $eqLogic->getConfiguration('autorefreshDependancy'), 'dependanceInfo', 'DepCheck', $dateRun, $options);
			}
			
			// Vérification connexions utilisateurs
			if ($eqLogic->getConfiguration('connectionCheck', 0) === 1) {
				$cmd = $eqLogic->getCmd('action', 'LastUser');
				if (is_object($cmd)) {
					log::add('discordlink', 'debug', 'Vérification connexion utilisateur pour ' . $eqLogic->getName());
					$cmd->execCmd($options);
				}
			}
		}
	}
	/*     * ***********************Methode static*************************** */

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

    /*     * *********************Méthodes d'instance************************* */

	// Dépendances gérées nativement via packages.json (Node.js géré par Jeedom Core)


	public static function deamon_info() {
		$return = array(
			'log' => 'discordlink_node',
			'state' => 'nok',
			'launchable' => 'nok'
		);

		$pidCount = (int)trim(shell_exec('pgrep -f ' . escapeshellarg('discordlink.js') . ' 2>/dev/null | wc -l'));
		if ($pidCount > 0) {
			$return['state'] = 'ok';
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
			'nice -n 19 node %s/discordlink.js %s %s %s %s %s %s',
			escapeshellarg(realpath(dirname(__FILE__) . '/../../resources')),
			escapeshellarg(network::getNetworkAccess('internal')),
			escapeshellarg(config::byKey('Token', 'discordlink')),
			escapeshellarg(log::getLogLevel('discordlink')),
			escapeshellarg(network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/discordlink/core/api/jeeDiscordlink.php?apikey=' . $apiKey),
			escapeshellarg($apiKey),
			escapeshellarg(config::byKey('joueA', 'discordlink', 'Travailler main dans la main avec votre Jeedom'))
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
		log::add('discordlink', 'info', 'Arrêt du service discordlink');
		
		// Arrêt gracieux via API HTTP
		try {
			$requestHttp = new com_http('http://' . config::byKey('internalAddr') . ':3466/stop');
			$requestHttp->setNoReportError(true);
			$requestHttp->setAllowEmptyReponse(true);
			$requestHttp->exec(1, 1);
		} catch (Exception $e) {
			log::add('discordlink', 'debug', 'Erreur arrêt gracieux : ' . $e->getMessage());
		}
		
		sleep(3);
		
		// Vérification et kill forcé si le processus est toujours actif
		$processPattern = escapeshellarg('discordlink.js');
		$pidCount = (int)trim(shell_exec('pgrep -f ' . $processPattern . ' 2>/dev/null | wc -l'));
		
		if ($pidCount > 0) {
			// SIGTERM d'abord
			exec('pkill -f ' . $processPattern . ' 2>&1');
			sleep(1);
			
			// Vérifier si toujours actif, puis SIGKILL
			if (static::deamon_info()['state'] == 'ok') {
				exec('pkill -9 -f ' . $processPattern . ' 2>&1');
				log::add('discordlink', 'warning', 'Arrêt forcé du démon requis (SIGKILL)');
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

	public static function CreateCmd() {

		$eqLogics = eqLogic::byType('discordlink');
		foreach ($eqLogics as $eqLogic) {

			$TabCmd = array(
				'sendMsg'=>array('reqplug' => '0', 'Libelle'=>'Envoi message', 'Type'=>'action', 'SubType' => 'message','request'=> 'sendMsg?message=#message#', 'visible' => 1, 'Template' => 'discordlink::message'),
				'sendMsgTTS'=>array('reqplug' => '0','Libelle'=>'Envoi message TTS', 'Type'=>'action', 'SubType' => 'message', 'request'=> 'sendMsgTTS?message=#message#', 'visible' => 1, 'Template' => 'discordlink::message'),
				'sendEmbed'=>array('reqplug' => '0','Libelle'=>'Envoi message évolué', 'Type'=>'action', 'SubType' => 'message', 'request'=> 'sendEmbed?color=#color#&title=#title#&url=#url#&description=#description#&field=#field#&countanswer=#countanswer#&footer=#footer#&timeout=#timeout#&quickreply=#quickreply#&defaultColor=#defaultColor#', 'visible' => 0),
				'sendFile'=>array('reqplug' => '0','Libelle'=>'Envoi fichier', 'Type'=>'action', 'SubType' => 'message', 'request'=> 'sendFile?patch=#patch#&name=#name#&message=#message#', 'visible' => 0),
				'deleteMessage'=>array('reqplug' => '0','Libelle'=>'Supprime les messages du channel', 'Type'=>'action', 'SubType'=>'other','request'=>'deleteMessage?null', 'visible' => 0),
				'deamonInfo'=>array('reqplug' => '0','Libelle'=>'Etat des démons', 'Type'=>'action', 'SubType'=>'other','request'=>'deamonInfo?null', 'visible' => 1),
				'dependanceInfo'=>array('reqplug' => '0','Libelle'=>'Etat des dépendances', 'Type'=>'action', 'SubType'=>'other','request'=>'dependanceInfo?null', 'visible' => 1),
				'globalSummary'=>array('reqplug' => '0','Libelle'=>'Résumé général', 'Type'=>'action', 'SubType'=>'other','request'=>'globalSummary?null', 'visible' => 1),
				'objectSummary'=>array('reqplug' => '0','Libelle'=>'Résumé par objet', 'Type'=>'action', 'SubType'=>'select','request'=>'objectSummary?null', 'visible' => 1),
				'batteryinfo'=>array('reqplug' => '0','Libelle'=>'Résumé des batteries', 'Type'=>'action', 'SubType'=>'other','request'=>'batteryinfo?null', 'visible' => 1),
				'centreMsg'=>array('reqplug' => '0','Libelle'=>'Centre de messages', 'Type'=>'action', 'SubType'=>'other','request'=>'centreMsg?null', 'visible' => 1),
				'LastUser'=>array('reqplug' => '0','Libelle'=>'Dernière Connexion utilisateur', 'Type'=>'action', 'SubType'=>'other','request'=>'LastUser?null', 'visible' => 1),
				'1oldmsg'=>array('reqplug' => '0','Libelle'=>'Dernier message', 'Type'=>'info', 'SubType'=>'string', 'visible' => 1),
				'2oldmsg'=>array('reqplug' => '0','Libelle'=>'Avant dernier message', 'Type'=>'info', 'SubType'=>'string', 'visible' => 1),
				'3oldmsg'=>array('reqplug' => '0','Libelle'=>'Avant Avant dernier message', 'Type'=>'info', 'SubType'=>'string', 'visible' => 1)
			);

			//Chaque commande
			$Order = 0;
			foreach ($TabCmd as $CmdKey => $Cmd){
				// Vérifier si le plugin requis est actif ("0" = pas de dépendance)
				if ($Cmd['reqplug'] == "0" || discordlink::testPlugin($Cmd['reqplug']))  {
					$Cmddiscordlink = $eqLogic->getCmd(null, $CmdKey);
					if (!is_object($Cmddiscordlink) ) {
						$Cmddiscordlink = new discordlinkCmd();
						$Cmddiscordlink->setName($Cmd['Libelle']);
						$Cmddiscordlink->setIsVisible($Cmd['visible']);
						$Cmddiscordlink->setType($Cmd['Type']);
						$Cmddiscordlink->setSubType($Cmd['SubType']);
					}
					$Cmddiscordlink->setEqLogic_id($eqLogic->getId());
					$Cmddiscordlink->setLogicalId($CmdKey);
					if ($Cmd['Type'] == "action" && $CmdKey != "deamonInfo") {
						$Cmddiscordlink->setConfiguration('request', $Cmd['request']);
						$Cmddiscordlink->setConfiguration('value', 'http://' . config::byKey('internalAddr') . ':3466/' . $Cmd['request'] . "&channelID=" . $eqLogic->getConfiguration('channelId'));
					}
					if ($Cmd['Type'] == "action" && $CmdKey == "deamonInfo") {
						$Cmddiscordlink->setConfiguration('request', $Cmd['request']);
						$Cmddiscordlink->setConfiguration('value', $Cmd['request']);
					}

					$Cmddiscordlink->setDisplay('generic_type','GENERIC_INFO');
					if (!empty($Cmd['Template'])) {
						$Cmddiscordlink->setTemplate("dashboard", $Cmd['Template']);
						$Cmddiscordlink->setTemplate("mobile", $Cmd['Template']);
					}
					$Cmddiscordlink->setOrder($Order);
					$Cmddiscordlink->setDisplay('message_placeholder', 'Message à envoyer sur Discord');
					$Cmddiscordlink->setDisplay('forceReturnLineBefore', true);
					$Cmddiscordlink->save();
					$Order++;
				}
			}
		}
	}

	public function getDefaultColor() {
		return $this->getConfiguration('defaultColor', self::DEFAULT_COLOR);	
	}

    public function postSave() {
		static::CreateCmd();
		static::updateObject();
	}

    public function preUpdate() {

    }

    public function postUpdate() {
		discordlink::CreateCmd();
    }

    public function preRemove() {

    }

    public function postRemove() {

    }

	public static function updateObject() {
		$objects = jeeObject::all();
		if (empty($objects)) return;
		
		$listValue = implode(';', array_map(function($object) {
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

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

	public static function getLastUserConnections() {
		$message = "";
		$userConnect_list_new = '';
		$userConnect_list = '';
		$onlineCount = 0;
		$daysBeforeUserRemoval = 61;
		$cronOk = false;
		$cron=65;
		$timeNow = date("Y-m-d H:i:s");
		$maxLine = log::getConfig('maxLineLog');
		// Récupération du niveau de log du log Connection (//100=debug | 200=info | 300=warning | 400=erreur=defaut | 1000=none)
		$level = log::getLogLevel('connection');
		$levelName = log::convertLogLevel($level);

		//Add Emoji
		$emo_warning = discordlink::addEmoji("lastUser_warning",":warning:");
		$emo_mag_right = discordlink::addEmoji("lastUser_mag_right",":mag_right:");
		$emo_mag = discordlink::addEmoji("lastUser_mag",":mag:");
		$emo_check = discordlink::addEmoji("lastUser_check",":white_check_mark:");
		$emo_internet = discordlink::addEmoji("lastUser_internet",":globe_with_meridians:");
		$emo_connecter = discordlink::addEmoji("lastUser_connecter",":green_circle:");
		$emo_deconnecter = discordlink::addEmoji("lastUser_deconnecter",":red_circle:");
		$emo_silhouette = discordlink::addEmoji("lastUser_silhouette",":busts_in_silhouette:");


		if($level > 200){
			$logLevelWarning = "\n"."\n".$emo_warning."Plus d'informations ? ".$emo_warning."\n"."veuillez mettre le log **connection** sur **info** dans *Configuration/Logs* (niveau actuel : **".$levelName."**)";
		} else {
			$logLevelWarning = "";
		}
		$offlineDelay = 10;
		$var_nbUser = 0;
		foreach (user::all() as $user) {
			$var_nbUser++;
				$userConnect_Date[$var_nbUser] = $user->getOptions('lastConnection');
			if($userConnect_Date[$var_nbUser] == ""){
				$userConnect_Date[$var_nbUser] = "1970-01-01 00:00:00";
			}
			if(strtotime($timeNow) - strtotime($userConnect_Date[$var_nbUser]) < $offlineDelay*60){
				$userConnect_Statut[$var_nbUser] = 'en ligne';
			}else{
				$userConnect_Statut[$var_nbUser] = 'hors ligne';
			}
			$userConnect_Name[$var_nbUser] = $user->getLogin();
			if($userConnect_list != ''){
				$userConnect_list = $userConnect_list.'|';
			}
			$userConnect_list .= $userConnect_Name[$var_nbUser].';'.$userConnect_Date[$var_nbUser].';'.$userConnect_Statut[$var_nbUser];
		}
		
		$userConnect_list_new = '';
		// Récupération des lignes du log Connection
		$logConnection_list = log::get('connection', 0, $maxLine);
		$plageRecherche = date("Y-m-d H:i:s", strtotime($timeNow)-$cron);
		$log_nbUser = 0;
		$logConnection_Name_tmp = '';
		if (is_array($logConnection_list)) {
			foreach ($logConnection_list as $value) {
				$logConnection = explode("]", $value);
				$logConnection = substr($logConnection[0], 1);
				if (strtotime($timeNow) - strtotime($logConnection) > $cron) {
					if ($log_nbUser == 0) {
						$message = "\n" . "**Pas de connexion** ces **" . $cron . "** dernières minutes !";
					}
					break;
				} else {
					$log_nbUser++;
					$logConnection_Date[$log_nbUser] = $logConnection;
					$logConnection = explode(" : ", $value);
					$logConnection_Name[$log_nbUser] = strtolower($logConnection[2]);
					if (strpos($logConnection[1], 'clef') !== false) {
						$logConnection_Type[$log_nbUser] = 'clef';
					} elseif (strpos($logConnection[1], 'API') !== false) {
						$logConnection_Type[$log_nbUser] = 'api';
					} else {
						$logConnection_Type[$log_nbUser] = 'navigateur';
					}
					if ($log_nbUser == 1) {
						$message .= "\n" . $emo_mag_right . "__Récapitulatif de ces " . $cron . " dernières secondes :__ " . $emo_mag;
					}
					$onlineCount++;
					$message .= "\n" . $emo_check . "**" . $logConnection_Name[$log_nbUser] . "** s'est connecté par **" . $logConnection_Type[$log_nbUser] . "** à **" . date("H", strtotime($logConnection_Date[$log_nbUser])) . "h" . date("i", strtotime($logConnection_Date[$log_nbUser])) . "**";
					$cronOk = true;
					$userNum = 0;
					$foundCount = 0;
					if (strpos($logConnection_Name_tmp, $logConnection_Name[$log_nbUser]) === false) {
					} else {
						continue;
					}
					$logConnection_Name_tmp = $logConnection_Name[$log_nbUser];
					foreach ($userConnect_Name as $userName) {
						$userNum++;
						if ($logConnection_Name[$log_nbUser] == $userConnect_Name[$userNum]) {        ///Utilisateur déjà enregistré
							$foundCount++;
							if ($userConnect_Statut[$userNum] == 'hors ligne') {
								$userConnect_Date[$userNum] = $logConnection_Date[$log_nbUser];
								$userConnect_Statut[$userNum] = 'en ligne';
							}
						}
						if ($userConnect_list_new != '') {
							$userConnect_list_new = $userConnect_list_new . '|';
						}
						$userConnect_list_new .= $userConnect_Name[$userNum] . ';' . $userConnect_Date[$userNum] . ';' . $userConnect_Statut[$userNum];
					}
					if ($foundCount == 0) {                                                                //Utilisateur nouveau
						$userConnect_Name[$userNum] = $logConnection_Name[$log_nbUser];
						$userConnect_Date[$userNum] = $logConnection_Date[$log_nbUser];
						$userConnect_Statut[$userNum] = 'en ligne';
						if ($userConnect_list_new != '') {
							$userConnect_list_new = $userConnect_list_new . '|';
						}
						$userConnect_list_new .= $userConnect_Name[$userNum] . ';' . $userConnect_Date[$userNum] . ';' . $userConnect_Statut[$userNum];
					}
					$userConnect_list = $userConnect_list_new;
				}
			}
		}
		
		$sessions = listSession();
		$sessionCount=count($sessions);												//nombre d'utilisateur en session actuellement
		
		$message .= "\n"."\n".$emo_mag_right."__Récapitulatif des sessions actuelles :__ ".$emo_mag;
		// Parcours des sessions pour vérifier le statut et le nombre de sessions
		$userNum=0;
		$userConnect_list_new = '';
		foreach($userConnect_Name as $value){
			$userNum++;
			$userSession=0;
			$foundCount = 0;
			$userConnect_Statut[$userNum] = 'hors ligne';
			$userConnect_IP[$userNum] = '';

			foreach($sessions as $id => $session){
				$userSession++;
				
				$userDelai = strtotime(date("Y-m-d H:i:s")) - strtotime($session['datetime']);

				if($userConnect_Name[$userNum] == $session['login']){
					if($userDelai < $offlineDelay*60){
						$foundCount++;
						$onlineCount++;
						$userConnect_Statut[$userNum] = 'en ligne';
						$userConnect_IP[$userNum] .= "\n"."-> ".$emo_internet." IP : ".$session['ip'];
					}else{
					}
				}			
			}
			if(date("Y-m-d",strtotime($userConnect_Date[$userNum])) == date("Y-m-d",strtotime($timeNow))){
				$heures = date("H",strtotime($userConnect_Date[$userNum]));
				$minutes = date("i",strtotime($userConnect_Date[$userNum]));
				$date = $heures."h".$minutes;
			}else{
				$dayName = date_fr(date("l", strtotime($userConnect_Date[$userNum])));
				$numJour = date("d",strtotime($userConnect_Date[$userNum]));
				$monthName = date_fr(date("F", strtotime($userConnect_Date[$userNum])));
				$numAnnee = date("Y",strtotime($userConnect_Date[$userNum]));
				$heures = date("H",strtotime($userConnect_Date[$userNum]));
				$minutes = date("i",strtotime($userConnect_Date[$userNum]));
				$date = $dayName." ".$numJour." ".$monthName." ".$numAnnee."** à **".$heures."h".$minutes;
			}
			if($foundCount > 0){
				$message .= "\n".$emo_connecter." **".$userConnect_Name[$userNum]."** est **en ligne** depuis **".$date."**";
				$message .= $userConnect_IP[$userNum];
			}else{
				if(strtotime($timeNow) - strtotime($userConnect_Date[$userNum]) < ($daysBeforeUserRemoval*24*60*60)){
					$message .= "\n".$emo_deconnecter." **".$userConnect_Name[$userNum]."** est **hors ligne** (dernière connexion **".$date."**)";
				}
			}
			if($userConnect_list_new != ''){
				$userConnect_list_new = $userConnect_list_new.'|';
			}
			$userConnect_list_new .= $userConnect_Name[$userNum].';'.$userConnect_Date[$userNum].';'.$userConnect_Statut[$userNum];
			$userConnect_list=$userConnect_list_new;
		}
		
		// Préparation des tags de notification
		$title = $emo_silhouette.'CONNEXIONS '.$emo_silhouette;
		return array(
			'title'=>$title,
			'message'=>$message.$logLevelWarning,
			'nbEnLigne'=>$onlineCount,
			'cronOk'=>$cronOk
		);
	}

    /*     * **********************Getteur Setteur*************************** */
}
class discordlinkCmd extends cmd {

		/*     * *************************Attributs****************************** */


		/*     * ***********************Methode static*************************** */


		/*     * *********************Methode d'instance************************* */

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
				if ($request != 'truesendwithembed') {
					log::add('discordlink', 'debug', 'Envoi de ' . $request);
					$request_http = new com_http($request);
					$request_http->setAllowEmptyReponse(true);//Autorise les réponses vides
					if ($this->getConfiguration('noSslCheck') == 1) $request_http->setNoSslCheck(true);
					if ($this->getConfiguration('doNotReportHttpError') == 1) $request_http->setNoReportError(true);
					if (isset($_options['speedAndNoErrorReport']) && $_options['speedAndNoErrorReport'] == true) {// option non activée
						$request_http->setNoReportError(true);
						$request_http->exec(0.1, 1);
						return;
					}
					$result = $request_http->exec($this->getConfiguration('timeout', 6), $this->getConfiguration('maxHttpRetry', 1));//Time out à 3s 3 essais
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
		
		$cmdANDarg = explode('?', $this->getConfiguration('request'), 2);
		$command = $cmdANDarg[0];
		
		$commandMap = array(
			'sendMsg' => 'build_ControledeSliderSelectMessage',
			'sendMsgTTS' => 'build_ControledeSliderSelectMessage',
			'sendEmbed' => 'build_ControledeSliderSelectEmbed',
			'sendFile' => 'build_ControledeSliderSelectFile',
			'deamonInfo' => 'build_deamonInfo',
			'dependanceInfo' => 'build_dependanceInfo',
			'globalSummary' => 'build_globalSummary',
			'batteryinfo' => 'build_baterieglobal',
			'objectSummary' => 'build_objectSummary',
			'centreMsg' => 'build_centreMsg',
			'LastUser' => 'build_LastUser',
			'deleteMessage' => 'clearChannel?'
		);
		
		if (isset($commandMap[$command])) {
			$request = is_callable(array($this, $commandMap[$command])) 
				? $this->{$commandMap[$command]}($_options) 
				: $commandMap[$command];
		} else {
			$request = '';
		}
		
		if ($request == 'truesendwithembed') return $request;
		
		$request = scenarioExpression::setTags($request);
		if (trim($request) == '') {
			throw new Exception(__('Commande inconnue ou requête vide : ', __FILE__) . print_r($this, true));
		}
		
		$channelID = str_replace('_player', '', $this->getEqLogic()->getConfiguration('channelId'));
		return 'http://' . config::byKey('internalAddr') . ':3466/' . $request . '&channelID=' . $channelID;
	}

	private function build_ControledeSliderSelectMessage($_options = array(), $default = "Une erreur est survenue") {
		$message = isset($_options['message']) && $_options['message'] != '' ? $_options['message'] : $default;
		$message = str_replace('|', "\n", $message);
		$request = str_replace('#message#', urlencode(self::decodeTexteAleatoire($message)), $this->getConfiguration('request'));
		log::add('discordlink_node', 'info', '---->RequestFinale:' . $request);
		return $request;
	}

		private function build_ControledeSliderSelectFile($_options = array(), $default = "Une erreur est survenu") {
			$patch = "null";
			$nameFile = "null";
			$message = "null";

			$request = $this->getConfiguration('request');
			if ((isset($_options['patch'])) && ($_options['patch'] == "")) $_options['patch'] = $default;
			if (!(isset($_options['patch']))) $_options['patch'] = "";

			if (isset($_options['files']) && is_array($_options['files'])) {
				foreach ($_options['files'] as $file) {
					if (version_compare(phpversion(), '5.5.0', '>=')) {
						$patch = $file;
						$files = new CurlFile($file);
						$nameexplode = explode('.',$files->getFilename());
						log::add('discordlink', 'info', $_options['title'].' taille : '.$nameexplode[sizeof($nameexplode)-1]);
						$nameFile = (isset($_options['title']) ? $_options['title'].'.'.$nameexplode[sizeof($nameexplode)-1] : $files->getFilename());
					}
				}
				$message = $_options['message'];

			} else {
				$patch = $_options['patch'];
				$nameFile = $_options['Name_File'];
			}

			$request = str_replace(array('#message#'),
			array(urlencode(self::decodeTexteAleatoire($message))), $request);
			$request = str_replace(array('#name#'),
			array(urlencode(self::decodeTexteAleatoire($nameFile))), $request);
			$request = str_replace(array('#patch#'),
			array(urlencode(self::decodeTexteAleatoire($patch))), $request);

			log::add('discordlink_node', 'info', '---->RequestFinale:'.$request);
			return $request;
		}

		private function build_ControledeSliderSelectEmbed($_options = array(), $default = "Une erreur est survenue") {

			$request = $this->getConfiguration('request');

			$title = "null";
			$url = "null";
			$description = "null";
			$footer = "null";
			$field = "null";
			$colors = "null";
			$timeout = "null";
			$countanswer = "null";
			$quickreply = "null";
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
					$choix = [":regional_indicator_a:", ":regional_indicator_b:", ":regional_indicator_c:", ":regional_indicator_d:", ":regional_indicator_e:", ":regional_indicator_f:", ":regional_indicator_g:", ":regional_indicator_h:", ":regional_indicator_i:", ":regional_indicator_j:", ":regional_indicator_k:", ":regional_indicator_l:", ":regional_indicator_m:", ":regional_indicator_n:", ":regional_indicator_o:", ":regional_indicator_p:", ":regional_indicator_q:", ":regional_indicator_r:", ":regional_indicator_s:", ":regional_indicator_t:", ":regional_indicator_u:", ":regional_indicator_v:", ":regional_indicator_w:", ":regional_indicator_x:", ":regional_indicator_y:", ":regional_indicator_z:"];
					while ($a < count($answer)) {
						$description .= $choix[$a] . " : " . $answer[$a];
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
				if (!empty($_options['footer'])) $footer = $_options['footer'];
				if (!empty($_options['colors'])) $colors = $_options['colors'];
				if (!empty($_options['field'])) $field = json_encode($_options['field']);
				if (!empty($_options['quickreply'])) $quickreply = $_options['quickreply'];
			}

			$description = discordlink::emojiConvert($description);
		log::add('discordlink', 'debug', 'description : ' . $description);
		$description = str_replace('|', "\n", $description);

		// Remplacement des variables
		$replacements = array(
			'#title#' => $title,
			'#url#' => $url,
			'#description#' => $description,
			'#footer#' => $footer,
			'#countanswer#' => $countanswer,
			'#field#' => $field,
			'#color#' => $colors,
			'#defaultColor#' => $defaultColor,
			'#timeout#' => $timeout,
			'#quickreply#' => $quickreply
		);
		
		foreach ($replacements as $key => $value) {
			$request = str_replace($key, urlencode(self::decodeTexteAleatoire($value)), $request);
		}

		log::add('discordlink_node', 'info', '---->RequestFinale:' . $request);
		}

		public static function decodeTexteAleatoire($_text) {
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

		public function build_deamonInfo($_options = array()) {
			$message='';
			$colors = '#00ff08';

			foreach(plugin::listPlugin(true) as $plugin){
				if($plugin->getHasOwnDeamon() && config::byKey('deamonAutoMode', $plugin->getId(), 1) == 1) {
					$deamon_info = $plugin->deamon_info();
					if ($deamon_info['state'] != 'ok') {
						$message .='|'.discordlink::getIcon("deamon_nok").$plugin->getName().' ('.$plugin->getId().')';
						if ($colors != '#ff0000') $colors = '#ff0000';
					} else {
						$message .='|'.discordlink::getIcon("deamon_ok").$plugin->getName().' ('.$plugin->getId().')';
					}

				}
			}

			if (isset($_options['cron']) AND $colors == '#00ff08') return 'truesendwithembed';
			$message=str_replace("|","\n",$message);
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('title'=>'Etat des démons', 'description'=> $message, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
			$cmd->execCmd($_options);
			return 'truesendwithembed';
		}

		public function build_dependanceInfo($_options = array()) {
			$message='';
			$colors = '#00ff08';

			foreach(plugin::listPlugin(true) as $plugin){
				if($plugin->getHasDependency()) {
					$dependency_info = $plugin->dependancy_info();
					if ($dependency_info['state'] == 'ok') {
						$message .='|'.discordlink::getIcon("dep_ok").$plugin->getName().' ('.$plugin->getId().')';
					} elseif ($dependency_info['state'] == 'in_progress') {
						$message .='|'.discordlink::getIcon("dep_progress").$plugin->getName().' ('.$plugin->getId().')';
						if ($colors == '#00ff08') $colors = '#ffae00';
					} else {
						$message .='|'.discordlink::getIcon("dep_nok").' ('.$plugin->getId().')';
						if ($colors != '#ff0000') $colors = '#ff0000';
					}

				}
			}

			if (isset($_options['cron']) && $colors == '#00ff08') return 'truesendwithembed';
			$message=str_replace("|","\n",$message);
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('title'=>'Etat des dépendances', 'description'=> $message, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
			$cmd->execCmd($_options);
			return 'truesendwithembed';
		}

		public function build_globalSummary($_options = array()) {

			$objects = jeeObject::all();
			$def = config::byKey('object:summary');
			if (!is_array($def)) {
				log::add('discordlink', 'error', 'Configuration object:summary invalide ou non définie');
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('title'=>'Erreur', 'description'=> '⚠️ Configuration des résumés non initialisée. Veuillez vérifier votre configuration Jeedom.', 'colors'=> '#ff0000');
				$cmd->execCmd($_options);
				return 'truesendwithembed';
			}
			$values = array();
			$message='';
			foreach ($def as $key => $value) {
				$result ='';
				$result = jeeObject::getGlobalSummary($key);
				if ($result == '') continue;
				$message .='|'.discordlink::getIcon($key).' *** '. $result.' '.$def[$key]['unit'] .' ***		('.$def[$key]['name'].')';
			}
				$message=str_replace("|","\n",$message);
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('title'=>'Résumé général', 'description'=> $message, 'colors'=> '#0033ff', 'footer'=> 'By DiscordLink');
				$cmd->execCmd($_options);

			return 'truesendwithembed';
		}

		public function build_baterieglobal($_options = array()) {
			$message='null';
			$colors = '#00ff08';
			$seuil_alert = 30;
			$seuil_critique = 10;
			$nb_alert = 0;
			$nb_critique = 0;
			$nb_battery = 0;
			$nb_total = 0;
		$max_length = 1800; // Limite Discord ~2000, on garde une marge

		$eqLogics = eqLogic::all(true);
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');

		$list_battery = '';
		foreach($eqLogics as $eqLogic) {
			$nb_total++;
			$battery = $eqLogic->getStatus('battery');
			
			if (!is_numeric($battery)) continue;
			
			$nb_battery++;
			$name = substr($eqLogic->getHumanName(), strrpos($eqLogic->getHumanName(), '[',-1) + 1, -1);
			
			if ($battery <= $seuil_critique) {
				$line = "\n" . discordlink::getIcon("batterie_nok") . $name . ' => __***' . $battery . "%***__";
				$nb_critique++;
				$colors = '#ff0000';
			} elseif ($battery <= $seuil_alert) {
				$line = "\n" . discordlink::getIcon("batterie_progress") . $name . ' =>  __***' . $battery . "%***__";
				$nb_alert++;
				if ($colors == '#00ff08') $colors = '#ffae00';
			} else {
				$line = "\n" . discordlink::getIcon("batterie_ok") . $name . ' =>  __***' . $battery . "%***__";
			}
			
			// Vérifier si l'ajout de cette ligne dépasserait la limite
			if (strlen($list_battery . $line) > $max_length) {
				$_options = array('title'=>'Résumé Batteries : ', 'description'=> str_replace("|","\n", $list_battery), 'colors'=> $colors, 'footer'=> 'By DiscordLink');
				$cmd->execCmd($_options);
				$list_battery = '';
			}
			
			$list_battery .= $line;
		}

		$_options = array('title'=>'Résumé Batteries : ', 'description'=> str_replace("|","\n", $list_battery), 'colors'=> $colors, 'footer'=> 'By DiscordLink');
		$cmd->execCmd($_options);
		
		$message2 = "Batterie en alerte : __***" . $nb_alert . "***__\n Batterie critique : __***".$nb_critique."***__";
		$_options2 = array('title'=>'Résumé Batterie', 'description'=> str_replace("|","\n", $message2), 'colors'=> $colors, 'footer'=> 'By DiscordLink');
		$cmd->execCmd($_options2);
		
		return 'truesendwithembed';
	}

	public function build_objectSummary($_options = array()) {

		$idobject = $_options['select'];
		log::add('discordlink', 'debug', 'idobject : '.$idobject);
		$object = jeeObject::byId($idobject);
			$def = config::byKey('object:summary');
			if (!is_array($def)) {
				log::add('discordlink', 'error', 'Configuration object:summary invalide ou non définie');
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('title'=>'Erreur', 'description'=> '⚠️ Configuration des résumés non initialisée. Veuillez vérifier votre configuration Jeedom.', 'colors'=> '#ff0000');
				$cmd->execCmd($_options);
				return 'truesendwithembed';
			}
			$message='';
			foreach ($def as $key => $value) {
				$result = '';
				$result = $object->getSummary($key);
				if ($result == '') continue;
				$message .='|'.discordlink::getIcon($key).' *** '. $result.' '.$def[$key]['unit'] .' ***		('.$def[$key]['name'].')';
			}
				$message=str_replace("|","\n",$message);
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('title'=>'Résumé : '.$object->getname(), 'description'=> $message, 'colors'=> '#0033ff', 'footer'=> 'By DiscordLink');
				$cmd->execCmd($_options);

			return 'truesendwithembed';
		}

	public function build_centreMsg($_options = array()) {
		// Parcours de tous les Updates
		$listUpdate = "";
		$updateCount = 0;
		$msgBloq = "";
		$blockedUpdateCount = 0;
		foreach (update::all() as $update) {
			$monUpdate = $update->getName();
			$statusUpdate = strtolower($update->getStatus());
			$configUpdate = $update->getConfiguration('doNotUpdate');
			
			if ($configUpdate == 1) {
				$configUpdate = " **(MaJ bloquée)**";
				$blockedUpdateCount++;
			} else {
				$configUpdate = "";
			}
			
			if ($statusUpdate == "update") {
				$updateCount++;
				$listUpdate .= ($listUpdate == "" ? "" : "\n") . $updateCount . "- " . $monUpdate . $configUpdate;
			}
		}
		
		// Message de blocage
		$msgBloq = $blockedUpdateCount == 0 ? "" : " (dont **" . $blockedUpdateCount . "** bloquée" . ($blockedUpdateCount > 1 ? "s" : "") . ")";

		// Message selon le nombre de mises à jour
		if ($updateCount == 0) {
			$msg = "*Vous n'avez pas de mises à jour en attente !*";
		} else {
			$pluriel = $updateCount > 1 ? "s" : "";
			$msg = "*Vous avez **" . $updateCount . "** mise" . $pluriel . " à jour en attente" . $msgBloq . " :*\n" . $listUpdate;
		}

		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title'=>':gear: CENTRE DE MISES A JOUR :gear:', 'description'=> $msg, 'colors'=> '#ff0000', 'footer'=> 'By jcamus86');
		$cmd->execCmd($_options);

		// -------------------------------------------------------------------------------------- //
		$msg = array();
		$nbMsg = 0;
		$nbMsgMax = 5;		//Nombre de messages par bloc de notification
		$MsgBloc = 1;
		$listMessage = message::all();
		foreach ($listMessage as $message){
			$nbMsg++;
			if (!($nbMsg <= $nbMsgMax)){
				$nbMsg = 1;
				$MsgBloc = $MsgBloc + 1;
			}

			$msg[$MsgBloc] .= "[".$message->getDate()."]";
			$msg[$MsgBloc] .= " (".$message->getPlugin().") :";
			$msg[$MsgBloc] .= "\n" ;
			($message->getAction() != "") ? $msg[$MsgBloc] .= " (Action : ".$message->getAction().")" : null;
			$msg[$MsgBloc] .= " ".$message->getMessage()."\n";
			$msg[$MsgBloc] .= "\n" ;
			$msg[$MsgBloc] = html_entity_decode($msg[$MsgBloc], ENT_QUOTES | ENT_HTML5);
		}
				
		if ($nbMsg == 0) {
			$_options = array('title'=>':clipboard: CENTRE DE MESSAGES :clipboard:', 'description'=> "*Le centre de message est vide !*", 'colors'=> '#ff8040', 'footer'=> 'By Jcamus86');
			$cmd->execCmd($_options);
		} else {
			$i = 0;
			foreach ($msg as $value) {
				$i++;
				$_options = array('title'=>':clipboard: CENTRE DE MESSAGES ' . $i . '/' . count($msg) . ' :clipboard:', 'description'=> $value, 'colors'=> '#ff8040', 'footer'=> 'By Jcamus86');
				$cmd->execCmd($_options);
			}
		}

		return 'truesendwithembed';
	}

	public function build_LastUser($_options = array()) {
		$result = discordlink::getLastUserConnections();
		if (isset($_options['cron']) && !$result['cronOk']) return 'truesendwithembed';
		
		$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
		$_options = array('title'=>$result['title'], 'description'=> str_replace("|","\n", $result['message']), 'colors'=> '#ff00ff', 'footer'=> 'By Yasu et Jcamus86');
		$cmd->execCmd($_options);
		return 'truesendwithembed';
	}

	public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {
		if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
		
		list($command, ) = explode('?', $this->getConfiguration('request'), 2);
		$data = '';
		if ($command == 'sendMsg')
			$data = getTemplate('core', 'scenario', 'cmd.sendMsg', 'discordlink');
		if ($command == 'sendMsgTTS')
			$data = getTemplate('core', 'scenario', 'cmd.sendMsgtts', 'discordlink');
		if ($command == 'sendEmbed')
			$data = getTemplate('core', 'scenario', 'cmd.sendEmbed', 'discordlink');
		if ($command == 'sendFile')
			$data = getTemplate('core', 'scenario', 'cmd.sendFile', 'discordlink');
		
		/** @var discordlink $eqLogic */
		$eqLogic = $this->getEqLogic();
		$defaultColor = $eqLogic->getDefaultColor();
		$replace = [
			'#defaultColor#' => $defaultColor,
			'#defaultTitle#' => '',
			'#defaultUrl#' => '',
			'#defaultFooter#' => '',
		];
		$data = str_replace(array_keys($replace), array_values($replace), $data);
		
		if (!is_null($data)) {
			if (version_compare(jeedom::version(),'4.2.0','>=')) {
				if(!is_array($data)) return array('template' => $data, 'isCoreWidget' => false);
			} else return $data;
		}
		return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
	}
	/*     * **********************Getteur Setteur*************************** */
}
?>
