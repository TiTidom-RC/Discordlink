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
require_once __DIR__ . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/../../core/php/discordlink.inc.php';

class discordlink extends eqLogic {
    /*     * *************************Attributs****************************** */

	const DEFAULT_COLOR = '#ff0000';

	public static function templateWidget() {
		$return['action']['message']['message'] =    array(
				'template' => 'message',
				'replace' => array("#_desktop_width_#" => "100","#_mobile_width_#" => "50", "#title_disable#" => "1", "#message_disable#" => "0")
		);
		$return['action']['message']['embed'] =    array(
			'template' => 'embed',
			'replace' => array("#_desktop_width_#" => "100","#_mobile_width_#" => "50", "#title_disable#" => "1", "#message_disable#" => "0")
		);
		return $return;
	}

	public static function testplugin($_pluginid){
		$result = false;
		try {$test = plugin::byId($_pluginid);  if ($test->isActive()) $result=true;}  catch(Exception $e) {}
		return $result;
	}

	public static function setchannel() {
		$channels = discordlink::getchannel();
		foreach ($channels as $key => $channel) {
			$channelname = $channel['name'];
			$channelname = discordlink::remove_emoji2($channelname);
			$channels[$key]['name'] = $channelname;
		}
		config::save('channels', $channels, 'discordlink');
	}

	private static function remove_emoji2($text){
		return preg_replace('/[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0077}\x{E006C}\x{E0073}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0073}\x{E0063}\x{E0074}\x{E007F})|[\x{1F3F4}](?:\x{E0067}\x{E0062}\x{E0065}\x{E006E}\x{E0067}\x{E007F})|[\x{1F3F4}](?:\x{200D}\x{2620}\x{FE0F})|[\x{1F3F3}](?:\x{FE0F}\x{200D}\x{1F308})|[\x{0023}\x{002A}\x{0030}\x{0031}\x{0032}\x{0033}\x{0034}\x{0035}\x{0036}\x{0037}\x{0038}\x{0039}](?:\x{FE0F}\x{20E3})|[\x{1F441}](?:\x{FE0F}\x{200D}\x{1F5E8}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F467})|[\x{1F468}](?:\x{200D}\x{1F468}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467}\x{200D}\x{1F466})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F467})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F469}\x{200D}\x{1F466})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F468})|[\x{1F469}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F469})|[\x{1F469}\x{1F468}](?:\x{200D}\x{2764}\x{FE0F}\x{200D}\x{1F48B}\x{200D}\x{1F468})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B3})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B2})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B1})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F9B0})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F9B0})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2640}\x{FE0F})|[\x{1F575}\x{1F3CC}\x{26F9}\x{1F3CB}](?:\x{FE0F}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FF}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FE}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FD}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FC}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FB}\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F9B8}\x{1F9B9}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{200D}\x{2640}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FF}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FE}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FD}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FC}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{1F3FB}\x{200D}\x{2642}\x{FE0F})|[\x{1F46E}\x{1F9B8}\x{1F9B9}\x{1F482}\x{1F477}\x{1F473}\x{1F471}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F9DE}\x{1F9DF}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F46F}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93C}\x{1F93D}\x{1F93E}\x{1F939}](?:\x{200D}\x{2642}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F692})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F680})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2708}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A8})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3A4})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BB})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F52C})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F4BC})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3ED})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F527})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F373})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F33E})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2696}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F3EB})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{200D}\x{1F393})|[\x{1F468}\x{1F469}](?:\x{1F3FF}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FE}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FD}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FC}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{1F3FB}\x{200D}\x{2695}\x{FE0F})|[\x{1F468}\x{1F469}](?:\x{200D}\x{2695}\x{FE0F})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FF})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FE})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FD})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FC})|[\x{1F476}\x{1F9D2}\x{1F466}\x{1F467}\x{1F9D1}\x{1F468}\x{1F469}\x{1F9D3}\x{1F474}\x{1F475}\x{1F46E}\x{1F575}\x{1F482}\x{1F477}\x{1F934}\x{1F478}\x{1F473}\x{1F472}\x{1F9D5}\x{1F9D4}\x{1F471}\x{1F935}\x{1F470}\x{1F930}\x{1F931}\x{1F47C}\x{1F385}\x{1F936}\x{1F9D9}\x{1F9DA}\x{1F9DB}\x{1F9DC}\x{1F9DD}\x{1F64D}\x{1F64E}\x{1F645}\x{1F646}\x{1F481}\x{1F64B}\x{1F647}\x{1F926}\x{1F937}\x{1F486}\x{1F487}\x{1F6B6}\x{1F3C3}\x{1F483}\x{1F57A}\x{1F9D6}\x{1F9D7}\x{1F9D8}\x{1F6C0}\x{1F6CC}\x{1F574}\x{1F3C7}\x{1F3C2}\x{1F3CC}\x{1F3C4}\x{1F6A3}\x{1F3CA}\x{26F9}\x{1F3CB}\x{1F6B4}\x{1F6B5}\x{1F938}\x{1F93D}\x{1F93E}\x{1F939}\x{1F933}\x{1F4AA}\x{1F9B5}\x{1F9B6}\x{1F448}\x{1F449}\x{261D}\x{1F446}\x{1F595}\x{1F447}\x{270C}\x{1F91E}\x{1F596}\x{1F918}\x{1F919}\x{1F590}\x{270B}\x{1F44C}\x{1F44D}\x{1F44E}\x{270A}\x{1F44A}\x{1F91B}\x{1F91C}\x{1F91A}\x{1F44B}\x{1F91F}\x{270D}\x{1F44F}\x{1F450}\x{1F64C}\x{1F932}\x{1F64F}\x{1F485}\x{1F442}\x{1F443}](?:\x{1F3FB})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FA}](?:\x{1F1FF})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1FA}](?:\x{1F1FE})|[\x{1F1E6}\x{1F1E8}\x{1F1F2}\x{1F1F8}](?:\x{1F1FD})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F9}\x{1F1FF}](?:\x{1F1FC})|[\x{1F1E7}\x{1F1E8}\x{1F1F1}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1FB})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1FB}](?:\x{1F1FA})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FE}](?:\x{1F1F9})|[\x{1F1E6}\x{1F1E7}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FA}\x{1F1FC}](?:\x{1F1F8})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F7})|[\x{1F1E6}\x{1F1E7}\x{1F1EC}\x{1F1EE}\x{1F1F2}](?:\x{1F1F6})|[\x{1F1E8}\x{1F1EC}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}](?:\x{1F1F5})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EE}\x{1F1EF}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1F8}\x{1F1F9}](?:\x{1F1F4})|[\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1F3})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1EC}\x{1F1ED}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FF}](?:\x{1F1F2})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1F1})|[\x{1F1E8}\x{1F1E9}\x{1F1EB}\x{1F1ED}\x{1F1F1}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FD}](?:\x{1F1F0})|[\x{1F1E7}\x{1F1E9}\x{1F1EB}\x{1F1F8}\x{1F1F9}](?:\x{1F1EF})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EB}\x{1F1EC}\x{1F1F0}\x{1F1F1}\x{1F1F3}\x{1F1F8}\x{1F1FB}](?:\x{1F1EE})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F5}\x{1F1F8}\x{1F1F9}](?:\x{1F1ED})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}](?:\x{1F1EC})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F9}\x{1F1FC}](?:\x{1F1EB})|[\x{1F1E6}\x{1F1E7}\x{1F1E9}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1F8}\x{1F1FB}\x{1F1FE}](?:\x{1F1EA})|[\x{1F1E6}\x{1F1E7}\x{1F1E8}\x{1F1EC}\x{1F1EE}\x{1F1F2}\x{1F1F8}\x{1F1F9}](?:\x{1F1E9})|[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1F9}\x{1F1FB}](?:\x{1F1E8})|[\x{1F1E7}\x{1F1EC}\x{1F1F1}\x{1F1F8}](?:\x{1F1E7})|[\x{1F1E7}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1F1}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F6}\x{1F1F8}\x{1F1F9}\x{1F1FA}\x{1F1FB}\x{1F1FF}](?:\x{1F1E6})|[\x{00A9}\x{00AE}\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}-\x{21AA}\x{231A}-\x{231B}\x{2328}\x{23CF}\x{23E9}-\x{23F3}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}-\x{25AB}\x{25B6}\x{25C0}\x{25FB}-\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}-\x{2615}\x{2618}\x{261D}\x{2620}\x{2622}-\x{2623}\x{2626}\x{262A}\x{262E}-\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{2660}\x{2663}\x{2665}-\x{2666}\x{2668}\x{267B}\x{267E}-\x{267F}\x{2692}-\x{2697}\x{2699}\x{269B}-\x{269C}\x{26A0}-\x{26A1}\x{26AA}-\x{26AB}\x{26B0}-\x{26B1}\x{26BD}-\x{26BE}\x{26C4}-\x{26C5}\x{26C8}\x{26CE}-\x{26CF}\x{26D1}\x{26D3}-\x{26D4}\x{26E9}-\x{26EA}\x{26F0}-\x{26F5}\x{26F7}-\x{26FA}\x{26FD}\x{2702}\x{2705}\x{2708}-\x{270D}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2728}\x{2733}-\x{2734}\x{2744}\x{2747}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2763}-\x{2764}\x{2795}-\x{2797}\x{27A1}\x{27B0}\x{27BF}\x{2934}-\x{2935}\x{2B05}-\x{2B07}\x{2B1B}-\x{2B1C}\x{2B50}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F0CF}\x{1F170}-\x{1F171}\x{1F17E}-\x{1F17F}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}-\x{1F202}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F23A}\x{1F250}-\x{1F251}\x{1F300}-\x{1F321}\x{1F324}-\x{1F393}\x{1F396}-\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}-\x{1F3F0}\x{1F3F3}-\x{1F3F5}\x{1F3F7}-\x{1F3FA}\x{1F400}-\x{1F4FD}\x{1F4FF}-\x{1F53D}\x{1F549}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F56F}-\x{1F570}\x{1F573}-\x{1F57A}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F590}\x{1F595}-\x{1F596}\x{1F5A4}-\x{1F5A5}\x{1F5A8}\x{1F5B1}-\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}-\x{1F64F}\x{1F680}-\x{1F6C5}\x{1F6CB}-\x{1F6D2}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6EB}-\x{1F6EC}\x{1F6F0}\x{1F6F3}-\x{1F6F9}\x{1F910}-\x{1F93A}\x{1F93C}-\x{1F93E}\x{1F940}-\x{1F945}\x{1F947}-\x{1F970}\x{1F973}-\x{1F976}\x{1F97A}\x{1F97C}-\x{1F9A2}\x{1F9B0}-\x{1F9B9}\x{1F9C0}-\x{1F9C2}\x{1F9D0}-\x{1F9FF}]/u', '', $text);
  	}

