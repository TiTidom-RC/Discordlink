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


(function () {
  'use strict';

  const AJAX_URL = 'plugins/discordlink/core/ajax/discordlink.ajax.php';

  /*
   * Fonction pour l'ajout de commande, appelé automatiquement par plugin.template
   */
  window.addCmdToTable = function (_cmd) {
    if (!isset(_cmd)) {
      _cmd = {
        configuration: {},
      };
    }

    if (!isset(_cmd.configuration)) {
      _cmd.configuration = {};
    }

    // Build test buttons
    const testButtons = is_numeric(_cmd.id)
      ? '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> <a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
      : '';

    // Prepare specific inputs
    let requestInput = '';
    if (init(_cmd.type) === 'action') {
      requestInput = '<div style="margin-top:5px;"><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="width:100%;" ' + (init(_cmd.logicalId) !== '' ? 'readonly' : '') + '></div>';
      if (init(_cmd.logicalId) === 'refresh') requestInput = '';
    }

    // Build row HTML
    const rowHtml = `<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span></td>
      <td>
        <div class="input-group">
          <input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">
          <span class="input-group-btn"><a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a></span>
          <span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>
        </div>
      </td>
      <td>
          ${requestInput}
      </td>
      <td>
        <label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible"/>{{Afficher}}</label>
        ${init(_cmd.type) == 'info' ? '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized"/>{{Historiser}}</label>' : ''}
        ${init(_cmd.subType) == 'binary' ? '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label>' : ''}
        ${init(_cmd.subType) == 'numeric' ? `
        <div style="margin-top:7px;">
          <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">
          <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">
          <input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">
        </div>` : ''}
        <div style="display:none;">
          <span class="type" type="${init(_cmd.type)}"></span>
          <span class="subType" subType="${init(_cmd.subType)}"></span>
        </div>
      </td>
      <td>
        <span class="cmdAttr" data-l1key="htmlstate"></span>
      </td>
      <td>
        ${testButtons}
        <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove" title="{{Supprimer la commande}}"></i>
      </td>`;

    const newRow = document.createElement('tr');
    newRow.className = 'cmd';
    newRow.setAttribute('data-cmd_id', init(_cmd.id));
    newRow.innerHTML = rowHtml;

    const tableBody = document.querySelector('#table_cmd tbody');
    if (tableBody) {
      tableBody.appendChild(newRow);
      newRow.setJeeValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(newRow, init(_cmd.subType));
    }
  };

  /**
   * Hook Jeedom pour l'initialisation de l'équipement
   */
  window.printEqLogic = function (_json) {
    const daemonCheck = document.getElementById('daemonCheck');
    const dependencyCheck = document.getElementById('dependencyCheck');
    if (daemonCheck) daemonCheck.dispatchEvent(new Event('change'));
    if (dependencyCheck) dependencyCheck.dispatchEvent(new Event('change'));
  };

  /**
   * Event Delegation pour l'ensemble du plugin
   */
  document.body.addEventListener('click', function (e) {
    // Emoji Settings
    if (e.target.closest('[data-action="emojiSettings"]')) {
      jeeDialog.dialog({
        id: 'md_emojiDiscordlink',
        title: "{{Emojis}}",
        contentUrl: 'index.php?v=d&plugin=discordlink&modal=emoji.discordlink',
        width: '90%',
        height: '80%',
        top: '10vh',
        onClose: function() {
          if (typeof cleanupEmoji === 'function') {
            cleanupEmoji();
          }
        }
      });
    }

    // Cron Daemon Generator
    if (e.target.closest('#bt_cronDaemonGenerator')) {
      jeedom.getCronSelectModal({}, function (result) {
        const input = document.querySelector(".eqLogicAttr[data-l1key=configuration][data-l2key=autoRefreshDaemon]");
        if (input) input.value = result.value;
      });
    }

    // Cron Dependency Generator
    if (e.target.closest('#bt_cronDependencyGenerator')) {
      jeedom.getCronSelectModal({}, function (result) {
        const input = document.querySelector(".eqLogicAttr[data-l1key=configuration][data-l2key=autoRefreshDependency]");
        if (input) input.value = result.value;
      });
    }

    // Refresh Channels
    const refreshBtn = e.target.closest('#bt_refreshChannels');
    if (refreshBtn) {
      e.preventDefault();
      const eqIdInput = document.querySelector('.eqLogicAttr[data-l1key=id]');
      const eqId = eqIdInput ? eqIdInput.value : null;

      const icon = refreshBtn.querySelector('i');
      if (icon) icon.classList.add('fa-spin');

        domUtils.ajax({
          type: "POST",
          url: AJAX_URL,
          data: {
            action: "getChannels",
            id: eqId
          },
          dataType: 'json',
          error: function (request, status, error) {
            handleAjaxError(request, status, error);
            if (icon) icon.classList.remove('fa-spin');
          },
          success: function (data) {
            if (icon) icon.classList.remove('fa-spin');

            const select = document.querySelector('select[data-l2key=channelId]');
            if (data.result && data.result.channels && select) {
              const currentVal = select.value;
              select.innerHTML = ''; // Clear options

              if (data.result.channels.length > 0) {
                data.result.channels.forEach(channel => {
                  const option = document.createElement('option');
                  option.value = channel.id;
                  option.text = `(${channel.guildName}) ${channel.name}`;
                  select.appendChild(option);
                });
              } else {
                const option = document.createElement('option');
                option.value = 'null';
                option.text = 'Pas de channel disponible';
                select.appendChild(option);
              }

              if (data.result.current) {
                select.value = String(data.result.current);
              } else if (currentVal && currentVal !== 'null' && select.querySelector(`option[value="${currentVal}"]`)) {
                select.value = currentVal;
              }
            } else {
              jeedomUtils.showAlert({
                message: 'Impossible de récupérer les channels.',
                level: 'danger'
              });
            }
          }
        });
    }
  });

  document.body.addEventListener('change', function (e) {
    // Daemon Checkbox Visibility
    if (e.target.id === 'daemonCheck') {
      const els = document.querySelectorAll('.daemon_freq');
      els.forEach(el => el.style.display = e.target.checked ? '' : 'none');
    }
    // Dependency Checkbox Visibility
    if (e.target.id === 'dependencyCheck') {
      const els = document.querySelectorAll('.dependency_freq');
      els.forEach(el => el.style.display = e.target.checked ? '' : 'none');
    }
  });

})();
