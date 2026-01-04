<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('discordlink');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>
<br>
<div id="div_AboAlert" style="display: none;"></div>
<div class="input-group" style="display:inline-flex">
    <span class="input-group-btn">
        <a class="btn btn-sm btn-default" href="index.php?v=d&m=discordlink&p=discordlink"><i class="fas fa-angle-double-left"></i> {{Retour à }} Discord Link
        </a>
    </span>
</div>
<div class="input-group pull-right" style="display:inline-flex">
    <span class="input-group-btn">
        <a class="btn btn-sm btn-default cmdAction roundedLeft" id="bt_adduser"><i class="fas fa-plus-circle"></i>{{Ajouter un utilisateur}}
        </a><a class="btn btn-sm btn-success eqLogicAction roundedRight" data-action="saveuser"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
        </a>
    </span>
    <br>
</div>
<br>
<div role="tabpanel" class="tab-pane active" id="commandtab">
    <table id="table_cmd" class="table table-bordered table-condensed ui-sortable">
        <thead>
            <tr class="">
                <th>{{Nom}}</th>
                <th>{{Prénom}}</th>
                <th>{{Ville de naissance}}</th>
                <th>{{Date de Naissance}}</th>
                <th style="width: 100px;"></th>
            </tr>
        </thead>
        <tbody>

    </table>
</div>

<?php include_file('desktop', 'discordlinkuser', 'js', 'discordlink'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>