	public static function setemojy($reset = 0) {
		$json = '{"motion":":person_walking:","door":":door:","windows":":frame_photo:","light":":bulb:","outlet":":electric_plug:","temperature":":thermometer:","humidity":":droplet:","luminosity":":sunny:","power":":cloud_lightning:","security":":rotating_light:","shutter":":beginner:","deamon_ok":":green_circle:","deamon_nok":":red_circle:","dep_ok":":green_circle:","dep_progress":":orange_circle:","dep_nok":":red_circle:","batterie_ok":":green_circle:","batterie_progress":":orange_circle:","batterie_nok":":red_circle:","zwave_ok":":green_circle:","zwave_nok":":red_circle:"}';
		$default = json_decode($json);
		$emojyarray = config::byKey('emojy', 'discordlink', $default);
		if ($reset == 1) {
			$emojyarray = $json;
		}
		config::save('emojy', $emojyarray, 'discordlink');
	}

	public static function setinvite() {
		$invite = discordlink::getinvite();
		config::save('invite', $invite, 'discordlink');
	}

	public static function updateinfo() {
		sleep(2);
		// discordlink::setinvite(); // Fonctionnalité obsolète - désactivée côté Node.js
		discordlink::updateobject();
		discordlink::setchannel();
	}

	public static function emojyconvert($_text): string
	{
		$_returntext = '';
		$textsplit = explode(" ", $_text);
		foreach ($textsplit as $value) {
			if (substr($value,0,4) === "emo_") {
				$emojy = discordlink::geticon(str_replace("emo_","",$value));
				$_returntext .= $emojy;
			} else {
				$_returntext .= $value;
			}
			$_returntext .= " ";
		}
		return $_returntext;
	}

