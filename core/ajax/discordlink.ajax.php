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
    
    if (init('action') == 'saveemojy') {

        $arrayemojy = init('arrayemojy');
        $emojyconfig = array();

        foreach ($arrayemojy as $emojy) {
            $key = $emojy['keyEmojy'];
            $emojyconfig[$key] = $emojy['codeEmojy'];
        }
        //$emojy = json_encode($emojyconfig);
        config::save('emojy', $emojyconfig, 'discordlink');
        ajax::success();
    }

    if (init('action') == 'saveUser') {
        $arrayUser = init('arrayUser');
        $userConfig = array();

        foreach ($arrayUser as $user) {
            $key = $user['prenomUser']."_".$user['nomUser'];
            $userConfig[$key] = $user;
        }

        config::save('user', $userConfig, 'discordlink');
        ajax::success();
    }

    if (init('action') == 'getemojy') {
        $emojyarray = config::byKey('emojy', 'discordlink');
        $emojycommandetable = array();
        foreach ($emojyarray as $key => $emojy) {
            $emojycmdligne = array('keyEmojy' => $key, 'codeEmojy' => $emojy);
            array_push($emojycommandetable,  $emojycmdligne);
        }
        $emojy = $emojycommandetable;
        ajax::success($emojy);
    }

    if (init('action') == 'getuser') {
        /** @var array $users **/
        $users = config::byKey('user', 'discordlink', array());
        $userscommandetable = array();

        foreach ($users as $user) {
            array_push($userscommandetable, $user);
        }

        ajax::success($userscommandetable);
    }

    if (init('action') == 'getuserArray') {
        $users = config::byKey('user', 'discordlink');

        ajax::success($users);
    }

    if (init('action') == 'getinvite') {
        $invite = discordlink::getinvite();
        ajax::success($invite);
    }

    if (init('action') == 'resetemojy') {
        discordlink::setemojy(1);
        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}