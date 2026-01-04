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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function discordlink_install() {
    discordlink::CreateCmd();
    discordlink::setEmoji();
    discordlink::updateObject();
}

function discordlink_update() {
    $plugin = plugin::byId("discordlink");
    $plugin->dependancy_install();
    
    // Suppression de l'ancien fichier quickreply.json dans resources/
    // Le nouveau fichier est automatiquement copié dans data/ lors de la mise à jour
    $oldQuickreplyPath = dirname(__FILE__) . '/../resources/quickreply.json';
    if (file_exists($oldQuickreplyPath)) {
        unlink($oldQuickreplyPath);
        log::add('discordlink', 'info', 'Suppression ancien fichier quickreply.json dans resources/');
    }
    
    // Migration de la clé de configuration globale emojy → emoji
    $emojiConfig = config::byKey('emojy', 'discordlink', null);
    if ($emojiConfig !== null) {
        config::save('emoji', $emojiConfig, 'discordlink');
        config::remove('emojy', 'discordlink');
    }
    
    // Migration des anciennes propriétés de configuration
    foreach (eqLogic::byType('discordlink') as $eqLogic) {
        $needSave = false;
        $configuration = $eqLogic->getConfiguration();
        
        // Migration autorefreshDependances → autorefreshDependancy
        if (isset($configuration['autorefreshDependances']) && $configuration['autorefreshDependances'] !== '') {
            $eqLogic->setConfiguration('autorefreshDependancy', $configuration['autorefreshDependances']);
            unset($configuration['autorefreshDependances']);
            $needSave = true;
        }
        
        // Migration channelid → channelId
        if (isset($configuration['channelid']) && $configuration['channelid'] !== '') {
            $eqLogic->setConfiguration('channelId', $configuration['channelid']);
            unset($configuration['channelid']);
            $needSave = true;
        }
        
        // Migration connectcheck → connectionCheck
        if (isset($configuration['connectcheck']) && $configuration['connectcheck'] !== '') {
            $eqLogic->setConfiguration('connectionCheck', $configuration['connectcheck']);
            unset($configuration['connectcheck']);
            $needSave = true;
        }
        
        // Migration clearchannel → clearChannel
        if (isset($configuration['clearchannel']) && $configuration['clearchannel'] !== '') {
            $eqLogic->setConfiguration('clearChannel', $configuration['clearchannel']);
            unset($configuration['clearchannel']);
            $needSave = true;
        }
        
        // Migration interactionjeedom → interactionJeedom
        if (isset($configuration['interactionjeedom']) && $configuration['interactionjeedom'] !== '') {
            $eqLogic->setConfiguration('interactionJeedom', $configuration['interactionjeedom']);
            unset($configuration['interactionjeedom']);
            $needSave = true;
        }
        
        // Appliquer les suppressions
        if ($needSave) {
            foreach (array_keys($configuration) as $key) {
                if (!isset($configuration[$key])) {
                    $eqLogic->setConfiguration($key, null);
                }
            }
            $eqLogic->save();
        }
        
        // Migration des anciennes commandes message (1oldmsg, 2oldmsg, 3oldmsg)
        $cmdMigrations = array(
            '1oldmsg' => 'lastMessage',
            '2oldmsg' => 'previousMessage1',
            '3oldmsg' => 'previousMessage2'
        );
        
        foreach ($cmdMigrations as $oldLogicalId => $newLogicalId) {
            $oldCmd = $eqLogic->getCmd('info', $oldLogicalId);
            if (is_object($oldCmd)) {
                // Vérifier si la nouvelle commande n'existe pas déjà
                $newCmd = $eqLogic->getCmd('info', $newLogicalId);
                if (!is_object($newCmd)) {
                    // Renommer la commande
                    $oldCmd->setLogicalId($newLogicalId);
                    $oldCmd->save();
                    log::add('discordlink', 'info', 'Migration commande: ' . $oldLogicalId . ' → ' . $newLogicalId);
                } else {
                    // La nouvelle existe déjà, supprimer l'ancienne
                    $oldCmd->remove();
                    log::add('discordlink', 'info', 'Suppression ancienne commande: ' . $oldLogicalId);
                }
            }
        }
    }
    
    discordlink::CreateCmd();
    discordlink::setEmoji();
    discordlink::updateObject();
}


function discordlink_remove() {

}

?>
