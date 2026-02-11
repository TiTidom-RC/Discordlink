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

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'discordlink')) {
	echo __('Vous n\'êtes pas autorisé à effectuer cette action', __FILE__);
	log::add('discordlink', 'debug',  'Clé Plugin Invalide');
	die();
}

if (init('test') != '') {
	echo 'OK';
	die();
}

$rawInput = file_get_contents("php://input");
$name = init('name');
log::add('discordlink', 'debug',  'Réception données sur jeediscordlink [' . $name . ']');

$result = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
	log::add('discordlink', 'error', 'Erreur décodage JSON : ' . json_last_error_msg());
	log::add('discordlink', 'debug', 'Payload reçu : ' . $rawInput);
}


if (!is_array($result)) {
	log::add('discordlink', 'debug', 'Format Invalide');
	die();
}

$logicalId = $result['channelId'] . "_player";

$discordEquipment = eqLogic::byLogicalId($result['channelId'], 'discordlink');

switch ($name) {

	case 'createJeedomMessage':
		message::add('discordlink', $result['msg']);
		break;

	case 'messageReceived':
		getDeviceAndUpdate("lastMessage", $result['message'], 'lastMessage', $result['channelId'], $result['userId']);
		break;
	case 'ASK':
		getASK($result['response'], $result['channelId'], $result['request']);
		break;

	case 'slashCommand':
		log::add('discordlink', 'debug', 'SlashCommand reçue : ' . $result['request'] . ' (User: ' . $result['username'] . ', ID: ' . $result['userId'] . ')');

		if (!is_object($discordEquipment)) {
			log::add('discordlink', 'error', 'SlashCommand : Équipement introuvable pour le channel ' . $result['channelId']);
			echo "Erreur : Équipement introuvable";
			die();
		}

		if ($discordEquipment->getConfiguration('interactionJeedom') != 1) {
			log::add('discordlink', 'warning', 'SlashCommand : Les interactions sont désactivées pour l\'équipement ' . $discordEquipment->getHumanName());
			echo "Les interactions sont désactivées pour cet équipement.";
			die();
		}

		$parameters = array();
		$parameters['plugin'] = 'discordlink';
		$parameters['userid'] = $result['userId'];
		$parameters['channel'] = $result['channelId'];
		
		log::add('discordlink', 'debug', 'SlashCommand : Envoi au moteur d\'interaction...');
		$reply = interactQuery::tryToReply(trim($result['request']), $parameters);
		log::add('discordlink', 'debug', 'SlashCommand : Réponse brute moteur : ' . json_encode($reply, JSON_UNESCAPED_UNICODE));
		
		if (isset($reply['reply'])) {
			$responseText = $reply['reply'];
			if (empty($responseText)) {
				$responseText = "Jeedom a exécuté la commande mais n'a rien renvoyé.";
			}
			log::add('discordlink', 'debug', 'SlashCommand : Réponse envoyée à Discord : ' . $responseText);
			echo $responseText;
		} else {
			log::add('discordlink', 'error', 'SlashCommand : Pas de champ "reply" dans la réponse du moteur');
			echo "Erreur interne Jeedom (pas de réponse interaction)";
		}
		die();
		break;

	default:
		if (!is_object($discordEquipment)) {
			log::add('discordlink', 'debug',  'Device non trouvé: ' . $logicalId);
			die();
		} else {
			log::add('discordlink', 'debug',  'Device trouvé: ' . $logicalId);
		}
}

function getDeviceAndUpdate($name, $value, $jeedomCommand, $_channelId, $_userId) {
	$discordEquipment = eqLogic::byLogicalId($_channelId, 'discordlink');

	if (!is_object($discordEquipment)) return;

	$oldMessage1 = $discordEquipment->getCmd('info', 'lastMessage');
	$oldMessage2 = $discordEquipment->getCmd('info', 'previousMessage1');
	$oldMessage1 = $oldMessage1->execCmd();
	$oldMessage2 = $oldMessage2->execCmd();

	updateCommand("lastMessage", $value, "lastMessage", $discordEquipment);
	updateCommand("previousMessage1", $oldMessage1, "previousMessage1", $discordEquipment);
	updateCommand("previousMessage2", $oldMessage2, "previousMessage2", $discordEquipment);
}

function updateCommand($name, $_value, $_logicalId, $_discordEquipment, $_updateTime = null) {
	try {
		if (isset($_value)) {
			if ($_discordEquipment->getIsEnable() == 1) {
				$cmd = is_object($_logicalId) ? $_logicalId : $_discordEquipment->getCmd('info', $_logicalId);
				if (is_object($cmd)) {
					$oldValue = $cmd->execCmd();
					if ($oldValue !== $cmd->formatValue($_value) || $oldValue === '') {
						$cmd->event($_value, $_updateTime);
					} else {
						$cmd->event(" ", $_updateTime);
						$cmd->event($_value, $_updateTime);
					}
				}
			}
		}
	} catch (Exception $e) {
		log::add('discordlink', 'info',  ' [' . $name . '] erreur_1: ' . $e);
	} catch (Error $e) {
		log::add('discordlink', 'info',  ' [' . $name . '] erreur_2: ' . $e);
	}
}

function getASK($_value, $_channelId, $_request) {
	$discordEquipment = eqLogic::byLogicalId($_channelId, 'discordlink');
	$cmd = $discordEquipment->getCmd('action', "sendEmbed");
	if ($_request === "text") {
		log::add('discordlink', 'debug', 'ASK : Text');
		$value = $_value;
	} else {
		log::add('discordlink', 'debug', 'ASK : Autre');
		$value = $_request[$_value];
	}

	log::add('discordlink', 'debug', 'ASK : Request :"' . $_request . '" || Response : "' . $value . '"');

	$cmd->askResponse($value);
}
