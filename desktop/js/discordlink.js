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

setupcase();
$('.bt_test').off('click').on('click', function () {

  console.log(getchannel);

});

$("#table_cmd").sortable({
  axis: "y",
  cursor: "move",
  items: ".cmd",
  placeholder: "ui-state-highlight",
  tolerance: "intersect",
  forcePlaceholderSize: true
});
/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {
      configuration: {}
    };
  }

  var DefinitionDivPourCommandesPredefinies = 'style="display: none;"';
  if (init(_cmd.logicalId) == "") DefinitionDivPourCommandesPredefinies = "";

  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }


  if (init(_cmd.type) == 'info') {
    var tr =
      '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">' +
      '<td>' +
      '<span class="cmdAttr" data-l1key="id"></span>' +
      '</td>' +
      '<td>' +
      '<div class="row">' +
      '<div class="col-lg-1">' +
      '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>' +
      '</div>' +
      '<div class="col-lg-8">' +
      '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom du capteur}}"></td>' +
      '<td>' +
      '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom : 5px;" />' +
      '</td>' +
      '<td>' +
      '</td>' +
      '<td>' +
      '</td>' +
      '<td>' +
      '</td>' +
      '<td>' +
      '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ' +
      '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ' +
      '</td>' +
      '<td>';

    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fas fa-cogs"></i></a> ' +
        '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }

    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>' +
      '</td>' +
      '</tr>';

    $('#table_cmd tbody').append(tr);
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
  }

  if (init(_cmd.type) == 'action') {
    var tr =
      '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">' +
      '<td>' +
      '<span class="cmdAttr" data-l1key="id"></span>' +
      '</td>' +
      '<td>' +
      '<div class="row">' +
      '<div class="col-lg-1">' +
      '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>' +
      '</div>' +
      '<div class="col-lg-8">' +
      '<input class="cmdAttr form-control input-sm" data-l1key="name">' +
      '</div>' +
      '</div>';

    tr += '</td>';


    tr += '<td>';
    tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled />';
    tr += '<div ' + DefinitionDivPourCommandesPredefinies + '>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</div></td>';
    tr += '<td>' +
      '<input class="cmdAttr form-control input-sm"';
    if (init(_cmd.logicalId) != "")
      tr += 'readonly';

    if (init(_cmd.logicalId) == "refresh")
      tr += ' style="display:none;" ';

    tr += ' data-l1key="configuration" data-l2key="request">';

    tr += '</td>';
    tr += '<td>';

    if ((init(_cmd.logicalId) == "") || (init(_cmd.logicalId) == "volume")) {
      tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite"  style="width : 100px;" placeholder="{{Unité}}" title="{{Unité}}" >';
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}"  title="{{Min}} style="margin-top : 3px;"> ';
      tr += '</td>';
      tr += '<td>';
      tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}} style="margin-top : 3px;">';
    } else {
      tr += '</td>';
      tr += '<td>';
    }

    tr += '</td>' +
      '<td>' +
      '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ' +
      '</td>' +
      '<td>';

    if (is_numeric(_cmd.id)) {
      tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fas fa-cogs"></i></a> ';
      if (!((init(_cmd.name) == "Routine") || (init(_cmd.name) == "xxxxxxxx"))) //Masquer le bouton Tester
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>' +
      '  </td>' +
      '</tr>';

    $('#table_cmd tbody').append(tr);
    var tr = $('#table_cmd tbody tr:last');
    jeedom.eqLogic.builSelectCmd({
      id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
      filter: {
        type: 'i'
      },
      error: function (error) {
        $('#div_alert').showAlert({
          message: error.message,
          level: 'danger'
        });
      },
      success: function (result) {
        tr.find('.cmdAttr[data-l1key=value]').append(result);
        tr.setValues(_cmd, '.cmdAttr');
        jeedom.cmd.changeType(tr, init(_cmd.subType));
      }
    });
  }
}

$('#bt_cronGeneratordeamon').on('click', function () {
  jeedom.getCronSelectModal({}, function (result) {
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=autorefreshDeamon]').value(result.value);
  });
});

$('#bt_cronGeneratorDependance').on('click', function () {
  jeedom.getCronSelectModal({}, function (result) {
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=autorefreshDependances]').value(result.value);
  });
});

$('#bt_cronGeneratorzwave').on('click', function () {
  jeedom.getCronSelectModal({}, function (result) {
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=autorefreshZWave]').value(result.value);
  });
});

function setupcase() {
  HideAll();
  if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=deamoncheck]').value() == 1) {
    var divsToHide = document.getElementsByClassName('deamon'); //divsToHide is an array
    for (var i = 0; i < divsToHide.length; i++) {
      divsToHide[i].style.visibility = "visible"; // or
      divsToHide[i].style.display = "initial"; // depending on what you're doing
    }
  }
  if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=depcheck]').value() == 1) {
    var divsToHide2 = document.getElementsByClassName('dependance'); //divsToHide is an array
    for (var a = 0; a < divsToHide2.length; a++) {
      divsToHide2[a].style.visibility = "visible"; // or
      divsToHide2[a].style.display = "initial"; // depending on what you're doing
    }
  }
  if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=zwavecheck]').value() == 1) {
    var divsToHide3 = document.getElementsByClassName('zwave'); //divsToHide is an array
    for (var b = 0; b < divsToHide3.length; b++) {
      divsToHide3[b].style.visibility = "visible"; // or
      divsToHide3[b].style.display = "initial"; // depending on what you're doing
    }
  }
}

function HideAll() {
  var divsToHide = document.getElementsByClassName('deamon'); //divsToHide is an array
  for (var i = 0; i < divsToHide.length; i++) {
    divsToHide[i].style.visibility = "hidden"; // or
    divsToHide[i].style.display = "none"; // depending on what you're doing
  }
  var divsToHide2 = document.getElementsByClassName('dependance'); //divsToHide is an array
  for (var a = 0; a < divsToHide2.length; a++) {
    divsToHide2[a].style.visibility = "hidden"; // or
    divsToHide2[a].style.display = "none"; // depending on what you're doing
  }
  var divsToHide3 = document.getElementsByClassName('zwave'); //divsToHide is an array
  for (var b = 0; b < divsToHide3.length; b++) {
    divsToHide3[b].style.visibility = "hidden"; // or
    divsToHide3[b].style.display = "none"; // depending on what you're doing
  }
}

$('#deamoncheck').click(function () {
  setupcase();
});

$('#depcheck').click(function () {
  setupcase();
});

$('#zwavecheck').click(function () {
  setupcase();
});

$(".eqLogicDisplayCard").on('click', function (event) {
  setTimeout(function () {
    setupcase();
  }, 800);
});