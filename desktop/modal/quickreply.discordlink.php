<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$quickReplyData = discordlink::getQuickReplyFileContent();
?>

<div class="row">
    <div class="col-sm-11">
        <div class="pull-right">
            <label for="daemonRestart" style="margin-left: 5px; margin-bottom: 0;">{{Redémarrer le démon après sauvegarde}}
                <sup><i class="fas fa-question-circle tippied" title="{{Redémarrer le démon après sauvegarde pour prise en compte des modifications}}"></i></sup>
            </label>
            <input type="checkbox" id="daemonRestart" checked style="margin-left: 15px;" />
            <button type="button" class="btn btn-primary" id="saveQuickReply"><i class="fas fa-save"></i> {{Sauvegarder}}</button>
        </div>
    </div>
</div>

<div class="row row-overflow">
    <div class="col-sm-12">
        <form id="quickReplyForm">
            <table class="table table-bordered table-condensed">
                <thead>
                    <tr>
                        <th>{{Clé}}</th>
                        <th>{{Libellé}}</th>
                        <th>{{Émoji}}</th>
                        <th>{{Type}}</th>
                        <th>{{Valeur}}</th>
                        <th>{{Timeout}}</th>
                        <th>{{Actions}}</th>
                    </tr>
                </thead>
                <tbody id="quickReplyContainer">
                </tbody>
            </table>
            <div>
                <button type="button" class="btn btn-success" id="addItem"><i class="fas fa-plus"></i> {{Ajouter une entrée}}</button>
            </div>
        </form>
    </div>
</div>

<?php include_file('desktop', 'quickreply.discordlink', 'js', 'discordlink'); ?>

<script>
    if (typeof initQuickReply === 'function') {
        initQuickReply(<?= json_encode($quickReplyData) ?>);
    }
</script>