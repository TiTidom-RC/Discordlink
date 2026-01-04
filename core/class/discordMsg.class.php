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

    public static function LastUser() {
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
                $userConnectDate[$userIndex] = $user->getOptions('lastConnection');
            if($userConnectDate[$userIndex] == ""){
                $userConnectDate[$userIndex] = "1970-01-01 00:00:00";
            }
            if(strtotime($timeNow) - strtotime($userConnectDate[$userIndex]) < $offlineDelay*60){
                $userConnectStatus[$userIndex] = 'en ligne';
            }else{
                $userConnectStatus[$userIndex] = 'hors ligne';
            }
            $userConnectName[$userIndex] = $user->getLogin();
            if($userConnectList != ''){
                $userConnectList = $userConnectList.'|';
            }
            $userConnectList .= $userConnectName[$userIndex].';'.$userConnectDate[$userIndex].';'.$userConnectStatus[$userIndex];
        }
        
        $userConnectListNew = '';
        // Récupération des lignes du log Connection
        $logData = log::getDelta('connection', 0, '', false, false, 0, $maxLine);
        $logConnectionList = !empty($logData['logText']) ? explode("\n", trim($logData['logText'])) : array();
        $logUserIndex = 0;
        $lastLogConnectionName = '';
        if (is_array($logConnectionList)) {
            foreach ($logConnectionList as $value) {
                $logConnection = explode("]", $value);
                $logConnection = substr($logConnection[0], 1);
                if (strtotime($timeNow) - strtotime($logConnection) > $cronInterval) {
                    if ($logUserIndex == 0) {
                        $message = "\n" . "**Pas de connexion** ces **" . $cronInterval . "** dernières minutes !";
                    }
                    break;
                } else {
                    $logUserIndex++;
                    $logConnectionDate[$logUserIndex] = $logConnection;
                    $logConnection = explode(" : ", $value);
                    $logConnectionName[$logUserIndex] = strtolower($logConnection[2]);
                    if (strpos($logConnection[1], 'clef') !== false) {
                        $logConnectionType[$logUserIndex] = 'clef';
                    } elseif (strpos($logConnection[1], 'API') !== false) {
                        $logConnectionType[$logUserIndex] = 'api';
                    } else {
                        $logConnectionType[$logUserIndex] = 'navigateur';
                    }
                    if ($logUserIndex == 1) {
                        $message .= "\n" . $emojiMagRight . "__Récapitulatif de ces " . $cronInterval . " dernières secondes :__ " . $emojiMag;
                    }
                    $onlineCount++;
                    $message .= "\n" . $emojiCheck . "**" . $logConnectionName[$logUserIndex] . "** s'est connecté par **" . $logConnectionType[$logUserIndex] . "** à **" . date("H", strtotime($logConnectionDate[$logUserIndex])) . "h" . date("i", strtotime($logConnectionDate[$logUserIndex])) . "**";
                    $hasCronActivity = true;
                    $userNumber = 0;
                    $foundCount = 0;
                    if (strpos($lastLogConnectionName, $logConnectionName[$logUserIndex]) === false) {
                    } else {
                        continue;
                    }
                    $lastLogConnectionName = $logConnectionName[$logUserIndex];
                    foreach ($userConnectName as $userName) {
                        $userNumber++;
                        if ($logConnectionName[$logUserIndex] == $userConnectName[$userNumber]) {        ///Utilisateur déjà enregistré
                            $foundCount++;
                            if ($userConnectStatus[$userNumber] == 'hors ligne') {
                                $userConnectDate[$userNumber] = $logConnectionDate[$logUserIndex];
                                $userConnectStatus[$userNumber] = 'en ligne';
                            }
                        }
                        if ($userConnectListNew != '') {
                            $userConnectListNew = $userConnectListNew . '|';
                        }
                        $userConnectListNew .= $userConnectName[$userNumber] . ';' . $userConnectDate[$userNumber] . ';' . $userConnectStatus[$userNumber];
                    }
                    if ($foundCount == 0) {                                                                //Utilisateur nouveau
                        $userConnectName[$userNumber] = $logConnectionName[$logUserIndex];
                        $userConnectDate[$userNumber] = $logConnectionDate[$logUserIndex];
                        $userConnectStatus[$userNumber] = 'en ligne';
                        if ($userConnectListNew != '') {
                            $userConnectListNew = $userConnectListNew . '|';
                        }
                        $userConnectListNew .= $userConnectName[$userNumber] . ';' . $userConnectDate[$userNumber] . ';' . $userConnectStatus[$userNumber];
                    }
                    $userConnectList = $userConnectListNew;
                }
            }
        }
        
        $sessions = listSession();
        $sessionCount=count($sessions);												//nombre d'utilisateur en session actuellement
        
        $message .= "\n"."\n".$emojiMagRight."__Récapitulatif des sessions actuelles :__ ".$emojiMag;
        // Parcours des sessions pour vérifier le statut et le nombre de sessions
        $userNumber=0;
        $userConnectListNew = '';
        foreach($userConnectName as $value){
            $userNumber++;
            $userSession=0;
            $foundCount = 0;
            $userConnectStatus[$userNumber] = 'hors ligne';
            $userConnectIP[$userNumber] = '';

            foreach($sessions as $id => $session){
                $userSession++;
                
                $userDelai = strtotime(date("Y-m-d H:i:s")) - strtotime($session['datetime']);

                if($userConnectName[$userNumber] == $session['login']){
                    if($userDelai < $offlineDelay*60){
                        $foundCount++;
                        $onlineCount++;
                        $userConnectStatus[$userNumber] = 'en ligne';
                        $userConnectIP[$userNumber] .= "\n"."-> ".$emojiInternet." IP : ".$session['ip'];
                    }else{
                    }
                }			
            }
            if(date("Y-m-d",strtotime($userConnectDate[$userNumber])) == date("Y-m-d",strtotime($timeNow))){
                $hours = date("H",strtotime($userConnectDate[$userNumber]));
                $minutes = date("i",strtotime($userConnectDate[$userNumber]));
                $date = $hours."h".$minutes;
            }else{
                $dayName = date_fr(date("l", strtotime($userConnectDate[$userNumber])));
                $dayNumber = date("d",strtotime($userConnectDate[$userNumber]));
                $monthName = date_fr(date("F", strtotime($userConnectDate[$userNumber])));
                $yearNumber = date("Y",strtotime($userConnectDate[$userNumber]));
                $hours = date("H",strtotime($userConnectDate[$userNumber]));
                $minutes = date("i",strtotime($userConnectDate[$userNumber]));
                $date = $dayName." ".$dayNumber." ".$monthName." ".$yearNumber."** à **".$hours."h".$minutes;
            }
            if($foundCount > 0){
                $message .= "\n".$emojiConnected." **".$userConnectName[$userNumber]."** est **en ligne** depuis **".$date."**";
                $message .= $userConnectIP[$userNumber];
            }else{
                if(strtotime($timeNow) - strtotime($userConnectDate[$userNumber]) < ($daysBeforeUserRemoval*24*60*60)){
                    $message .= "\n".$emojiDisconnected." **".$userConnectName[$userNumber]."** est **hors ligne** (dernière connexion **".$date."**)";
                }
            }
            if($userConnectListNew != ''){
                $userConnectListNew = $userConnectListNew.'|';
            }
            $userConnectListNew .= $userConnectName[$userNumber].';'.$userConnectDate[$userNumber].';'.$userConnectStatus[$userNumber];
            $userConnectList=$userConnectListNew;
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