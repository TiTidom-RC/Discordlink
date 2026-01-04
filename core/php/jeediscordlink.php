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

$receivedString=file_get_contents("php://input");
$name=$_GET["name"];
log::add('discordlink', 'debug',  'Réception données sur jeediscordlink ['.$name.']');

log::add('discordlink', 'debug',  "receivedString: ".$receivedString);

$start=strpos($receivedString, "{");
$end=strrpos($receivedString, "}");
$length=1+intval($end)-intval($start);
$correctedString=substr($receivedString, $start, $length);

log::add('discordlink', 'debug',  "correctedString: ".$correctedString);
log::add('discordlink', 'debug',  "name: ".$name);

$result = json_decode($correctedString, true);


if (!is_array($result)) {
	log::add('discordlink', 'debug', 'Format Invalide');
	die();
}

$logical_id = $result['channelId']."_player";

$discordlinkeqlogic=eqLogic::byLogicalId($result['channelId'], 'discordlink');

switch ($name) {
	
		case 'messageReceived':
		getDeviceAndUpdate("1oldmsg", $result['message'], '1oldmsg', $result['channelId'], $result['userId']);
		break;	
		case 'ASK':
		getASK($result['response'], $result['channelId'], $result['request']);
	break;
				
	default:
			if (!is_object($discordlinkeqlogic)) {
				log::add('discordlink', 'debug',  'Device non trouvé: '.$logical_id);
				die();
			} else {
				log::add('discordlink', 'debug',  'Device trouvé: '.$logical_id);
			}
}

function getDeviceAndUpdate($name, $value, $jeedomCommand, $_channelId, $_userId) {
	$discordlinkeqlogic=eqLogic::byLogicalId($_channelId, 'discordlink');

	if (!is_object($discordlinkeqlogic)) return;

	$oldMessage1 = $discordlinkeqlogic->getCmd('info', '1oldmsg');
	$oldMessage2 = $discordlinkeqlogic->getCmd('info', '2oldmsg');
	$oldMessage1 = $oldMessage1->execCmd();
	$oldMessage2 = $oldMessage2->execCmd();

	updateCommand("1oldmsg", $value, "1oldmsg", $discordlinkeqlogic);
	updateCommand("2oldmsg", $oldMessage1, "2oldmsg", $discordlinkeqlogic);
	updateCommand("3oldmsg", $oldMessage2, "3oldmsg", $discordlinkeqlogic);

	log::add('discordlink', 'debug', $discordlinkeqlogic->getConfiguration('interactionJeedom'));
	if ($discordlinkeqlogic->getConfiguration('interactionJeedom') == 1) {
		$parameters['plugin'] = 'discordlink';
		$parameters['userid'] = $_userId;
		$parameters['channel'] = $_channelId;
		$reply = interactQuery::tryToReply(trim($value), $parameters);
		log::add('discordlink', 'debug', 'Interaction ' . print_r($reply, true));
		if ($reply['reply'] != "Désolé je n'ai pas compris" && $reply['reply'] != "Désolé je n'ai pas compris la demande" && $reply['reply'] != "Désolé je ne comprends pas la demande" && $reply['reply'] != "Je ne comprends pas" && $reply['reply'] != "ceci est un message de test" && $reply['reply'] != "" && $reply['reply'] != " ") {
			log::add('discordlink', 'debug',  "La reponse : ".$reply['reply']. " est valide je vous l'ai donc renvoyée");
			$cmd = $discordlinkeqlogic->getCmd('action', 'sendMsg');
			$option = array('message' => $reply['reply']);
			$cmd->execute($option);
		} else {
			log::add('discordlink', 'debug',  "La reponse : ".$reply['reply']. " est une reponse générique je vous l'ai donc pas renvoyée");
		}
	}

}

function updateCommand($name, $_value, $_logicalId, $_discordlinkeqlogic, $_updateTime = null) {
	try {
		if (isset($_value)) {
			if ($_discordlinkeqlogic->getIsEnable() == 1) {
				$cmd = is_object($_logicalId) ? $_logicalId : $_discordlinkeqlogic->getCmd('info', $_logicalId);
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
		log::add('discordlink', 'info',  ' ['.$name.'] erreur_1: '.$e);		
	} catch (Error $e) {
		log::add('discordlink', 'info',  ' ['.$name.'] erreur_2: '.$e);
    }	
}

function getASK($_value, $_channelId, $_request) {
	$discordlinkeqlogic=eqLogic::byLogicalId($_channelId, 'discordlink');
	$cmd = $discordlinkeqlogic->getCmd('action', "sendEmbed");
	if ($_request === "text") {
		log::add('discordlink', 'debug', 'ASK : Text');
		$value = $_value;
	} else {
		log::add('discordlink', 'debug', 'ASK : Autre');
		$value = $_request[$_value];
	}

	log::add('discordlink', 'debug', 'ASK : Request :"'.$_request.'" || Response : "'.$value.'"');

	$cmd->askResponse($value);
}
?>