	public static function checkall() {
		$dateRun = new DateTime();
		$_options = array('cron'=>true);
		$eqLogics = eqLogic::byType('discordlink');

		foreach ($eqLogics as $eqLogic) {

			$autorefreshDeamon = $eqLogic->getConfiguration('autorefreshDeamon');
			$autorefreshDependances = $eqLogic->getConfiguration('autorefreshDependances');
			$autorefreshZWave = $eqLogic->getConfiguration('autorefreshZWave');

			if ($eqLogic->getConfiguration('deamoncheck', 0) == 1 && $autorefreshDeamon != '') {
				try {
					$c = new Cron\CronExpression($autorefreshDeamon, new Cron\FieldFactory);
					if ($c->isDue($dateRun)) {
						log::add('discordlink', 'debug', 'DeamonCheck');
						$cmdDeamon = $eqLogic->getCmd('action', 'deamonInfo');
						$cmdDeamon->execCmd($_options);
					}
				} catch (Exception $exc) {
					log::add('discordlink', 'error', __('Expression cron non valide pour ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $autorefreshDeamon);
				}
			}
			if ($eqLogic->getConfiguration('depcheck', 0) == 1 && $autorefreshDependances != '') {
				try {
					$c = new Cron\CronExpression($autorefreshDependances, new Cron\FieldFactory);
					if ($c->isDue($dateRun)) {
						log::add('discordlink', 'debug', 'DepCheck');
						$cmdDep = $eqLogic->getCmd('action', 'dependanceInfo');
						$cmdDep->execCmd($_options);
					}
				} catch (Exception $exc) {
					log::add('discordlink', 'error', __('Expression cron non valide pour ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $autorefreshDependances);
				}
			}
			if ($eqLogic->getConfiguration('zwavecheck', 0) == 1 && $autorefreshZWave != '') {
				try {
					$c = new Cron\CronExpression($autorefreshZWave, new Cron\FieldFactory);
					if ($c->isDue($dateRun)) {
						log::add('discordlink', 'debug', 'ZWaveCheck');
						$cmdZwave = $eqLogic->getCmd('action', 'zwave');
						$cmdZwave->execCmd($_options);
					}
				} catch (Exception $exc) {
					log::add('discordlink', 'error', __('Expression cron non valide pour ', __FILE__) . $eqLogic->getHumanName() . ' : ' . $autorefreshZWave);
				}
			}

			if ($eqLogic->getConfiguration('connectcheck', 0) == 1) {
				log::add('discordlink', 'debug', 'connectcheck');
				$cmdDeamon = $eqLogic->getCmd('action', 'LastUser');
				$cmdDeamon->execCmd($_options);
			}
		}
	}
	/*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
	 */
	public static function cron() {
		discordlink::checkall();
	}

	/*public static function cron5() {
		discordlink::checkall();
	}*/

    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom*/
      public static function cronHourly() {
		discordlink::updateinfo();
      }

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom*/
      public static function cronDaily() {
		  $eqLogics = eqLogic::byType('discordlink');
		  foreach ($eqLogics as $eqLogic) {
			  $clearchannel = $eqLogic->getConfiguration('clearchannel',0);
			  if ($clearchannel ==1) {
			  	$cmd = $eqLogic->getCmd("action","deleteMessage");
				if (is_object($cmd)) {
					try {
						log::add('discordlink', 'info', 'Nettoyage quotidien du channel pour ' . $eqLogic->getName());
						$cmd->execCmd();
					} catch (Exception $e) {
						log::add('discordlink', 'error', 'Erreur lors du nettoyage quotidien pour ' . $eqLogic->getName() . ': ' . $e->getMessage());
					}
				}
			  }
		  }
      }

    /*     * *********************Méthodes d'instance************************* */

	public static function getchannel() {
		$deamon = discordlink::deamon_info();
		if ($deamon['state'] == 'ok') {
			$request_http = new com_http("http://" . config::byKey('internalAddr') . ":3466/getchannel");
			$request_http->setAllowEmptyReponse(true);//Autorise les réponses vides
			$request_http->setNoSslCheck(true);
			$request_http->setNoReportError(true);
			$result = $request_http->exec(6,3);//Time out à 3s 3 essais
			log::add('discordlink', 'debug', 'Set Invite Channel Json: '.$result);
			if (!$result) return "null";
			//$result = substr($result, 1, -1);
			$json = json_decode($result, true);
			return $json;
		} else {
			log::add('discordlink', 'debug', 'Set Invite Deamon : ERROR');
		}
	}

	public static function getinvite() {
		$deamon = discordlink::deamon_info();
		if ($deamon['state'] == 'ok') {
			log::add('discordlink', 'debug', 'Get Invite !!');
			$request_http = new com_http("http://" . config::byKey('internalAddr') . ":3466/getinvite");
			$request_http->setAllowEmptyReponse(true);//Autorise les réponses vides
			$request_http->setNoSslCheck(true);
			$request_http->setNoReportError(true);
			$result = $request_http->exec(6,3);//Time out à 3s 3 essais
			if (!$result) return "null";
			$result = substr($result, 1, -1);
			$json = json_decode($result, true);
			$invite = $json['invite'] ?? "null";
			log::add('discordlink', 'debug', 'Invite = '.$invite);
			return $invite;
		}
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'discordlink_dep';
		$resources = realpath(dirname(__FILE__) . '/../../resources/');
		$packageJson=json_decode(file_get_contents($resources.'/package.json'),true);
		$state='ok';
		foreach($packageJson["dependencies"] as $dep => $ver){
			if(!file_exists($resources.'/node_modules/'.$dep.'/package.json')) {
				$state='nok';
			}
		}
		$return['progress_file'] = jeedom::getTmpFolder('discordlink') . '/dependance';
		$return['state']=$state;
		return $return;
    }

	public static function dependancy_install($verbose = "false") {
		if (file_exists(jeedom::getTmpFolder('discordlink') . '/dependance')) {
			return false;
		}
		log::remove('discordlink_dep');
		$_debug = 0;
		if (log::getLogLevel('discordlink') == 100 || $verbose === "true" || $verbose === true) $_debug = 1;
		log::add('discordlink', 'info', 'Installation des dépendances : ');
		$resource_path = realpath(dirname(__FILE__) . '/../../resources');
		return array('script' => $resource_path . '/install.sh ' . $resource_path . ' discordlink ' . $_debug, 'log' => log::getPathToLog('discordlink_dep'));
	}


	public static function deamon_info() {
		$return = array();
		$return['log'] = 'discordlink_node';
		$return['state'] = 'nok';

		// Regarder si discordlink.js est lancé
		$pid = trim(shell_exec('ps ax | grep "resources/discordlink.js" | grep -v "grep" | wc -l'));
		if ($pid != '' && $pid != '0') $return['state'] = 'ok';

		// Regarder si le token est ok
		if (config::byKey('Token', 'discordlink', 'null') != "null") $return['launchable'] = 'ok';
		else {
			$return['launchable'] = 'nok';
			$return['launchable_message'] = "TOKEN DISCORD ABSENT ";
		}
		return $return;
	}

	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		log::add('discordlink', 'info', 'Lancement du bot');
		$url = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/discordlink/core/api/jeeDiscordlink.php?apikey=' . jeedom::getApiKey('discordlink');
		$log = $_debug ? '1' : '0';
		$sensor_path = realpath(dirname(__FILE__) . '/../../resources');
		$joueA = 'Travailler main dans la main avec votre Jeedom';
		if(config::byKey('joueA', 'discordlink') != ''){
			$joueA = config::byKey('joueA', 'discordlink');
		}
		$cmd = 'nice -n 19 node ' . $sensor_path . '/discordlink.js ' . network::getNetworkAccess('internal') . ' ' . config::byKey('Token', 'discordlink') . ' '.log::getLogLevel('discordlink') . ' ' . $url . ' ' . jeedom::getApiKey('discordlink') . ' ' . rawurlencode($joueA);
		log::add('discordlink', 'debug', 'Lancement démon discordlink : ' . $cmd);
		$result = exec('NODE_ENV=production nohup ' . $cmd . ' >> ' . log::getPathToLog('discordlink_node') . ' 2>&1 &');
		if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
			log::add('discordlink', 'error', $result);
			return false;
		}
		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') break;
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add('discordlink', 'error', 'Impossible de lancer le démon discordlink, vérifiez le port', 'unableStartDeamon');
			return false;
		}
		message::removeAll('discordlink', 'unableStartDeamon');
		log::add('discordlink', 'info', 'Démon discordlink lancé');
		discordlink::updateinfo();
		return true;
	}

	public static function deamon_stop() {
		log::add('discordlink', 'info', 'Arrêt du service discordlink');
		@file_get_contents("http://" . config::byKey('internalAddr') . ":3466/stop");
		sleep(3);
		if(shell_exec('ps aux | grep "resources/discordlink.js" | grep -v "grep" | wc -l') == '1') {
			exec('sudo kill $(ps aux | grep "resources/discordlink.js" | grep -v "grep" | awk \'{print $2}\') &>/dev/null');
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				sleep(1);
				exec('sudo kill -9 $(ps aux | grep "resources/discordlink.js" | grep -v "grep" | awk \'{print $2}\') &>/dev/null');
			}
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				sleep(1);
				exec('sudo kill -9 $(ps aux | grep "resources/discordlink.js" | grep -v "grep" | awk \'{print $2}\') &>/dev/null');
			}
		}
    }


    public function preInsert() {
		$this->setConfiguration('defaultColor', self::DEFAULT_COLOR);
		$this->setIsEnable(1);
    }

    public function postInsert() {

    }

    public function preSave() {
		$channel = $this->getConfiguration('channelid');
		log::add('discordlink', 'debug', 'setLogicalId : ' . $channel);
		if ($channel != 'null' && $channel != '') {
			if (isset($channel)) {
				$this->setLogicalId($channel);
				log::add('discordlink', 'debug', 'setLogicalId : ' . $channel);
			}
		} else {
			$this->setConfiguration('channelid', $this->getLogicalId());
		}
	}

	public static function geticon($_icon) {
		$icon = "null";
		$emojyArray = config::byKey('emojy', 'discordlink');
		$icon = $emojyArray[$_icon];
		if ($icon == "null" || $icon == "") {
			$icon = discordlink::addemojy($_icon);
		}
		$icon .= " ";
		return $icon;
	}

	public static function addemojy($_icon, $_emojy = "null") {
		$icon = "null";
		$emojyArray = config::byKey('emojy', 'discordlink');
		$icon = $emojyArray[$_icon];
		if ($icon == "null" || $icon == "") {
			if ($_emojy != "null") {
				$emojyArray[$_icon] = $_emojy;
			} else {
				$emojyArray[$_icon] = ":interrobang:";
			}
		}
		config::save('emojy', $emojyArray, 'discordlink');
		return $emojyArray[$_icon];
	}

	public static function CreateCmd() {

		$eqLogics = eqLogic::byType('discordlink');
		foreach ($eqLogics as $eqLogic) {

			$TabCmd = array(
				'sendMsg'=>array('reqplug' => '0', 'Libelle'=>'Envoi message', 'Type'=>'action', 'SubType' => 'message','request'=> 'sendMsg?message=#message#', 'visible' => 1, 'Template' => 'discordlink::message'),
				'sendMsgTTS'=>array('reqplug' => '0','Libelle'=>'Envoi message TTS', 'Type'=>'action', 'SubType' => 'message', 'request'=> 'sendMsgTTS?message=#message#', 'visible' => 1, 'Template' => 'discordlink::message'),
				'sendEmbed'=>array('reqplug' => '0','Libelle'=>'Envoi message évolué', 'Type'=>'action', 'SubType' => 'message', 'request'=> 'sendEmbed?color=#color#&title=#title#&url=#url#&description=#description#&field=#field#&countanswer=#countanswer#&footer=#footer#&timeout=#timeout#&quickreply=#quickreply#&defaultColor=#defaultColor#', 'visible' => 0),
				'sendFile'=>array('reqplug' => '0','Libelle'=>'Envoi fichier', 'Type'=>'action', 'SubType' => 'message', 'request'=> 'sendFile?patch=#patch#&name=#name#&message=#message#', 'visible' => 0),
				'deleteMessage'=>array('reqplug' => '0','Libelle'=>'Supprime les messages du channel', 'Type'=>'action', 'SubType'=>'other','request'=>'deleteMessage?null', 'visible' => 0),
				'deamonInfo'=>array('reqplug' => '0','Libelle'=>'Etat des démons', 'Type'=>'action', 'SubType'=>'other','request'=>'deamonInfo?null', 'visible' => 1),
				'dependanceInfo'=>array('reqplug' => '0','Libelle'=>'Etat des dépendances', 'Type'=>'action', 'SubType'=>'other','request'=>'dependanceInfo?null', 'visible' => 1),
				'zwave'=>array('reqplug' => 'openzwave','Libelle'=>'Etat des équipements Z-Wave', 'Type'=>'action', 'SubType'=>'other','request'=>'zwave?null', 'visible' => 1),
				'globalSummary'=>array('reqplug' => '0','Libelle'=>'Résumé général', 'Type'=>'action', 'SubType'=>'other','request'=>'globalSummary?null', 'visible' => 1),
				'objectSummary'=>array('reqplug' => '0','Libelle'=>'Résumé par objet', 'Type'=>'action', 'SubType'=>'select','request'=>'objectSummary?null', 'visible' => 1),
				'batteryinfo'=>array('reqplug' => '0','Libelle'=>'Résumé des batteries', 'Type'=>'action', 'SubType'=>'other','request'=>'batteryinfo?null', 'visible' => 1),
				'centreMsg'=>array('reqplug' => '0','Libelle'=>'Centre de messages', 'Type'=>'action', 'SubType'=>'other','request'=>'centreMsg?null', 'visible' => 1),
				'LastUser'=>array('reqplug' => '0','Libelle'=>'Dernière Connexion utilisateur', 'Type'=>'action', 'SubType'=>'other','request'=>'LastUser?null', 'visible' => 1),
				'covidSend'=>array('reqplug' => '0','Libelle'=>'Send Attestation', 'Type'=>'action', 'SubType'=>'other','request'=>'covidSend?null', 'visible' => 1),
				'1oldmsg'=>array('reqplug' => '0','Libelle'=>'Dernier message', 'Type'=>'info', 'SubType'=>'string', 'visible' => 1),
				'2oldmsg'=>array('reqplug' => '0','Libelle'=>'Avant dernier message', 'Type'=>'info', 'SubType'=>'string', 'visible' => 1),
				'3oldmsg'=>array('reqplug' => '0','Libelle'=>'Avant Avant dernier message', 'Type'=>'info', 'SubType'=>'string', 'visible' => 1)
			);

			//Chaque commande
			$Order = 0;
			foreach ($TabCmd as $CmdKey => $Cmd){
				$pluginisla = 0;
				if ($Cmd['reqplug'] != "0") {
					if (discordlink::testplugin($Cmd['reqplug'])) $pluginisla = 1;
				}
				if ($Cmd['reqplug'] == "0" || $pluginisla == 1)  {
					$Cmddiscordlink = $eqLogic->getCmd(null, $CmdKey);
					if (!is_object($Cmddiscordlink) ) {
						$Cmddiscordlink = new discordlinkCmd();
						$Cmddiscordlink->setName($Cmd['Libelle']);
						$Cmddiscordlink->setIsVisible($Cmd['visible']);
						$Cmddiscordlink->setType($Cmd['Type']);
						$Cmddiscordlink->setSubType($Cmd['SubType']);
					}
					$Cmddiscordlink->setEqLogic_id($eqLogic->getId());
					$Cmddiscordlink->setLogicalId($CmdKey);
					if ($Cmd['Type'] == "action" && $CmdKey != "deamonInfo") {
						$Cmddiscordlink->setConfiguration('request', $Cmd['request']);
						$Cmddiscordlink->setConfiguration('value', 'http://' . config::byKey('internalAddr') . ':3466/' . $Cmd['request'] . "&channelID=" . $eqLogic->getConfiguration('channelid'));
					}
					if ($Cmd['Type'] == "action" && $CmdKey == "deamonInfo") {
						$Cmddiscordlink->setConfiguration('request', $Cmd['request']);
						$Cmddiscordlink->setConfiguration('value', $Cmd['request']);
					}

					$Cmddiscordlink->setDisplay('generic_type','GENERIC_INFO');
					if (!empty($Cmd['Template'])) {
						$Cmddiscordlink->setTemplate("dashboard", $Cmd['Template']);
						$Cmddiscordlink->setTemplate("mobile", $Cmd['Template']);
					}
					$Cmddiscordlink->setOrder($Order);
					$Cmddiscordlink->setDisplay('message_placeholder', 'Message à envoyer sur Discord');
					$Cmddiscordlink->setDisplay('forceReturnLineBefore', true);
					$Cmddiscordlink->save();
					$Order++;
				}
			}
		}
	}

	public function getDefaultColor() {
		return $this->getConfiguration('defaultColor', self::DEFAULT_COLOR);	
	}

    public function postSave() {
		discordlink::CreateCmd();
		discordlink::updateobject();
	}

    public function preUpdate() {

    }

    public function postUpdate() {
		discordlink::CreateCmd();
    }

    public function preRemove() {

    }

    public function postRemove() {

    }

	public static function updateobject() {

		$listValue = '';
		foreach(jeeObject::all() as $object) {
			$listValue .= $object->getId()."|".$object->getName().";";
		}

		if ($listValue != '') {
			$eqLogics = eqLogic::byType('discordlink');
			foreach ($eqLogics as $eqLogic) {
				$Cmddiscordlink = $eqLogic->getCmd(null, 'objectSummary');
				$Cmddiscordlink->setConfiguration('listValue', $listValue);
				$Cmddiscordlink->save();
			}
		}
	}

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}
class discordlinkCmd extends cmd {

