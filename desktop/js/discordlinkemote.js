
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

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
initemojy();
function initemojy() {
  
  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'getemojy'
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error, $('#div_AboAlert'));
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_AboAlert').showAlert({message: 'ERROR', level: 'danger'});
        return;
      }
      for (var i in data.result) {
        addEmojyToTable(data.result[i]);
      }
    }
  });
}

function addEmojyToTable(_cmd) {

    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    const tr =  ' <tr class="emojy">'
      +   '<td>'
      +     '<div class="row">'
      +       '<div class="col-lg-8">'
      +         '<input class="emojyAttr form-control input-sm" data-l1key="keyEmojy">'
      +       '</div>'
      +   '</td>'
      +   '<td>'
      +     '<div class="row">'
      +        '<div class="col-lg-8">'
      +          '<input class="emojyAttr form-control input-sm" data-l1key="codeEmojy">'
      +        '</div>'
      +     '</div>'
      + '<td>'
      + '<i class="fas fa-minus-circle pull-right emojyAction cursor" data-action="remove"></i>'
      +   '</td>'
      + '</tr>';

    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.emojyAttr');
}

$('.eqLogicAction[data-action=saveemojy]').off('click').on('click', function () {
  
  emojyarray = $('#commandtab').find('.emojy').getValues('.emojyAttr');

  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'saveemojy',
      arrayemojy: emojyarray
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error, $('#div_AboAlert'));
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_AboAlert').showAlert({message: 'ERROR', level: 'danger'});
        return;
      }
    }
  });
});

$("#bt_addemojy").off('click').on('click', function(event)
{
  let _cmd = {};
  addEmojyToTable(_cmd);
});

$("#bt_reset").off('click').on('click', function(event)
{
  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'resetemojy'
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error, $('#div_AboAlert'));
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_AboAlert').showAlert({message: 'ERROR', level: 'danger'});
        return;
      }
      location.reload();
    }
  });
});

$('#div_pageContainer').on( 'click', '.emojy .emojyAction[data-action=remove]',function () {
  modifyWithoutSave = true;
  $(this).closest('tr').remove();
});