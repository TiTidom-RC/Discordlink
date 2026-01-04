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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <div class="form-group">
            <label class="col-lg-4 control-label">{{Token : }}</label>
            <div class="col-lg-6">
                <input class="configKey form-control" data-l1key="Token" />
            </div>
        </div>
        </br>
        <div class="form-group" hidden>
            <label class="col-lg-4 control-label">{{Bot Invite : }}</label>
            <div class="col-lg-6">
                <?php
                    echo '<a class="btn btn-success btn-sm bt_getinvite">Ajouter votre bot à votre serveur discord</a>';
                    echo '<a class="btn btn-danger btn-sm bt_errorinvite">Erreur, lance ton démon et si tu n\'y arrives pas, clique sur moi</a>';
                    include_file('desktop', 'configuration', 'js', 'discordlink');
                ?>
            </div>
        </div>
      <br/>
      <div class="form-group">
          <label class="col-lg-4 control-label">{{Joue à : }}</label>
          <div class="col-lg-6">
              <input class="configKey form-control" data-l1key="joueA" placeholder="Travailler main dans la main avec votre Jeedom"/>
          </div>
      </div>
    </fieldset>
</form>
