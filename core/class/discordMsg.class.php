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
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class discordMsg {

    public static function lastUser() {
        $message = "";
        $userConnectListNew = '';
        $userConnectList = '';
        $onlineCount = 0;
        $daysBeforeUserRemoval = 61;
        $hasCronActivity = false;
        $cronInterval = 65;
        $timeNow = date("Y-m-d H:i:s");
        $maxLine = log::getConfig('maxLineLog');
        // Récupération du niveau de log du log Connection (//100=debug | 200=info | 300=warning | 400=erreur=defaut | 1000=none)
        $level = log::getLogLevel('connection');
        $levelName = log::convertLogLevel($level);

        //Add Emoji
        $emojiWarning = discordlink::addEmoji("lastUser_warning",":warning:");
        $emojiMagRight = discordlink::addEmoji("lastUser_mag_right",":mag_right:");
        $emojiMag = discordlink::addEmoji("lastUser_mag",":mag:");
        $emojiCheck = discordlink::addEmoji("lastUser_check",":white_check_mark:");
        $emojiInternet = discordlink::addEmoji("lastUser_internet",":globe_with_meridians:");
        $emojiConnected = discordlink::addEmoji("lastUser_connecter",":green_circle:");
        $emojiDisconnected = discordlink::addEmoji("lastUser_deconnecter",":red_circle:");
        $emojiSilhouette = discordlink::addEmoji("lastUser_silhouette",":busts_in_silhouette:");


        if($level > 200){
            $logLevelWarning = "\n"."\n".$emojiWarning."Plus d'informations ? ".$emojiWarning."\n"."veuillez mettre le log **connection** sur **info** dans *Configuration/Logs* (niveau actuel : **".$levelName."**)";
        } else {
            $logLevelWarning = "";
        }
        $offlineDelay = 10;
        $userIndex = 0;
        foreach (user::all() as $user) {
            $userIndex++;
                $connectedUserDates[$userIndex] = $user->getOptions('lastConnection');
            if($connectedUserDates[$userIndex] == ""){
                $connectedUserDates[$userIndex] = "1970-01-01 00:00:00";
            }
            if(strtotime($timeNow) - strtotime($connectedUserDates[$userIndex]) < $offlineDelay*60){
                $connectedUserStatuses[$userIndex] = 'en ligne';
            }else{
                $connectedUserStatuses[$userIndex] = 'hors ligne';
            }
            $connectedUserNames[$userIndex] = $user->getLogin();
            if($connectedUserList != ''){
                $connectedUserList = $connectedUserList.'|';
            }
            $connectedUserList .= $connectedUserNames[$userIndex].';'.$connectedUserDates[$userIndex].';'.$connectedUserStatuses[$userIndex];
        }
        
        $connectedUserListNew = '';
        // Récupération des lignes du log Connection
        $logData = log::getDelta('connection', 0, '', false, false, 0, $maxLine);
        $connectionLogs = !empty($logData['logText']) ? explode("\n", trim($logData['logText'])) : array();
        $logUserIndex = 0;
        $lastLogConnectionName = '';
        if (is_array($connectionLogs)) {
            foreach ($connectionLogs as $value) {
                $logConnection = explode("]", $value);
                $logConnection = substr($logConnection[0], 1);
                if (strtotime($timeNow) - strtotime($logConnection) > $cronInterval) {
                    if ($logUserIndex == 0) {
                        $message = "\n" . "**Pas de connexion** ces **" . $cronInterval . "** dernières minutes !";
                    }
                    break;
                } else {
                    $logUserIndex++;
                    $connectionLogDates[$logUserIndex] = $logConnection;
                    $logConnection = explode(" : ", $value);
                    $connectionLogNames[$logUserIndex] = strtolower($logConnection[2]);
                    if (strpos($logConnection[1], 'clef') !== false) {
                        $connectionLogTypes[$logUserIndex] = 'clef';
                    } elseif (strpos($logConnection[1], 'API') !== false) {
                        $connectionLogTypes[$logUserIndex] = 'api';
                    } else {
                        $connectionLogTypes[$logUserIndex] = 'navigateur';
                    }
                    if ($logUserIndex == 1) {
                        $message .= "\n" . $emojiMagRight . "__Récapitulatif de ces " . $cronInterval . " dernières secondes :__ " . $emojiMag;
                    }
                    $onlineCount++;
                    $message .= "\n" . $emojiCheck . "**" . $connectionLogNames[$logUserIndex] . "** s'est connecté par **" . $connectionLogTypes[$logUserIndex] . "** à **" . date("H", strtotime($connectionLogDates[$logUserIndex])) . "h" . date("i", strtotime($connectionLogDates[$logUserIndex])) . "**";
                    $hasCronActivity = true;
                    $userIndex = 0;
                    $foundCount = 0;
                    if (strpos($lastLogConnectionName, $connectionLogNames[$logUserIndex]) === false) {
                    } else {
                        continue;
                    }
                    $lastLogConnectionName = $connectionLogNames[$logUserIndex];
                    foreach ($connectedUserNames as $userName) {
                        $userIndex++;
                        if ($connectionLogNames[$logUserIndex] == $connectedUserNames[$userIndex]) {        ///Utilisateur déjà enregistré
                            $foundCount++;
                            if ($connectedUserStatuses[$userIndex] == 'hors ligne') {
                                $connectedUserDates[$userIndex] = $connectionLogDates[$logUserIndex];
                                $connectedUserStatuses[$userIndex] = 'en ligne';
                            }
                        }
                        if ($connectedUserListNew != '') {
                            $connectedUserListNew = $connectedUserListNew . '|';
                        }
                        $connectedUserListNew .= $connectedUserNames[$userIndex] . ';' . $connectedUserDates[$userIndex] . ';' . $connectedUserStatuses[$userIndex];
                    }
                    if ($foundCount == 0) {                                                                //Utilisateur nouveau
                        $connectedUserNames[$userIndex] = $connectionLogNames[$logUserIndex];
                        $connectedUserDates[$userIndex] = $connectionLogDates[$logUserIndex];
                        $connectedUserStatuses[$userIndex] = 'en ligne';
                        if ($connectedUserListNew != '') {
                            $connectedUserListNew = $connectedUserListNew . '|';
                        }
                        $connectedUserListNew .= $connectedUserNames[$userIndex] . ';' . $connectedUserDates[$userIndex] . ';' . $connectedUserStatuses[$userIndex];
                    }
                    $connectedUserList = $connectedUserListNew;
                }
            }
        }
        
        $sessions = listSession();
        $sessionCount=count($sessions);												//nombre d'utilisateur en session actuellement
        
        $message .= "\n"."\n".$emojiMagRight."__Récapitulatif des sessions actuelles :__ ".$emojiMag;
        // Parcours des sessions pour vérifier le statut et le nombre de sessions
        $userIndex=0;
        $connectedUserListNew = '';
        foreach($connectedUserNames as $value){
            $userIndex++;
            $sessionIndex=0;
            $foundCount = 0;
            $connectedUserStatuses[$userIndex] = 'hors ligne';
            $connectedUserIPs[$userIndex] = '';

            foreach($sessions as $id => $session){
                $sessionIndex++;
                
                $userDelay = strtotime(date("Y-m-d H:i:s")) - strtotime($session['datetime']);

                if($connectedUserNames[$userIndex] == $session['login']){
                    if($userDelay < $offlineDelay*60){
                        $foundCount++;
                        $onlineCount++;
                        $connectedUserStatuses[$userIndex] = 'en ligne';
                        $connectedUserIPs[$userIndex] .= "\n"."-> ".$emojiInternet." IP : ".$session['ip'];
                    }else{
                    }
                }			
            }
            if(date("Y-m-d",strtotime($connectedUserDates[$userIndex])) == date("Y-m-d",strtotime($timeNow))){
                $hours = date("H",strtotime($connectedUserDates[$userIndex]));
                $minutes = date("i",strtotime($connectedUserDates[$userIndex]));
                $date = $hours."h".$minutes;
            }else{
                $dayName = date_fr(date("l", strtotime($connectedUserDates[$userIndex])));
                $dayNumber = date("d",strtotime($connectedUserDates[$userIndex]));
                $monthName = date_fr(date("F", strtotime($connectedUserDates[$userIndex])));
                $yearNumber = date("Y",strtotime($connectedUserDates[$userIndex]));
                $hours = date("H",strtotime($connectedUserDates[$userIndex]));
                $minutes = date("i",strtotime($connectedUserDates[$userIndex]));
                $date = $dayName." ".$dayNumber." ".$monthName." ".$yearNumber."** à **".$hours."h".$minutes;
            }
            if($foundCount > 0){
                $message .= "\n".$emojiConnected." **".$connectedUserNames[$userIndex]."** est **en ligne** depuis **".$date."**";
                $message .= $connectedUserIPs[$userIndex];
            }else{
                if(strtotime($timeNow) - strtotime($connectedUserDates[$userIndex]) < ($daysBeforeUserRemoval*24*60*60)){
                    $message .= "\n".$emojiDisconnected." **".$connectedUserNames[$userIndex]."** est **hors ligne** (dernière connexion **".$date."**)";
                }
            }
            if($connectedUserListNew != ''){
                $connectedUserListNew = $connectedUserListNew.'|';
            }
            $connectedUserListNew .= $connectedUserNames[$userIndex].';'.$connectedUserDates[$userIndex].';'.$connectedUserStatuses[$userIndex];
            $connectedUserList=$connectedUserListNew;
        }
        
        // Préparation des tags de notification
        $title = $emojiSilhouette.'CONNEXIONS '.$emojiSilhouette;
        return array(
            'title'=>$title,
            'message'=>$message.$logLevelWarning,
            'nbEnLigne'=>$onlineCount,
            'cronOk'=>$hasCronActivity
        );
    }
}