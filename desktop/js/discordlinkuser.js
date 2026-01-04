
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
inituser();
function inituser() {
  
  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'getuser'
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
      console.log(data)
      for (var i in data.result) {
        addUserToTable(data.result[i]);
      }
    }
  });
}

function addUserToTable(_cmd) {

    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    var tr =  ' <tr class="user">'
      +   '<td>'
      +     '<div class="row">'
      +       '<div class="col-lg-8">'
      +         '<input class="userAttr form-control input-sm" data-l1key="nomUser">'
      +       '</div>'

      +   '<td>'
      +     '<div class="row">'
      +        '<div class="col-lg-8">'
      +          '<input class="userAttr form-control input-sm" data-l1key="prenomUser">'
      +        '</div>'
      +     '</div>'
      +   '</td>'
      +   '<td>'
      +     '<div class="row">'
      +        '<div class="col-lg-8">'
      +          '<input class="userAttr form-control input-sm" data-l1key="villeNaissanceUser">'
      +        '</div>'
      +     '</div>'
      +   '</td>'
      +   '<td>'
      +     '<div class="row">'
      +        '<div class="col-lg-8">'
      +          '<input class="userAttr form-control input-sm" data-l1key="dateNaissanceUser">'
      +        '</div>'
      +     '</div>'
      +   '</td>'
      +   '<td>'
      + '<i class="fas fa-minus-circle pull-right userAction cursor" data-action="remove"></i>'
      +   '</td>'
      + '</tr>';

    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.userAttr');
}

$('.eqLogicAction[data-action=saveuser]').off('click').on('click', function () {
  
  userArray = $('#commandtab').find('.user').getValues('.userAttr');

  console.log(userArray)

  $.ajax({
    type: 'POST',
    url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
    data: {
      action: 'saveUser',
      arrayUser: userArray
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

$("#bt_adduser").off('click').on('click', function(event)
{
  var _cmd = {};
  addUserToTable(_cmd);
});


$('#div_pageContainer').on( 'click', '.user .userAction[data-action=remove]',function () {
  modifyWithoutSave = true;
  $(this).closest('tr').remove();
});