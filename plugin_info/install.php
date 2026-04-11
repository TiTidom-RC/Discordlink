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
    config::save('socketport', discordlink::SOCKET_PORT, 'discordlink');

    message::add('discordlink', 'Merci d\'avoir installé le plugin DiscordLink version ' . $version);

    discordlink::createCmd();
    discordlink::setEmoji();
    discordlink::updateObject();
    discordlink::createQuickActionFile();
}

function discordlink_update() {
    $info = discordlink::getInfo();
    $version = $info['pluginVersion'];
    log::add('discordlink', 'info', '---------------------------------------------------------------------');
    log::add('discordlink', 'info', 'Début de la mise à jour du plugin DiscordLink vers la version ' . $version);

    config::save('pluginVersion', $version, 'discordlink');

    // 1. Initialisation et Migration Configuration Globale
    // ---------------------------------------------------
    if (config::byKey('socketport', 'discordlink', '') === '') {
        config::save('socketport', discordlink::SOCKET_PORT, 'discordlink');
        log::add('discordlink', 'info', '  - Initialisation du port du démon : ' . discordlink::SOCKET_PORT);
    }

    // Migration de la clé de configuration globale emojy → emoji
    $oldEmojiConfig = config::byKey('emojy', 'discordlink', null);
    if ($oldEmojiConfig !== null) {
        // On ne migre que si la nouvelle clé n'existe pas déjà pour éviter d'écraser des modifications récentes
        if (config::byKey('emoji', 'discordlink', null) === null) {
            config::save('emoji', $oldEmojiConfig, 'discordlink');
            log::add('discordlink', 'info', '  - Configuration globale : emojy → emoji');
        }
        config::remove('emojy', 'discordlink');
    }

    // 2. Nettoyage Système de Fichiers et vérification du fichier quickaction.json
    // ------------------------------------------------------------------------------
    log::add('discordlink', 'info', 'Nettoyage des anciens fichiers et vérification du fichier quickaction.json...');
    $pluginDir = dirname(__DIR__);
    try {
        $pathsToRemove = array(
            // Accepte fichiers ET répertoires (rm -rf) — ajouter ici les chemins à supprimer à chaque mise à jour
            $pluginDir . '/core/class/discordlinkCovid.class.php',
            $pluginDir . '/core/class/discordMsg.class.php',
            $pluginDir . '/core/php/discordlink.inc.php',
            $pluginDir . '/core/template/mobile/cmd.action.other.templeteTemplate.html',
            $pluginDir . '/core/template/scenario/cmd.covidSend.html',
            $pluginDir . '/desktop/js/configuration.js',
            $pluginDir . '/desktop/js/discordlinkuser.js',
            $pluginDir . '/desktop/php/discordlinkuser.php',
            $pluginDir . '/plugin_info/_icon.png',
            $pluginDir . '/resources/post_install.sh',
            $pluginDir . '/resources/pre_install.sh',
            $pluginDir . '/resources/install.sh',
            $pluginDir . '/resources/install_nodejs.sh',
            $pluginDir . '/resources/yarn.lock',
            $pluginDir . '/resources/dependance.lib',
            $pluginDir . '/resources/i18n',
            $pluginDir . '/resources/quickreply.json',
            $pluginDir . '/data/quickreply.json',
            $pluginDir . '/core/php/.htaccess',
        );
        $cleanupRemoved = 0;
        $cleanupErrors = 0;
        foreach ($pathsToRemove as $path) {
            if (file_exists($path)) {
                $output = array();
                $returnVar = 0;
                exec('rm -rf ' . escapeshellarg($path) . ' 2>&1', $output, $returnVar);
                if ($returnVar !== 0) {
                    $cleanupErrors++;
                    log::add('discordlink', 'warning', '[CLEANUP_KO] Echec suppression "' . $path . '" (Code: ' . $returnVar . ') : ' . implode(' ', $output));
                } else {
                    $cleanupRemoved++;
                    log::add('discordlink', 'info', '[CLEANUP_OK] Chemin supprimé : ' . $path);
                }
            }
        }
        $cleanupSummary = count($pathsToRemove) . ' chemin(s) vérifié(s), ' . $cleanupRemoved . ' supprimé(s)';
        if ($cleanupErrors > 0) {
            $cleanupSummary .= ', ' . $cleanupErrors . ' erreur(s)';
        }
        log::add('discordlink', 'debug', '[CLEANUP] ' . $cleanupSummary);
    } catch (Exception $e) {
        log::add('discordlink', 'warning', '[CLEANUP_KO] Erreur lors du nettoyage : ' . $e->getMessage());
    }

    $quickActionPath = $pluginDir . '/data/quickaction.json';
    if (!file_exists($quickActionPath)) {
        log::add('discordlink', 'info', '  - Création du fichier quickaction.json par défaut');
        discordlink::createQuickActionFile();
    }

    // 3. Correction et Nettoyage des Commandes
    // ----------------------------------------
    // MIGRATION V2.0 : Correction des commandes existantes sans LogicalId
    // Pour éviter l'erreur SQL 1062 Duplicate Entry lors de la recréation des commandes
    log::add('discordlink', 'info', 'Vérification et correction des commandes...');
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
                'Avant Avant dernier message' => 'previousMessage2',
                'Avant avant dernier message' => 'previousMessage2'
            );

            foreach ($eqLogic->getCmd() as $cmd) {
                // Correction Logical ID
                if (array_key_exists($cmd->getName(), $cmdsToFix)) {
                    $targetLogicalId = $cmdsToFix[$cmd->getName()];
                    if ($cmd->getLogicalId() != $targetLogicalId) {
                        log::add('discordlink', 'info', '  - ' . $eqLogic->getHumanName() . ': LogicalId corrigé ' . $cmd->getName() . ' (' . $cmd->getLogicalId() . ' -> ' . $targetLogicalId . ')');
                        $cmd->setLogicalId($targetLogicalId);
                        $cmd->save();
                    }
                }
            }

            $cmdsToRemove = array(
                'covidSend',
            );

            foreach ($eqLogic->getCmd() as $cmd) {
                // Suppression commandes obsolètes connues
                if (in_array($cmd->getLogicalId(), $cmdsToRemove)) {
                    log::add('discordlink', 'info', '  - ' . $eqLogic->getHumanName() . ': Commande obsolète supprimée : ' . $cmd->getName() . ' (' . $cmd->getLogicalId() . ')');
                    $cmd->remove();
                }
            }
        }
    } catch (Exception $e) {
        log::add('discordlink', 'error', 'Erreur lors de la migration des commandes : ' . $e->getMessage());
    }

    // 4. Migration des Paramètres des Équipements
    // -------------------------------------------
    log::add('discordlink', 'info', 'Migration des configurations équipements...');
    foreach (eqLogic::byType('discordlink') as $eqLogic) {
        $needSave = false;
        $configuration = $eqLogic->getConfiguration();

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
                // Ne migrer que si la nouvelle clé n'a pas déjà une valeur valide
                if (!isset($configuration[$newKey]) || $configuration[$newKey] === '') {
                    $eqLogic->setConfiguration($newKey, $configuration[$oldKey]);
                    log::add('discordlink', 'info', '  - ' . $eqLogic->getHumanName() . ': ' . $oldKey . ' → ' . $newKey);
                } else {
                    log::add('discordlink', 'info', '  - ' . $eqLogic->getHumanName() . ': ' . $oldKey . ' ignoré (' . $newKey . ' déjà défini à "' . $configuration[$newKey] . '")');
                }
                // Supprimer réellement l'ancienne clé de l'équipement
                $eqLogic->setConfiguration($oldKey, null);
                unset($configuration[$oldKey]);
                $needSave = true;
            }
        }

        $color = $eqLogic->getConfiguration('defaultColor');
        if($color === '' || $color == '#000000') {
            $eqLogic->setConfiguration('defaultColor', discordlink::DEFAULT_COLOR);
            log::add('discordlink', 'info', '  - ' . $eqLogic->getHumanName() . ': defaultColor initialisé à la valeur par défaut (' . discordlink::DEFAULT_COLOR . ')');
            $needSave = true;
        }

        if ($needSave) {
            $eqLogic->save();
        }
    }

    // 5. Régénération des Commandes et Emojis
    // ---------------------------------------
    log::add('discordlink', 'info', 'Mise à jour des définitions des commandes et emojis...');
    log::add('discordlink', 'info', '  - Vérification des commandes des équipements...');
    discordlink::createCmd();
    log::add('discordlink', 'info', '  - Vérification des emojis des équipements...');
    discordlink::setEmoji();

    // 6. Détection des commandes obsolètes ou avec mauvais logicalId
    // --------------------------------------------------------------
    log::add('discordlink', 'info', 'Analyse des commandes existantes...');
    $obsoleteLogicalIds = [
        '1oldmsg',
        '2oldmsg',
        '3oldmsg',
        'deamonInfo',
        'dependanceInfo',
        'batteryinfo',
        'centreMsg',
        'LastUser'
    ];
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
        'previousMessage2' => 'Avant avant dernier message'
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
                $problematicCommands[] = "    - Commande obsolète : '$cmdName' (logicalId: $logicalId, ID: $cmdId)";
                $hasProblematicCommands = true;
            }

            // Détecter les commandes avec mauvais logicalId
            foreach ($expectedCommands as $expectedLogicalId => $expectedName) {
                if ($cmdName === $expectedName && $logicalId !== $expectedLogicalId) {
                    $problematicCommands[] = "    - Commande '$cmdName' a un mauvais logicalId : '$logicalId' (attendu: '$expectedLogicalId', ID: $cmdId)";
                    $hasProblematicCommands = true;
                    break;
                }
            }
        }

        if (!empty($problematicCommands)) {
            log::add('discordlink', 'warning', '  - Équipement ' . $eqLogic->getHumanName() . ' - Commandes à corriger :');
            foreach ($problematicCommands as $message) {
                log::add('discordlink', 'warning', $message);
            }
        }
    }

    if ($hasProblematicCommands) {
        log::add('discordlink', 'warning', '==========================================================================');
        log::add('discordlink', 'warning', 'ATTENTION : Des commandes obsolètes ou incorrectes ont été détectées.');
        log::add('discordlink', 'warning', 'Veuillez supprimer manuellement les commandes listées ci-dessus.');
        log::add('discordlink', 'warning', 'Les nouvelles commandes seront recréées automatiquement.');
        log::add('discordlink', 'warning', '==========================================================================');
    } else {
        log::add('discordlink', 'info', '  - Aucune commande problématique détectée.');
    }

    if (config::byKey('disableUpdateMessage', 'discordlink', 0) == 0) {
        message::add('discordlink', 'Le plugin DiscordLink a été mis à jour en version ' . $version);
    }

    log::add('discordlink', 'info', 'Mise à jour terminée avec succès.');
    log::add('discordlink', 'info', '---------------------------------------------------------------------');
}


function discordlink_remove() {
}
