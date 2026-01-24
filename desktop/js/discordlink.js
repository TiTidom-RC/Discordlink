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


/*
 * Fonction pour l'ajout de commande, appelé automatiquement par plugin.template
 */
function addCmdToTable(_cmd) {
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
     if (init(_cmd.logicalId) == 'refresh') requestInput = ''; 
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
      <span class="type" type="${init(_cmd.type)}">${jeedom.cmd.availableType()}</span>
      <span class="subType" subType="${init(_cmd.subType)}"></span>
    </td>
    <td>
      <label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible"/>{{Afficher}}</label>
      <label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized"/>{{Historiser}}</label>
      <label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label>
      <div style="margin-top:7px;">
        <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">
        <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">
        <input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:30%;max-width:80px;display:inline-block;margin-right:2px;">
      </div>
    </td>
    <td>
        ${requestInput}
        <div class="content_cmd" style="margin-top: 5px;"></div> 
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
    const $newRow = $(newRow);
    $newRow.setValues(_cmd, '.cmdAttr');
    jeedom.cmd.changeType($newRow, init(_cmd.subType));
    
    // Logic from original code for specific subtypes
    if (isset(_cmd.subType) && _cmd.subType == "message") {
        let divCmd = $newRow.find(".content_cmd").empty();
        divCmd.append(
            $("<label>")
            .text("{{Message}}")
            .append(
                $('<input class="cmdAttr form-control input-sm">')
                .attr("data-l1key", "display")
                .attr("data-l2key", "message_placeholder")
            )
        );
    }
  }
}

// Expose functions globally for Jeedom core (plugin.template.js)
window.addCmdToTable = addCmdToTable;


$("#bt_cronGeneratordeamon").on("click", function () {
  jeedom.getCronSelectModal({}, function (result) {
    $(
      ".eqLogicAttr[data-l1key=configuration][data-l2key=autoRefreshDaemon]",
    ).value(result.value);
  });
});

$("#bt_cronGeneratorDependance").on("click", function () {
  jeedom.getCronSelectModal({}, function (result) {
    $(
      ".eqLogicAttr[data-l1key=configuration][data-l2key=autoRefreshDependency]",
    ).value(result.value);
  });
});


function printEqLogic(_json) {
  $('#daemonCheck').trigger('change');
  $('#dependencyCheck').trigger('change');
}

$("#daemonCheck").on("change", function () {
  if ($(this).is(':checked')) {
    $('.deamon').show();
  } else {
    $('.deamon').hide();
  }
});

$("#dependencyCheck").on("change", function () {
  if ($(this).is(':checked')) {
    $('.dependance').show();
  } else {
    $('.dependance').hide();
  }
});


$('body').off('click', '#bt_refreshChannels').on('click', '#bt_refreshChannels', function (event) {
  event.preventDefault();
  let btn = $(this);
  let eqId = $('.eqLogicAttr[data-l1key=id]').val();
  
  btn.find('i').addClass('fa-spin');
  $.ajax({
      type: "POST",
      url: "plugins/discordlink/core/ajax/discordlink.ajax.php",
      data: {
          action: "getChannels",
          id: eqId
      },
      dataType: 'json',
      error: function (request, status, error) {
          handleAjaxError(request, status, error);
          btn.find('i').removeClass('fa-spin');
      },
      success: function (data) {
          btn.find('i').removeClass('fa-spin');

          if (data.result && data.result.error) {
              jeedomUtils.showAlert({
                  message: data.result.error,
                  level: 'warning'
              });
              return;
          }

          if (data.result && data.result.channels) {
              let select = $('select[data-l2key=channelId]');
              let currentVal = select.val();
              select.empty();
              
              if (data.result.channels.length > 0) {
                  for (let i in data.result.channels) {
                      select.append('<option value="' + data.result.channels[i].id + '">(' + data.result.channels[i].guildName + ') ' + data.result.channels[i].name + '</option>');
                  }
              } else {
                  select.append($('<option>', { value: 'null', text: 'Pas de channel disponible' }));
              }
              
              if (data.result.current) {
                   select.val(String(data.result.current));
              } else if (currentVal && currentVal !== 'null' && select.find('option[value="' + currentVal + '"]').length > 0) {
                  select.val(currentVal);
              }
          } else {
               jeedomUtils.showAlert({
                  message: 'Impossible de récupérer les channels.',
                  level: 'danger'
              });
          }
      }
  });
});