		/*     * *************************Attributs****************************** */


		/*     * ***********************Methode static*************************** */


		/*     * *********************Methode d'instance************************* */

		/*
		 * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
		  public function dontRemoveCmd() {
		  return true;
		  }
		 */

		public function execute($_options = null) {
			if ($this->getLogicalId() == 'refresh') {
				$this->getEqLogic()->refresh();
				return;
			}

			$deamon = discordlink::deamon_info();
			if ($deamon['state'] == 'ok') {
				$request = $this->buildRequest($_options);
				if ($request != 'truesendwithembed') {
					log::add('discordlink', 'debug', 'Envoi de ' . $request);
					$request_http = new com_http($request);
					$request_http->setAllowEmptyReponse(true);//Autorise les réponses vides
					if ($this->getConfiguration('noSslCheck') == 1) $request_http->setNoSslCheck(true);
					if ($this->getConfiguration('doNotReportHttpError') == 1) $request_http->setNoReportError(true);
					if (isset($_options['speedAndNoErrorReport']) && $_options['speedAndNoErrorReport'] == true) {// option non activée
						$request_http->setNoReportError(true);
						$request_http->exec(0.1, 1);
						return;
					}
					$result = $request_http->exec($this->getConfiguration('timeout', 6), $this->getConfiguration('maxHttpRetry', 1));//Time out à 3s 3 essais
					if (!$result) throw new Exception(__('Serveur injoignable', __FILE__));
					return true;
				} else {
					return true;
				}
			}
			return false;
		}

