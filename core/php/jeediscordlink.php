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
		$discordEquipment = eqLogic::byLogicalId($result['channelId'], 'discordlink');
		log::add('discordlink', 'debug', 'SlashCommand reçue : execType => ' . $result['execType'] . ' request => ' . $result['request'] . ' (User: ' . $result['username'] . ', ID: ' . $result['userId'] . ')');

		if (!is_object($discordEquipment)) {
			log::add('discordlink', 'error', 'SlashCommand : Équipement introuvable pour le channel ' . $result['channelId']);
			echo "Erreur : Équipement introuvable";
			die();
		}

		if ($result['execType'] == 'interaction') {

			if ($discordEquipment->getConfiguration('interactionJeedom') != 1) {
				log::add('discordlink', 'warning', 'SlashCommand : Les interactions sont désactivées pour l\'équipement ' . $discordEquipment->getHumanName());
				echo "Les interactions sont désactivées pour cet équipement.";
				die();
			}

			$parameters = array();
			$parameters['plugin'] = 'discordlink';
			$parameters['userid'] = $result['userId'];
			$parameters['channel'] = $result['channelId'];

			log::add('discordlink', 'debug', 'SlashCommand : Envoi au moteur d\'interaction.... demandée par ' . $result['username']);
			// Le @ supprime le PHP Notice "Only variables should be passed by reference" généré par
			// interactQuery.class.php (core Jeedom) lors de l'appel à tryToReply().
			// Ce notice est produit par le core lui-même (expression temporaire passée par référence)
			// et ne peut pas être corrigé côté plugin.
			$reply = @interactQuery::tryToReply(trim($result['request']), $parameters);
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
		} elseif ($result['execType'] == 'command') {
			if ($discordEquipment->getConfiguration('commandJeedom') != 1) {
				log::add('discordlink', 'warning', 'SlashCommand : Les commandes sont désactivées pour l\'équipement ' . $discordEquipment->getHumanName());
				echo "L'exécution des commandes est désactivée pour cet équipement.";
				die();
			}

			$cmdId = $result['request'];
			log::add('discordlink', 'debug', 'SlashCommand : Exécution de la commande Jeedom ID ' . $cmdId . ' demandée par ' . $result['username']);

			$cmd = cmd::byId($cmdId);
			if (!is_object($cmd)) {
				log::add('discordlink', 'error', 'SlashCommand : Commande introuvable pour ID ' . $cmdId);
				echo "Erreur : Commande introuvable";
				die();
			}

			$cmd->execCmd();
			echo "Commande exécutée";
			log::add('discordlink', 'debug', 'SlashCommand : Commande exécutée avec succès');
		} elseif ($result['execType'] == 'scenario') {
			if ($discordEquipment->getConfiguration('scenarioJeedom') != 1) {
				log::add('discordlink', 'warning', 'SlashCommand : Les scénarios sont désactivés pour l\'équipement ' . $discordEquipment->getHumanName());
				echo "L'exécution des scénarios est désactivée pour cet équipement.";
				die();
			}

			$scId = $result['request'];
			log::add('discordlink', 'debug', 'SlashCommand : Exécution du scénario Jeedom ID ' . $scId . ' demandée par ' . $result['username']);

			$scenario = scenario::byId($scId);
			if (!is_object($scenario)) {
				log::add('discordlink', 'error', 'SlashCommand : Scénario introuvable pour ID ' . $scId);
				echo "Erreur : Scénario introuvable";
				die();
			}


			if (version_compare(jeedom::version(), '4.5', '<')) {
				$scenario_return = $scenario->launch('DiscordLink', 'Lancement du scénario ' . $scenario->getHumanName() . ' (' . $scId . ') via slashcommand par ' . $result['username']);
			} else {
				$scenario->addTag('trigger', 'DiscordLink');
				$scenario->addTag('trigger_message', 'Lancement du scénario ' . $scenario->getHumanName() . ' (' . $scId . ') via slashcommand par ' . $result['username']);
				$scenario_return = $scenario->launch();
			}

			if (is_bool($scenario_return)) {
				$return = $scenario_return ? "Scénario exécuté avec succès" : "Le scénario a été lancé mais a rencontré une erreur d'exécution";
			} else {
				$return = $scenario_return;
			}
			echo $return;
			log::add('discordlink', 'debug', 'SlashCommand - Réponse scénario : ' . $return);
		} else {
			log::add('discordlink', 'warning', 'SlashCommand : Commande inconnue "' . $result['execType'] . '"');
			echo "Commande inconnue";
		}
		die();
		break;

	case 'scenarioSearch':
		$discordEquipment = eqLogic::byLogicalId($result['channelId'], 'discordlink');
		log::add('discordlink', 'debug', 'ScenarioSearch reçue pour "' . $result['name'] . '" (User: ' . $result['username'] . ', ID: ' . $result['userId'] . ')');

		if (!is_object($discordEquipment)) {
			log::add('discordlink', 'error', 'ScenarioSearch : Équipement introuvable pour le channel ' . $result['channelId']);
			echo json_encode(['found' => false, 'message' => 'Équipement introuvable'], JSON_UNESCAPED_UNICODE);
			die();
		}

		if ($discordEquipment->getConfiguration('scenarioJeedom') != 1) {
			log::add('discordlink', 'warning', 'ScenarioSearch : Les scénarios sont désactivés pour l\'équipement ' . $discordEquipment->getHumanName());
			echo json_encode(['found' => false, 'message' => 'L\'exécution des scénarios est désactivée pour cet équipement.'], JSON_UNESCAPED_UNICODE);
			die();
		}

		$searchName = trim($result['name'] ?? '');
		if (empty($searchName)) {
			echo json_encode(['found' => false, 'message' => 'Nom de scénario vide'], JSON_UNESCAPED_UNICODE);
			die();
		}

		$allScenarios = scenario::all();
		$matches = [];

		foreach ($allScenarios as $sc) {
			if (!$sc->getIsActive()) {
				continue;
			}
			$scName = $sc->getName();
			$score  = 0;

			// Correspondance exacte (insensible à la casse)
			if (strtolower($scName) === strtolower($searchName)) {
				$score = 100;
			} elseif (stripos($scName, $searchName) !== false || stripos($searchName, $scName) !== false) {
				// Sous-chaîne
				$score = 80;
			} else {
				// Similarité textuelle
				similar_text(strtolower($scName), strtolower($searchName), $percent);
				if ($percent >= 50) {
					$score = (float) $percent;
				}
			}

			if ($score > 0) {
				$matches[] = ['score' => $score, 'id' => (int) $sc->getId(), 'name' => $scName];
			}
		}

		// Trier par score décroissant, limiter à 9 (max emojis numériques)
		usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
		$matches = array_slice($matches, 0, 9);
		$results = array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name']], $matches);

		if (!empty($results)) {
			log::add('discordlink', 'debug', 'ScenarioSearch : ' . count($results) . ' résultat(s) trouvé(s) pour "' . $searchName . '"');
			echo json_encode(['found' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
		} else {
			log::add('discordlink', 'debug', 'ScenarioSearch : Aucun résultat pour "' . $searchName . '"');
			echo json_encode(['found' => false], JSON_UNESCAPED_UNICODE);
		}
		die();
		break;

	case 'getChannelIds':
		$eqLogics = eqLogic::byType('discordlink');
		$channelIds = [];
		foreach ($eqLogics as $eql) {
			if (!$eql->getIsEnable()) continue;
			$channelId = $eql->getConfiguration('channelId');
			if (!empty($channelId) && $channelId !== 'null') {
				$channelIds[] = $channelId;
			}
		}
		echo json_encode(['channelIds' => $channelIds], JSON_UNESCAPED_UNICODE);
		die();
		break;

	default:
		log::add('discordlink', 'warning', 'Route inconnue reçue : "' . $name . '" - payload : ' . json_encode($result, JSON_UNESCAPED_UNICODE));
		die();
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
	if (!is_object($discordEquipment)) return;

	$cmd = $discordEquipment->getCmd('action', "sendEmbed");
	if ($_request === "text") {
		log::add('discordlink', 'debug', 'ASK : Text');
		$value = $_value;
	} else {
		log::add('discordlink', 'debug', 'ASK : Autre');
		$value = $_request[$_value];
	}

	$requestLog = is_array($_request) ? json_encode($_request, JSON_UNESCAPED_UNICODE) : $_request;
	log::add('discordlink', 'debug', 'ASK : Request :"' . $requestLog . '" || Response : "' . $value . '"');

	$cmd->askResponse($value);
}
