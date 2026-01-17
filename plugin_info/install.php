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
    $info = discordlink::getInfo();
    $version = $info['pluginVersion'];
    config::save('pluginVersion', $version, 'discordlink');
    
    message::add('discordlink', 'Merci d\'avoir installé le plugin DiscordLink version ' . $version);
    
    discordlink::createCmd();
    discordlink::setEmoji();
    discordlink::updateObject();
}

function discordlink_update() {
    $info = discordlink::getInfo();
    $version = $info['pluginVersion'];
    config::save('pluginVersion', $version, 'discordlink');

    // MIGRATION V2.0 : Correction des commandes existantes sans LogicalId
    // Pour éviter l'erreur SQL 1062 Duplicate Entry lors de la recréation des commandes
    try {
        $eqLogics = eqLogic::byType('discordlink');
        foreach ($eqLogics as $eqLogic) {
            // Liste des commandes critiques qui provoquaient des doublons
            $cmdsToFix = array(
                'Etat des démons' => 'daemonInfo', 
                'Etat des dépendances' => 'dependencyInfo', 
                'Résumé général' => 'globalSummary',
                'Résumé par objet' => 'objectSummary',
                'Résumé des batteries' => 'batteryInfo',
                'Centre de messages' => 'messageCenter',
                'Dernière Connexion utilisateur' => 'lastUser',
                'Dernier message' => 'lastMessage',
                'Avant dernier message' => 'previousMessage1',
                'Avant Avant dernier message' => 'previousMessage2'
            );
            
            // On itère sur toutes les commandes de l'équipement pour être sûr de trouver celles qui ont le "bon nom" mais le "mauvais ID"
            foreach ($eqLogic->getCmd() as $cmd) {
                if (array_key_exists($cmd->getName(), $cmdsToFix)) {
                    $targetLogicalId = $cmdsToFix[$cmd->getName()];
                    // Si le logicalID est différent de celui attendu (vide ou obsolète)
                    if ($cmd->getLogicalId() != $targetLogicalId) {
                        log::add('discordlink', 'info', '[Migration] Correction LogicalId pour commande: ' . $cmd->getName() . ' (' . $cmd->getLogicalId() . ' -> ' . $targetLogicalId . ')');
                        $cmd->setLogicalId($targetLogicalId);
                        $cmd->save();
                    }
                }
            }
        }
    } catch (Exception $e) {
        // En cas d'erreur de migration, on continue
        log::add('discordlink', 'error', 'Erreur lors de la migration des commandes : ' . $e->getMessage());
    }

    if (config::byKey('disableUpdateMessage', 'discordlink', 0) == 0) {
        message::add('discordlink', 'Le plugin DiscordLink a été mis à jour en version ' . $version);
    }
    
    // Suppression de l'ancien fichier quickreply.json dans resources/
    // Le nouveau fichier est automatiquement copié dans data/ lors de la mise à jour
    $oldQuickreplyPath = dirname(__FILE__) . '/../resources/quickreply.json';
    if (file_exists($oldQuickreplyPath)) {
        unlink($oldQuickreplyPath);
        log::add('discordlink', 'info', 'Suppression ancien fichier quickreply.json dans resources/');
    }
    
    // Migration de la clé de configuration globale emojy → emoji
    $oldEmojiConfig = config::byKey('emojy', 'discordlink', null);
    if ($oldEmojiConfig !== null) {
        // On ne migre que si la nouvelle clé n'existe pas déjà pour éviter d'écraser des modifications récentes
        if (config::byKey('emoji', 'discordlink', null) === null) {
            config::save('emoji', $oldEmojiConfig, 'discordlink');
            log::add('discordlink', 'info', 'Migration configuration globale: emojy → emoji');
        }
        config::remove('emojy', 'discordlink');
    }
    
    // Migration des paramètres de configuration des équipements
    foreach (eqLogic::byType('discordlink') as $eqLogic) {
        $needSave = false;
        $configuration = $eqLogic->getConfiguration();
        
        // Migration des noms de configuration (anciennes versions)
        $configMigrations = [
            'autorefreshDependances' => 'autoRefreshDependency',
            'autorefreshDependancy' => 'autoRefreshDependency',
            'autorefreshDeamon' => 'autoRefreshDaemon',
            'autorefreshDaemon' => 'autoRefreshDaemon',
            'autorefreshDependency' => 'autoRefreshDependency',
            'deamoncheck' => 'daemonCheck',
            'depcheck' => 'dependencyCheck',
            'channelid' => 'channelId',
            'connectcheck' => 'connectionCheck',
            'clearchannel' => 'clearChannel',
            'interactionjeedom' => 'interactionJeedom'
        ];
        
        foreach ($configMigrations as $oldKey => $newKey) {
            if (isset($configuration[$oldKey]) && $configuration[$oldKey] !== '') {
                $eqLogic->setConfiguration($newKey, $configuration[$oldKey]);
                unset($configuration[$oldKey]);
                $needSave = true;
                log::add('discordlink', 'info', 'Migration configuration équipement ' . $eqLogic->getHumanName() . ': ' . $oldKey . ' → ' . $newKey);
            }
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
    }
    
    // Détection des commandes obsolètes ou avec mauvais logicalId
    $obsoleteLogicalIds = ['1oldmsg', '2oldmsg', '3oldmsg', 'deamonInfo', 'dependanceInfo', 
                           'batteryinfo', 'centreMsg', 'LastUser'];
    
    $expectedCommands = [
        'sendMsg' => 'Envoi message',
        'sendMsgTTS' => 'Envoi message TTS',
        'sendEmbed' => 'Envoi message évolué',
        'sendFile' => 'Envoi fichier',
        'deleteMessage' => 'Supprime les messages du channel',
        'daemonInfo' => 'Etat des démons',
        'dependencyInfo' => 'Etat des dépendances',
        'globalSummary' => 'Résumé général',
        'objectSummary' => 'Résumé par objet',
        'batteryInfo' => 'Résumé des batteries',
        'messageCenter' => 'Centre de messages',
        'lastUser' => 'Dernière Connexion utilisateur',
        'lastMessage' => 'Dernier message',
        'previousMessage1' => 'Avant dernier message',
        'previousMessage2' => 'Avant Avant dernier message'
    ];
    
    $hasProblematicCommands = false;
    
    foreach (eqLogic::byType('discordlink') as $eqLogic) {
        $problematicCommands = [];
        
        foreach ($eqLogic->getCmd() as $cmd) {
            $cmdName = $cmd->getName();
            $logicalId = $cmd->getLogicalId();
            $cmdId = $cmd->getId();
            
            // Détecter les anciens logicalId obsolètes
            if (in_array($logicalId, $obsoleteLogicalIds)) {
                $problematicCommands[] = "  - Commande obsolète : '$cmdName' (logicalId: $logicalId, ID: $cmdId)";
                $hasProblematicCommands = true;
            }
            
            // Détecter les commandes avec mauvais logicalId
            foreach ($expectedCommands as $expectedLogicalId => $expectedName) {
                if ($cmdName === $expectedName && $logicalId !== $expectedLogicalId) {
                    $problematicCommands[] = "  - Commande '$cmdName' a un mauvais logicalId : '$logicalId' (attendu: '$expectedLogicalId', ID: $cmdId)";
                    $hasProblematicCommands = true;
                    break;
                }
            }
        }
        
        if (!empty($problematicCommands)) {
            log::add('discordlink', 'warning', 'Équipement ' . $eqLogic->getHumanName() . ' - Commandes à corriger :');
            foreach ($problematicCommands as $message) {
                log::add('discordlink', 'warning', $message);
            }
        }
    }
    
    if ($hasProblematicCommands) {
        log::add('discordlink', 'warning', '==========================================================================');
        log::add('discordlink', 'warning', 'MISE À JOUR : Des commandes obsolètes ou incorrectes ont été détectées.');
        log::add('discordlink', 'warning', 'Veuillez supprimer manuellement les commandes listées ci-dessus.');
        log::add('discordlink', 'warning', 'Les nouvelles commandes seront recréées automatiquement.');
        log::add('discordlink', 'warning', '==========================================================================');
    } else {
        log::add('discordlink', 'info', 'Mise à jour terminée - Aucune commande problématique détectée.');
    }
}


function discordlink_remove() {

}

?>