		private function buildRequest($_options = array()) {
			if ($this->getType() != 'action') return $this->getConfiguration('request');
			$cmdANDarg = explode('?', $this->getConfiguration('request'), 2);
			if (count($cmdANDarg) > 1)
				list($command, $arguments) = $cmdANDarg;
			else {
				$command=$this->getConfiguration('request');
				$arguments="";
			}
			switch ($command) {
				case 'sendMsg':
				case 'sendMsgTTS':
					$request = $this->build_ControledeSliderSelectMessage($_options);
				break;
				case 'sendEmbed':
					$request = $this->build_ControledeSliderSelectEmbed($_options);
				break;
				case 'sendFile':
					$request = $this->build_ControledeSliderSelectFile($_options);
				break;
				case 'deamonInfo':
					$request = $this->build_deamonInfo($_options);
				break;
				case 'dependanceInfo':
					$request = $this->build_dependanceInfo($_options);
				break;
				case 'globalSummary':
					$request = $this->build_globalSummary($_options);
				break;
				case 'batteryinfo':
					$request = $this->build_baterieglobal($_options);
				break;
				case 'objectSummary':
					$request = $this->build_objectSummary($_options);
				break;
				case 'zwave':
					$request = $this->build_zwave($_options);
				break;
				case 'centreMsg':
					$request = $this->build_centreMsg($_options);
				break;
				case 'LastUser':
					$request = $this->build_LastUser($_options);
				break;
				case 'covidSend':
					$request = $this->build_CovidSend($_options);
					break;
				case 'deleteMessage':
					$request = 'clearChannel?'; 
					break;
				default:
					$request = '';
				break;
			}
			if ($request != 'truesendwithembed') {
				$request = scenarioExpression::setTags($request);
				if (trim($request) == '') throw new Exception(__('Commande inconnue ou requête vide : ', __FILE__) . print_r($this, true));
				$channelID=str_replace("_player", "", $this->getEqLogic()->getConfiguration('channelid'));
				return 'http://' . config::byKey('internalAddr') . ':3466/' . $request . '&channelID=' . $channelID;
			} else {
				return $request;
			}
		}

		private function build_ControledeSliderSelectMessage($_options = array(), $default = "Une erreur est survenue") {

			$request = $this->getConfiguration('request');
			if ((isset($_options['message'])) && ($_options['message'] == "")) $_options['message'] = $default;
			if (!(isset($_options['message']))) $_options['message'] = "";
			$_options['message']=str_replace("|","\n",$_options['message']);
			$request = str_replace(array('#message#'), array(urlencode(self::decodeTexteAleatoire($_options['message']))), $request);
			log::add('discordlink_node', 'info', '---->RequestFinale:'.$request);
			return $request;
		}

		private function build_ControledeSliderSelectFile($_options = array(), $default = "Une erreur est survenu") {
			$patch = "null";
			$nameFile = "null";
			$message = "null";

			$request = $this->getConfiguration('request');
			if ((isset($_options['patch'])) && ($_options['patch'] == "")) $_options['patch'] = $default;
			if (!(isset($_options['patch']))) $_options['patch'] = "";

			if (isset($_options['files']) && is_array($_options['files'])) {
				foreach ($_options['files'] as $file) {
					if (version_compare(phpversion(), '5.5.0', '>=')) {
						$patch = $file;
						$files = new CurlFile($file);
						$nameexplode = explode('.',$files->getFilename());
						log::add('discordlink', 'info', $_options['title'].' taille : '.$nameexplode[sizeof($nameexplode)-1]);
						$nameFile = (isset($_options['title']) ? $_options['title'].'.'.$nameexplode[sizeof($nameexplode)-1] : $files->getFilename());
					}
				}
				$message = $_options['message'];

			} else {
				$patch = $_options['patch'];
				$nameFile = $_options['Name_File'];
			}

			$request = str_replace(array('#message#'),
			array(urlencode(self::decodeTexteAleatoire($message))), $request);
			$request = str_replace(array('#name#'),
			array(urlencode(self::decodeTexteAleatoire($nameFile))), $request);
			$request = str_replace(array('#patch#'),
			array(urlencode(self::decodeTexteAleatoire($patch))), $request);

			log::add('discordlink_node', 'info', '---->RequestFinale:'.$request);
			return $request;
		}

