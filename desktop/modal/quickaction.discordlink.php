<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$quickActionData = discordlink::getQuickActionFileContent();
?>

<div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 10px;">
    <button type="button" class="btn btn-primary" id="saveQuickAction"><i class="fas fa-save"></i> {{Sauvegarder}}</button>
</div>

<form id="quickActionForm">
    <table class="table table-bordered table-condensed" style="width: 100%; table-layout: fixed;">
        <thead>
            <tr>
                <th style="width: 13%;">{{Clé}}</th>
                <th style="width: 18%;">{{Libellé}}</th>
                <th style="width: 7%;">{{Émoji}}</th>
                <th style="width: 14%;">{{Type}}</th>
                <th style="width: 28%;">{{Valeur}}</th>
                <th style="width: 13%;">{{Timeout}}</th>
                <th style="width: 4%; text-align: right;"></th>
            </tr>
        </thead>
        <tbody id="quickActionContainer">
        </tbody>
    </table>
    <div style="margin-top: 8px;">
        <button type="button" class="btn btn-success" id="addItem"><i class="fas fa-plus"></i> {{Ajouter une entrée}}</button>
    </div>
</form>

<?php include_file('desktop', 'quickaction.discordlink', 'js', 'discordlink'); ?>

<script>
    if (typeof initQuickAction === 'function') {
        initQuickAction(<?= json_encode($quickActionData) ?>);
    }
</script>