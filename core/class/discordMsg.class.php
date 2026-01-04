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
        $userConnect_list_new = '';
        $userConnect_list = '';
        $onlineCount = 0;
        $daysBeforeUserRemoval = 61;
        $cronOk = false;
        $cron=65;
        $timeNow = date("Y-m-d H:i:s");
        $maxLine = log::getConfig('maxLineLog');
        // Récupération du niveau de log du log Connection (//100=debug | 200=info | 300=warning | 400=erreur=defaut | 1000=none)
        $level = log::getLogLevel('connection');
        $levelName = log::convertLogLevel($level);

        //Add Emoji
        $emo_warning = discordlink::addEmoji("lastUser_warning",":warning:");
        $emo_mag_right = discordlink::addEmoji("lastUser_mag_right",":mag_right:");
        $emo_mag = discordlink::addEmoji("lastUser_mag",":mag:");
        $emo_check = discordlink::addEmoji("lastUser_check",":white_check_mark:");
        $emo_internet = discordlink::addEmoji("lastUser_internet",":globe_with_meridians:");
        $emo_connecter = discordlink::addEmoji("lastUser_connecter",":green_circle:");
        $emo_deconnecter = discordlink::addEmoji("lastUser_deconnecter",":red_circle:");
        $emo_silhouette = discordlink::addEmoji("lastUser_silhouette",":busts_in_silhouette:");


        if($level > 200){
            $logLevelWarning = "\n"."\n".$emo_warning."Plus d'informations ? ".$emo_warning."\n"."veuillez mettre le log **connection** sur **info** dans *Configuration/Logs* (niveau actuel : **".$levelName."**)";
        } else {
            $logLevelWarning = "";
        }
        $offlineDelay = 10;
        $var_nbUser = 0;
        foreach (user::all() as $user) {
            $var_nbUser++;
                $userConnect_Date[$var_nbUser] = $user->getOptions('lastConnection');
            if($userConnect_Date[$var_nbUser] == ""){
                $userConnect_Date[$var_nbUser] = "1970-01-01 00:00:00";
            }
            if(strtotime($timeNow) - strtotime($userConnect_Date[$var_nbUser]) < $offlineDelay*60){
                $userConnect_Statut[$var_nbUser] = 'en ligne';
            }else{
                $userConnect_Statut[$var_nbUser] = 'hors ligne';
            }
            $userConnect_Name[$var_nbUser] = $user->getLogin();
            if($userConnect_list != ''){
                $userConnect_list = $userConnect_list.'|';
            }
            $userConnect_list .= $userConnect_Name[$var_nbUser].';'.$userConnect_Date[$var_nbUser].';'.$userConnect_Statut[$var_nbUser];
        }
        
        $userConnect_list_new = '';
        // Récupération des lignes du log Connection
        $logData = log::getDelta('connection', 0, '', false, false, 0, $maxLine);
        $logConnection_list = !empty($logData['logText']) ? explode("\n", trim($logData['logText'])) : array();
        $log_nbUser = 0;
        $logConnection_Name_tmp = '';
        if (is_array($logConnection_list)) {
            foreach ($logConnection_list as $value) {
                $logConnection = explode("]", $value);
                $logConnection = substr($logConnection[0], 1);
                if (strtotime($timeNow) - strtotime($logConnection) > $cron) {
                    if ($log_nbUser == 0) {
                        $message = "\n" . "**Pas de connexion** ces **" . $cron . "** dernières minutes !";
                    }
                    break;
                } else {
                    $log_nbUser++;
                    $logConnection_Date[$log_nbUser] = $logConnection;
                    $logConnection = explode(" : ", $value);
                    $logConnection_Name[$log_nbUser] = strtolower($logConnection[2]);
                    if (strpos($logConnection[1], 'clef') !== false) {
                        $logConnection_Type[$log_nbUser] = 'clef';
                    } elseif (strpos($logConnection[1], 'API') !== false) {
                        $logConnection_Type[$log_nbUser] = 'api';
                    } else {
                        $logConnection_Type[$log_nbUser] = 'navigateur';
                    }
                    if ($log_nbUser == 1) {
                        $message .= "\n" . $emo_mag_right . "__Récapitulatif de ces " . $cron . " dernières secondes :__ " . $emo_mag;
                    }
                    $onlineCount++;
                    $message .= "\n" . $emo_check . "**" . $logConnection_Name[$log_nbUser] . "** s'est connecté par **" . $logConnection_Type[$log_nbUser] . "** à **" . date("H", strtotime($logConnection_Date[$log_nbUser])) . "h" . date("i", strtotime($logConnection_Date[$log_nbUser])) . "**";
                    $cronOk = true;
                    $userNum = 0;
                    $foundCount = 0;
                    if (strpos($logConnection_Name_tmp, $logConnection_Name[$log_nbUser]) === false) {
                    } else {
                        continue;
                    }
                    $logConnection_Name_tmp = $logConnection_Name[$log_nbUser];
                    foreach ($userConnect_Name as $userName) {
                        $userNum++;
                        if ($logConnection_Name[$log_nbUser] == $userConnect_Name[$userNum]) {        ///Utilisateur déjà enregistré
                            $foundCount++;
                            if ($userConnect_Statut[$userNum] == 'hors ligne') {
                                $userConnect_Date[$userNum] = $logConnection_Date[$log_nbUser];
                                $userConnect_Statut[$userNum] = 'en ligne';
                            }
                        }
                        if ($userConnect_list_new != '') {
                            $userConnect_list_new = $userConnect_list_new . '|';
                        }
                        $userConnect_list_new .= $userConnect_Name[$userNum] . ';' . $userConnect_Date[$userNum] . ';' . $userConnect_Statut[$userNum];
                    }
                    if ($foundCount == 0) {                                                                //Utilisateur nouveau
                        $userConnect_Name[$userNum] = $logConnection_Name[$log_nbUser];
                        $userConnect_Date[$userNum] = $logConnection_Date[$log_nbUser];
                        $userConnect_Statut[$userNum] = 'en ligne';
                        if ($userConnect_list_new != '') {
                            $userConnect_list_new = $userConnect_list_new . '|';
                        }
                        $userConnect_list_new .= $userConnect_Name[$userNum] . ';' . $userConnect_Date[$userNum] . ';' . $userConnect_Statut[$userNum];
                    }
                    $userConnect_list = $userConnect_list_new;
                }
            }
        }
        
        $sessions = listSession();
        $sessionCount=count($sessions);												//nombre d'utilisateur en session actuellement
        
        $message .= "\n"."\n".$emo_mag_right."__Récapitulatif des sessions actuelles :__ ".$emo_mag;
        // Parcours des sessions pour vérifier le statut et le nombre de sessions
        $userNum=0;
        $userConnect_list_new = '';
        foreach($userConnect_Name as $value){
            $userNum++;
            $userSession=0;
            $foundCount = 0;
            $userConnect_Statut[$userNum] = 'hors ligne';
            $userConnect_IP[$userNum] = '';

            foreach($sessions as $id => $session){
                $userSession++;
                
                $userDelai = strtotime(date("Y-m-d H:i:s")) - strtotime($session['datetime']);

                if($userConnect_Name[$userNum] == $session['login']){
                    if($userDelai < $offlineDelay*60){
                        $foundCount++;
                        $onlineCount++;
                        $userConnect_Statut[$userNum] = 'en ligne';
                        $userConnect_IP[$userNum] .= "\n"."-> ".$emo_internet." IP : ".$session['ip'];
                    }else{
                    }
                }			
            }
            if(date("Y-m-d",strtotime($userConnect_Date[$userNum])) == date("Y-m-d",strtotime($timeNow))){
                $hours = date("H",strtotime($userConnect_Date[$userNum]));
                $minutes = date("i",strtotime($userConnect_Date[$userNum]));
                $date = $hours."h".$minutes;
            }else{
                $dayName = date_fr(date("l", strtotime($userConnect_Date[$userNum])));
                $dayNumber = date("d",strtotime($userConnect_Date[$userNum]));
                $monthName = date_fr(date("F", strtotime($userConnect_Date[$userNum])));
                $yearNumber = date("Y",strtotime($userConnect_Date[$userNum]));
                $hours = date("H",strtotime($userConnect_Date[$userNum]));
                $minutes = date("i",strtotime($userConnect_Date[$userNum]));
                $date = $dayName." ".$dayNumber." ".$monthName." ".$yearNumber."** à **".$hours."h".$minutes;
            }
            if($foundCount > 0){
                $message .= "\n".$emo_connecter." **".$userConnect_Name[$userNum]."** est **en ligne** depuis **".$date."**";
                $message .= $userConnect_IP[$userNum];
            }else{
                if(strtotime($timeNow) - strtotime($userConnect_Date[$userNum]) < ($daysBeforeUserRemoval*24*60*60)){
                    $message .= "\n".$emo_deconnecter." **".$userConnect_Name[$userNum]."** est **hors ligne** (dernière connexion **".$date."**)";
                }
            }
            if($userConnect_list_new != ''){
                $userConnect_list_new = $userConnect_list_new.'|';
            }
            $userConnect_list_new .= $userConnect_Name[$userNum].';'.$userConnect_Date[$userNum].';'.$userConnect_Statut[$userNum];
            $userConnect_list=$userConnect_list_new;
        }
        
        // Préparation des tags de notification
        $title = $emo_silhouette.'CONNEXIONS '.$emo_silhouette;
        return array(
            'title'=>$title,
            'message'=>$message.$logLevelWarning,
            'nbEnLigne'=>$onlineCount,
            'cronOk'=>$cronOk
        );
    }
}