		private function build_ControledeSliderSelectEmbed($_options = array(), $default = "Une erreur est survenue") {

			$request = $this->getConfiguration('request');

			$titre = "null";
			$url = "null";
			$description = "null";
			$footer = "null";
			$field = "null";
			$colors = "null";
			$timeout = "null";
			$countanswer = "null";
			$quickreply = "null";
			$defaultColor = $this->getEqLogic()->getDefaultColor();

			if (isset($_options['answer'])) {
				if (("" != ($_options['title']))) $titre = $_options['title'];
				$colors = "#1100FF";

				if ($_options['answer'][0] != "") {
					$answer = $_options['answer'];
					$timeout = $_options['timeout'];
					$description = "";

					$a = 0;
					$url = "[";
					$choix = [":regional_indicator_a:", ":regional_indicator_b:", ":regional_indicator_c:", ":regional_indicator_d:", ":regional_indicator_e:", ":regional_indicator_f:", ":regional_indicator_g:", ":regional_indicator_h:", ":regional_indicator_i:", ":regional_indicator_j:", ":regional_indicator_k:", ":regional_indicator_l:", ":regional_indicator_m:", ":regional_indicator_n:", ":regional_indicator_o:", ":regional_indicator_p:", ":regional_indicator_q:", ":regional_indicator_r:", ":regional_indicator_s:", ":regional_indicator_t:", ":regional_indicator_u:", ":regional_indicator_v:", ":regional_indicator_w:", ":regional_indicator_x:", ":regional_indicator_y:", ":regional_indicator_z:"];
					while ($a < count($answer)) {
						$description .= $choix[$a] . " : " . $answer[$a];
						$description .= "
";
						$url .= '"' . $answer[$a] . '",';
						$a++;
					}
					$url = rtrim($url, ',');
					$url .= ']';
					$countanswer = count($answer);
				} else {
					$timeout = $_options['timeout'];
					$countanswer = 0;
					$description = "Votre prochain message sera la réponse.";
					$url = "text";
				}
			} else {
				if (isset($_options['Titre'])) if (("" != ($_options['Titre']))) $titre = $_options['Titre'];
				if (isset($_options['url'])) if (("" != ($_options['url']))) $url = $_options['url'];
				if (isset($_options['description'])) if (("" != ($_options['description']))) $description = $_options['description'];
				if (isset($_options['footer'])) if (("" != ($_options['footer']))) $footer = $_options['footer'];
				if (isset($_options['colors'])) if (("" != ($_options['colors']))) $colors = $_options['colors'];
				if (isset($_options['field'])) if (("" != ($_options['field']))) $field = json_encode($_options['field']);
				if (isset($_options['quickreply'])) if (("" != ($_options['quickreply']))) $quickreply = $_options['quickreply'];
			}

			$description = discordlink::emojyconvert($description);
			log::add('discordlink', 'debug', 'description : '.$description);

			$description = str_replace("|","\n",$description);

			$request = str_replace(array('#title#'),
			array(urlencode(self::decodeTexteAleatoire($titre))), $request);
			$request = str_replace(array('#url#'),
			array(urlencode(self::decodeTexteAleatoire($url))), $request);
			$request = str_replace(array('#description#'),
			array(urlencode(self::decodeTexteAleatoire($description))), $request);
			$request = str_replace(array('#footer#'),
			array(urlencode(self::decodeTexteAleatoire($footer))), $request);
			$request = str_replace(array('#countanswer#'),
			array(urlencode(self::decodeTexteAleatoire($countanswer))), $request);
			$request = str_replace(array('#field#'),
			array(urlencode(self::decodeTexteAleatoire($field))), $request);
			$request = str_replace(array('#color#'),
			array(urlencode(self::decodeTexteAleatoire($colors))), $request);
			$request = str_replace(array('#defaultColor#'),
			array(urlencode(self::decodeTexteAleatoire($defaultColor))), $request);
			$request = str_replace(array('#timeout#'),
			array(urlencode(self::decodeTexteAleatoire($timeout))), $request);
			$request = str_replace(array('#quickreply#'),
			array(urlencode(self::decodeTexteAleatoire($quickreply))), $request);

			log::add('discordlink_node', 'info', '---->RequestFinale:'.$request);
			return $request;
		}

		public static function decodeTexteAleatoire($_text) {
			$return = $_text;
			if (strpos($_text, '|') !== false && strpos($_text, '[') !== false && strpos($_text, ']') !== false) {
				$replies = interactDef::generateTextVariant($_text);
				$random = rand(0, count($replies) - 1);
				$return = $replies[$random];
			}
			preg_match_all('/{\((.*?)\) \?(.*?):(.*?)}/', $return, $matches, PREG_SET_ORDER, 0);
			$replace = array();
			if (is_array($matches) && count($matches) > 0) {
				foreach ($matches as $match) {
					if (count($match) != 4) {
						continue;
					}
					$replace[$match[0]] = (jeedom::evaluateExpression($match[1])) ? trim($match[2]) : trim($match[3]);
				}
			}
			return str_replace(array_keys($replace), $replace, $return);
		}

