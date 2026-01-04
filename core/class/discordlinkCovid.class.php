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

class discordlinkCovid
{

    public static function generateCovid ($nom = null, $prenom = null, $date_naissance = null, $lieu_naissance = null, $motif = null, $datesortie = null, $heuresortie = null) {

        date_default_timezone_set('Europe/Paris');
        $createdate = date("m/d/Y H:i");

        $adresse = config::bykey("info::address");
        $code_postal = config::bykey("info::postalCode");
        $ville = config::bykey("info::city");

        ("" == $adresse || is_null($adresse)) ? $adresse = "pas d'adresse" : null;
        ("" == $code_postal || is_null($code_postal)) ? $code_postal = "12345" : null;
        ("" == $ville || is_null($ville)) ? $ville = "pas de Ville" : null;
        ("" == $datesortie || is_null($datesortie) || $datesortie == "Maintenant") ? $datesortie = date("Y-m-d") : null;
        ("" == $heuresortie || is_null($heuresortie) || $heuresortie == "Maintenant") ? $heuresortie = date("H:i") : null;

        log::add('discordlink', 'debug', '$datesortie : '.$datesortie);
        log::add('discordlink', 'debug', '$heuresortie : '.$heuresortie);

        log::add('discordlink', 'debug', '$adresse : '.$adresse);
        log::add('discordlink', 'debug', '$code_postal : '.$code_postal);
        log::add('discordlink', 'debug', '$ville : '.$ville);

        $nom = self::encodeTag($nom);
        $prenom = self::encodeTag($prenom);
        $date_naissance = self::encodeTag($date_naissance);
        $lieu_naissance= self::encodeTag($lieu_naissance);
        $adresse = self::encodeTag($adresse);
        $code_postal = self::encodeTag($code_postal);
        $ville = self::encodeTag($ville);
        $motif = self::encodeTag($motif);
        $createdate = self::encodeTag($createdate);
        $datesortie = self::encodeTag($datesortie);
        $heuresortie = self::encodeTag($heuresortie);

        $url = "https://attestation.les2t.fr/#prenom=$prenom&nom=$nom&date-de-naissance=$date_naissance&lieu-de-naissance=$lieu_naissance&adresse=$adresse&ville=$ville&code-postal=$code_postal&reason=$motif&createAT=$createdate&datesortie=$datesortie&heuresortie=$heuresortie&autogenpdf";

        return $url;
    }

    function encodeTag($string) {
        $entities = array('+', 'é', 'è', 'à', 'ç', 'ù', '"', '%22', '/');
        $replacements = array('%20', '%C3%A9', '%C3%A8', '%C3%A0', '%C3%A7', '%C3%B9', '%C3%AB', '', '', '%2F');
        return str_replace($entities, $replacements, urlencode($string));
    }

}
