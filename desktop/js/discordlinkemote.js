
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
initEmoji();
function initEmoji() {
  
  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'getEmoji'
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
        addEmojiToTable(data.result[i]);
      }
    }
  });
}

function addEmojiToTable(_cmd) {

    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    const tr =  ' <tr class="emoji">'
      +   '<td>'
      +     '<div class="row">'
      +       '<div class="col-lg-8">'
      +         '<input class="emojiAttr form-control input-sm" data-l1key="keyEmoji">'
      +       '</div>'
      +   '</td>'
      +   '<td>'
      +     '<div class="row">'
      +        '<div class="col-lg-8">'
      +          '<input class="emojiAttr form-control input-sm" data-l1key="codeEmoji">'
      +        '</div>'
      +     '</div>'
      + '<td>'
      + '<i class="fas fa-minus-circle pull-right emojiAction cursor" data-action="remove"></i>'
      +   '</td>'
      + '</tr>';

    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.emojiAttr');
}

$('.eqLogicAction[data-action=saveEmoji]').off('click').on('click', function () {
  
  emojiArray = $('#commandtab').find('.emoji').getValues('.emojiAttr');

  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'saveEmoji',
      arrayEmoji: emojiArray
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

$("#bt_addEmoji").off('click').on('click', function(event)
{
  let _cmd = {};
  addEmojiToTable(_cmd);
});

$("#bt_reset").off('click').on('click', function(event)
{
  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'resetEmoji'
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

$('#div_pageContainer').on( 'click', '.emoji .emojiAction[data-action=remove]',function () {
  modifyWithoutSave = true;
  $(this).closest('tr').remove();
});