		public function build_deamonInfo($_options = array()) {
			$message='';
			$colors = '#00ff08';

			foreach(plugin::listPlugin(true) as $plugin){
				if($plugin->getHasOwnDeamon() && config::byKey('deamonAutoMode', $plugin->getId(), 1) == 1) {
					$deamon_info = $plugin->deamon_info();
					if ($deamon_info['state'] != 'ok') {
						$message .='|'.discordlink::geticon("deamon_nok").$plugin->getName().' ('.$plugin->getId().')';
						if ($colors != '#ff0000') $colors = '#ff0000';
					} else {
						$message .='|'.discordlink::geticon("deamon_ok").$plugin->getName().' ('.$plugin->getId().')';
					}

					if ($plugin->getId() == 'blea') {
						if (discordlink::testplugin('blea')) {
							$remotes = blea_remote::all();
							foreach ($remotes as $remote) {
								$last = $remote->getCache('lastupdate','0');
								if ($last == '0' || time() - strtotime($last)>65){
									$message .='|'.discordlink::geticon("deamon_nok").' Antenne BLEA : '.$remote->getRemoteName();
									if ($colors != '#ff0000') $colors = '#ff0000';
								} else {
									$message .='|'.discordlink::geticon("deamon_ok").' Antenne BLEA : '.$remote->getRemoteName();
								}
							}
						}
					}

				}
			}

			if (isset($_options['cron']) AND $colors == '#00ff08') return 'truesendwithembed';
			$message=str_replace("|","\n",$message);
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('Titre'=>'Etat des démons', 'description'=> $message, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
			$cmd->execCmd($_options);
			return 'truesendwithembed';
		}

		public function build_dependanceInfo($_options = array()) {
			$message='';
			$colors = '#00ff08';

			foreach(plugin::listPlugin(true) as $plugin){
				if($plugin->getHasDependency()) {
					$dependency_info = $plugin->dependancy_info();
					if ($dependency_info['state'] == 'ok') {
						$message .='|'.discordlink::geticon("dep_ok").$plugin->getName().' ('.$plugin->getId().')';
					} elseif ($dependency_info['state'] == 'in_progress') {
						$message .='|'.discordlink::geticon("dep_progress").$plugin->getName().' ('.$plugin->getId().')';
						if ($colors == '#00ff08') $colors = '#ffae00';
					} else {
						$message .='|'.discordlink::geticon("dep_nok").' ('.$plugin->getId().')';
						if ($colors != '#ff0000') $colors = '#ff0000';
					}

				}
			}

			if (isset($_options['cron']) && $colors == '#00ff08') return 'truesendwithembed';
			$message=str_replace("|","\n",$message);
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('Titre'=>'Etat des dépendances', 'description'=> $message, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
			$cmd->execCmd($_options);
			return 'truesendwithembed';
		}

		public function build_globalSummary($_options = array()) {

			$objects = jeeObject::all();
			$def = config::byKey('object:summary');
			$values = array();
			$message='';
			foreach ($def as $key => $value) {
				$result ='';
				$result = jeeObject::getGlobalSummary($key);
				if ($result == '') continue;
				$message .='|'.discordlink::geticon($key).' *** '. $result.' '.$def[$key]['unit'] .' ***		('.$def[$key]['name'].')';
			}
				$message=str_replace("|","\n",$message);
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('Titre'=>'Résumé général', 'description'=> $message, 'colors'=> '#0033ff', 'footer'=> 'By DiscordLink');
				$cmd->execCmd($_options);

			return 'truesendwithembed';
		}

		public function build_baterieglobal($_options = array()) {
			$message='null';
			$colors = '#00ff08';
			$seuil_alert = 30;
			$seuil_critique = 10;
			$nb_alert = 0;
			$nb_critique = 0;
			$nb_battery = 0;
			$nb_total = 0;
			$nb_ligne = 0;

			$eqLogics = eqLogic::all(true);
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');

			$list_battery = '';
			foreach($eqLogics as $eqLogic)
			{
				$nb_total = $nb_total + 1;
				if((is_numeric(eqLogic::byId($eqLogic->getId())->getStatus('battery')) == 1)) {
					$nb_battery = $nb_battery + 1;
					if(eqLogic::byId($eqLogic->getId())->getStatus('battery') <= $seuil_alert) {
						if(eqLogic::byId($eqLogic->getId())->getStatus('battery') <= $seuil_critique) {

							$list_battery .= "\n".discordlink::geticon("batterie_nok").substr($eqLogic->getHumanName(), strrpos($eqLogic->getHumanName(), '[',-1) + 1, -1) . ' => __***' . eqLogic::byId($eqLogic->getId())->getStatus('battery') . "%***__";
							$nb_critique = $nb_critique + 1;
							if ($colors != '#ff0000') $colors = '#ff0000';
						} else {
							$list_battery .= "\n".discordlink::geticon("batterie_progress").substr($eqLogic->getHumanName(), strrpos($eqLogic->getHumanName(), '[',-1) + 1, -1) . ' =>  __***' . eqLogic::byId($eqLogic->getId())->getStatus('battery') . "%***__";
							$nb_alert = $nb_alert + 1;
							if ($colors == '#00ff08') $colors = '#ffae00';
						}
					} else {
						$list_battery = $list_battery . "\n" .discordlink::geticon("batterie_ok"). substr($eqLogic->getHumanName(), strrpos($eqLogic->getHumanName(), '[',-1) + 1, -1) . ' =>  __***' . eqLogic::byId($eqLogic->getId())->getStatus('battery') . "%***__";
					}

					if ($nb_ligne == 20) {
						$message = $list_battery;
						$message=str_replace("|","\n",$message);
						$_options = array('Titre'=>'Résumé Batteries : ', 'description'=> $message, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
						$cmd->execCmd($_options);
						$nb_ligne = 0;
						$list_battery = '';
						$message = '';
					}
					$nb_ligne++;
				}
			}

			$message = $list_battery;
			$message=str_replace("|","\n",$message);
			$_options = array('Titre'=>'Résumé Batteries : ', 'description'=> $message, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
			$cmd->execCmd($_options);

			$message2 = "Batterie en alerte : __***" . $nb_alert . "***__\n Batterie critique : __***".$nb_critique."***__";

			$message2=str_replace("|","\n",$message2);
			$_options2 = array('Titre'=>'Résumé Batterie', 'description'=> $message2, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
			$cmd->execCmd($_options2);

			return 'truesendwithembed';
		}

		public function build_objectSummary($_options = array()) {

			$idobject = $_options['select'];
			log::add('discordlink', 'debug', 'idobject : '.$idobject);
			$object = jeeObject::byId($idobject);
			$def = config::byKey('object:summary');
			$message='';
			foreach ($def as $key => $value) {
				$result = '';
				$result = $object->getSummary($key);
				if ($result == '') continue;
				$message .='|'.discordlink::geticon($key).' *** '. $result.' '.$def[$key]['unit'] .' ***		('.$def[$key]['name'].')';
			}
				$message=str_replace("|","\n",$message);
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('Titre'=>'Résumé : '.$object->getname(), 'description'=> $message, 'colors'=> '#0033ff', 'footer'=> 'By DiscordLink');
				$cmd->execCmd($_options);

			return 'truesendwithembed';
		}

		public function build_zwave($_options = array()) {

			if (discordlink::testplugin('openzwave')) {
				$message = '';
				$colors = '#00ff08';
				$maxTime = $this->getEqLogic()->getConfiguration('TempMax', 43200);
				$_format = 'Y-m-d H:M:S';
				$eqLogics = eqLogic::byType('openzwave');
				foreach($eqLogics as $eqLogic) {
					$zwaveexclude = $this->getEqLogic()->getConfiguration('zwaveIdExclude', '');
					if (!(strpos($zwaveexclude, $eqLogic->getLogicalId()) !== false)) {
						$maxDate = date($_format, "1970-1-1 00:00:00");
						$collectDate = strtotime($eqLogic->getStatus('lastCommunication', date($_format)));
						//$scenario->setLog( 'Commande ' . $cmd->getHumanName() . ' - ' . $collectDate);
						$maxDate = max($maxDate, $collectDate);
						$elapsedTime = time() - $maxDate;
						if ($elapsedTime >= $maxTime) {
							$message .= "|".discordlink::geticon("zwave_nok"). " ". $eqLogic->getName(). ' ('.$elapsedTime.')';
							if ($colors != '#ff0000') $colors = '#ff0000';
						} else {
							$message .= "|".discordlink::geticon("zwave_ok"). " ". $eqLogic->getName().' ('.$elapsedTime.')';
						}
					}
				}
				// log fin de traitement
				if (isset($_options['cron']) && $colors == '#00ff08') return 'truesendwithembed';
				$message=str_replace("|","\n",$message);
				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('Titre'=>'Zwave Info ', 'description'=> $message, 'colors'=> $colors, 'footer'=> 'By DiscordLink');
				$cmd->execCmd($_options);
			}
			return 'truesendwithembed';
		}

		public function build_centreMsg($_options = array()) {

			// Parcours de tous les Updates
				$listUpdate = "";
				$nbMaj = 0;
				$msgBloq = "";
				$nbMajBloq = 0;
				foreach (update::all() as $update) {
					$monUpdate = $update->getName();
					$statusUpdate = strtolower($update->getStatus());
					$configUpdate = $update->getConfiguration('doNotUpdate');
					if ($configUpdate == 1) {
						$configUpdate = " **(MaJ bloquée)**";
						$nbMajBloq++;
					}else{$configUpdate = "";}
					if ($statusUpdate == "update") {
						$nbMaj++;
						if ($listUpdate == ""){ $listUpdate = $nbMaj."- ".$monUpdate.$configUpdate; } else {$listUpdate .= "\n".$nbMaj."- ".$monUpdate.$configUpdate;}
					}
				}
				if ($nbMajBloq == 0) {
					$msgBloq = "";
				} elseif ($nbMajBloq == 1) {
					$msgBloq = " (dont **".$nbMajBloq."** bloquée)";
				} else {
					$msgBloq = " (dont **".$nbMajBloq."** bloquées)";
				}

				// Message selon le nombre de mises à jours
				if ($nbMaj == 0) {
					$msg = "*Vous n'avez pas de mises à jour en attente !*";
				}elseif  ($nbMaj == 1) {
					$msg = "*Vous avez **".$nbMaj."** mise à jour en attente".$msgBloq." :*"."\n".$listUpdate;
				} else {
					$msg = "*Vous avez **".$nbMaj."** mises à jour en attente".$msgBloq." :*"."\n".$listUpdate;
				}

				$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
				$_options = array('Titre'=>':gear: CENTRE DE MISES A JOUR :gear:', 'description'=> $msg, 'colors'=> '#ff0000', 'footer'=> 'By jcamus86');
				$cmd->execCmd($_options);

				// -------------------------------------------------------------------------------------- //
				$msg = "";
				$nbMsg = 0;
				$nbMsgMax = 5;		//Nombre de messages par bloc de notification
				$MsgBloc = 1;
				$listMessage = message::all();
				foreach ($listMessage as $message){
					$nbMsg++;
					if (!($nbMsg <= $nbMsgMax)){
						$nbMsg = 1;
						$MsgBloc = $MsgBloc + 1;
					}

					$msg[$MsgBloc] .= "[".$message->getDate()."]";
					$msg[$MsgBloc] .= " (".$message->getPlugin().") :";
					$msg[$MsgBloc] .= "\n" ;
					($message->getAction() != "") ? $msg[$MsgBloc] .= " (Action : ".$message->getAction().")" : null;
					$msg[$MsgBloc] .= " ".$message->getMessage()."\n";
					$msg[$MsgBloc] .= "\n" ;
					$msg[$MsgBloc] = html_entity_decode($msg[$MsgBloc], ENT_QUOTES | ENT_HTML5);
				}

				// Message selon le nombre de messages
				if ($nbMsg == 0){
					$i=1;
					$msg = "*Le centre de message est vide !*";
					$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
					$_options = array('Titre'=>':clipboard: CENTRE DE MESSAGES :clipboard:', 'description'=> $msg, 'colors'=> '#ff8040', 'footer'=> 'By Jcamus86');
					$cmd->execCmd($_options);
				}else{
					$i=0;
					foreach ($msg as $value){
						$i++;
						$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
						$_options = array('Titre'=>':clipboard: CENTRE DE MESSAGES '.$i.'/'.count($msg).' :clipboard:', 'description'=> $value, 'colors'=> '#ff8040', 'footer'=> 'By Jcamus86');
						$cmd->execCmd($_options);
					}
				}

			return 'truesendwithembed';
		}

		public function build_LastUser($_options = array()) {
			$result = discordMsg::LastUser();
			if (isset($_options['cron']) && !$result['cronOk']) return 'truesendwithembed';
			$message=str_replace("|","\n",$result['Message']);
			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('Titre'=>$result['Titre'], 'description'=> $message, 'colors'=> '#ff00ff', 'footer'=> 'By Yasu et Jcamus86');
			$cmd->execCmd($_options);
			return 'truesendwithembed';
		}

		public function build_CovidSend($_options = array()) {

			$motif = "travail";
			$datesortie = "Maintenant";
			$heuresortie = "Maintenant";

			$users = config::byKey('user', 'discordlink');
			$user = $_options['user'];
			$user = $users[$user];
			log::add('discordlink', 'debug', 'Users '.json_encode($users));
			log::add('discordlink', 'debug', 'User '.json_encode($user));

			$nom = $user['nomUser'];
			$prenom = $user['prenomUser'];
			$datenaissance = $user['dateNaissanceUser'];
			$villenaissance = $user['villeNaissanceUser'];

			if (("" != ($_options['motif']))) $motif = $_options['motif'];
			if (("" != ($_options['datesortie']))) $datesortie = $_options['datesortie'];
			if (("" != ($_options['heuresortie']))) $heuresortie = $_options['heuresortie'];

			$result = discordlinkCovid::generateCovid($prenom,$nom,$datenaissance,$villenaissance,$motif,$datesortie,$heuresortie);
			$message=str_replace("|","\n","[Attestation Covid]($result)");

			$fields = array(
				array("name"=> "Nom", "value" => $nom, "inline" => 1),
				array("name"=> "Prénom", "value" => $prenom, "inline" => 1),
				array("name"=> "Motif", "value" => $motif, "inline" => 0),
				array("name"=> "Date", "value" => $datesortie, "inline" => 0),
				array("name"=> "Heure", "value" => $heuresortie, "inline" => 0)
			);

			$cmd = $this->getEqLogic()->getCmd('action', 'sendEmbed');
			$_options = array('Titre'=>"Votre attestation Covid", 'description'=> $message, 'colors'=> '#ff00ff', 'footer'=> 'By Noodom & Thibaut', 'field'=> $fields);
			$cmd->execCmd($_options);
			return 'truesendwithembed';
		}

		public function getWidgetTemplateCode($_version = 'dashboard', $_clean = true, $_widgetName = '') {
			$data = null;
			if ($_version != 'scenario') return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
			
			list($command, ) = explode('?', $this->getConfiguration('request'), 2);
			if ($command == 'sendMsg')
				$data = getTemplate('core', 'scenario', 'cmd.sendMsg', 'discordlink');
			if ($command == 'sendMsgTTS')
				$data = getTemplate('core', 'scenario', 'cmd.sendMsgtts', 'discordlink');
			if ($command == 'sendEmbed')
				$data = getTemplate('core', 'scenario', 'cmd.sendEmbed', 'discordlink');
			if ($command == 'sendFile')
				$data = getTemplate('core', 'scenario', 'cmd.sendFile', 'discordlink');
			if ($command == 'covidSend')
				$data = getTemplate('core', 'scenario', 'cmd.covidSend', 'discordlink');
			
			$defaultColor = $this->getEqLogic()->getDefaultColor();
			$replace = [
				'#defaultColor#'  => $defaultColor,
				'#defaultTitle#' => '',
				'#defaultDescription#' => '',
				'#defaultUrl#' => '',	
				'#defaultFooter#' => '',	
			 ] ;
			$data = str_replace(array_keys($replace), array_values($replace), $data);
			
			if (!is_null($data)) {
				if (version_compare(jeedom::version(),'4.2.0','>=')) {
					 if(!is_array($data)) return array('template' => $data, 'isCoreWidget' => false);
				} else return $data;
			}
			return parent::getWidgetTemplateCode($_version, $_clean, $_widgetName);
		}

		/*     * **********************Getteur Setteur*************************** */
	}
?>
