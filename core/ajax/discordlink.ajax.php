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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    //    if (!isConnect('admin')) {
    //        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    //    }
    ajax::init();

    if (init('action') == 'saveEmoji') {

        $arrayEmoji = init('arrayEmoji');
        $emojiConfig = array();

        foreach ($arrayEmoji as $emoji) {
            $key = $emoji['keyEmoji'];
            $emojiConfig[$key] = $emoji['codeEmoji'];
        }
        //$emoji = json_encode($emojiConfig);
        config::save('emoji', $emojiConfig, 'discordlink');
        ajax::success();
    }

    if (init('action') == 'getChannels') {
        if (discordlink::deamon_info()['state'] != 'ok') {
            throw new Exception('Le démon n\'est pas démarré. Veuillez le démarrer avant de rafraîchir les channels.');
        }

        $channels = discordlink::getChannel();
        // Force IDs to string to avoid snowflake precision issues in JSON/JS
        foreach ($channels as &$channel) {
            $channel['id'] = (string)$channel['id'];
        }
        unset($channel); // Break reference

        $result = array('channels' => $channels);
        
        $id = init('id');
        if (!empty($id) && is_numeric($id)) {
            $eqLogic = eqLogic::byId($id);
            if (is_object($eqLogic)) {
                $result['current'] = (string)$eqLogic->getConfiguration('channelId');
            }
        }
        
        ajax::success($result);
    }

    if (init('action') == 'getEmoji') {
        $emojiArray = config::byKey('emoji', 'discordlink');
        if (!is_array($emojiArray)) {
            ajax::success(array());
            return;
        }
        $emojiCommandTable = array();
        foreach ($emojiArray as $key => $emoji) {
            $emojiCmdLine = array('keyEmoji' => $key, 'codeEmoji' => $emoji);
            array_push($emojiCommandTable,  $emojiCmdLine);
        }
        $emoji = $emojiCommandTable;
        ajax::success($emoji);
    }

    if (init('action') == 'resetEmoji') {
        discordlink::setEmoji(1);
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * ***** Catch exception ***** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}
