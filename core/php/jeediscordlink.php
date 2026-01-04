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

$chaineRecuperee=file_get_contents("php://input");
$nom=$_GET["nom"];
log::add('discordlink', 'debug',  'Réception données sur jeediscordlink ['.$nom.']');

log::add('discordlink', 'debug',  "chaineRecuperee: ".$chaineRecuperee);

$debut=strpos($chaineRecuperee, "{");
$fin=strrpos($chaineRecuperee, "}");
$longeur=1+intval($fin)-intval($debut);
$chaineRecupereeCorrigee=substr($chaineRecuperee, $debut, $longeur);

log::add('discordlink', 'debug',  "chaineRecupereeCorrigee: ".$chaineRecupereeCorrigee);
log::add('discordlink', 'debug',  "nom: ".$nom);

$result = json_decode($chaineRecupereeCorrigee, true);


if (!is_array($result)) {
	log::add('discordlink', 'debug', 'Format Invalide');
	die();
}

$logical_id = $result['idchannel']."_player";

$discordlinkeqlogic=eqLogic::byLogicalId($result['idchannel'], 'discordlink');

switch ($nom) {
	
		case 'messagerecu':
			getdevicepuisupdate("1oldmsg", $result['message'], '1oldmsg', $result['idchannel'], $result['iduser']);
		break;	
		case 'ASK':
			getASK($result['reponse'], $result['idchannel'], $result['demande']);
		break;
					
		default:
			if (!is_object($discordlinkeqlogic)) {
				log::add('discordlink', 'debug',  'Device non trouvé: '.$logical_id);
				die();
			} else {
				log::add('discordlink', 'debug',  'Device trouvé: '.$logical_id);
			}
}

function getdevicepuisupdate($nom, $variable, $commandejeedom, $_idchannel, $id_user) {
	$discordlinkeqlogic=eqLogic::byLogicalId($_idchannel, 'discordlink');

	if (!is_object($discordlinkeqlogic)) return;

	$oldmsg1 = $discordlinkeqlogic->getCmd('info', '1oldmsg');
	$oldmsg2 = $discordlinkeqlogic->getCmd('info', '2oldmsg');
	$oldmsg1 = $oldmsg1->execCmd();
	$oldmsg2 = $oldmsg2->execCmd();

	updatecommande("1oldmsg", $variable, "1oldmsg", $discordlinkeqlogic);
	updatecommande("2oldmsg", $oldmsg1, "2oldmsg", $discordlinkeqlogic);
	updatecommande("3oldmsg", $oldmsg2, "3oldmsg", $discordlinkeqlogic);

	log::add('discordlink', 'debug', $discordlinkeqlogic->getConfiguration('interactionjeedom'));
	if ($discordlinkeqlogic->getConfiguration('interactionjeedom') == 1) {
		$parameters['plugin'] = 'discordlink';
		$parameters['userid'] = $id_user;
		$parameters['channel'] = $_idchannel;
		$reply = interactQuery::tryToReply(trim($variable), $parameters);
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

function updatecommande($nom, $_value, $_logicalId, $_discordlinkeqlogic, $_updateTime = null) {
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
		log::add('discordlink', 'info',  ' ['.$nom.'] erreur_1: '.$e);		
	} catch (Error $e) {
		log::add('discordlink', 'info',  ' ['.$nom.'] erreur_2: '.$e);
    }	
}

function getASK($_value, $_idchannel, $_demande) {
	$discordlinkeqlogic=eqLogic::byLogicalId($_idchannel, 'discordlink');
	$cmd = $discordlinkeqlogic->getCmd('action', "sendEmbed");
	if ($_demande === "text") {
		log::add('discordlink', 'debug', 'ASK : Text');
		$value = $_value;
	} else {
		log::add('discordlink', 'debug', 'ASK : Autre');
		$value = $_demande[$_value];
	}

	log::add('discordlink', 'debug', 'ASK : Demande :"'.$_demande.'" || Reponse : "'.$value.'"');

	$cmd->askResponse($value);
}
?>