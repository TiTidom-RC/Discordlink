<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
?>

<div id="div_EmojiAlert" style="display: none;"></div>

<div class="row">
    <div class="col-lg-12">
        <div class="pull-right">
            <a class="btn btn-success btn-sm cmdAction" id="bt_addEmoji">
                <i class="fas fa-plus-circle"></i> {{Ajouter Emoji}}
            </a>
            <a class="btn btn-success btn-sm eqLogicAction" data-action="saveEmoji">
                <i class="fas fa-check-circle"></i> {{Sauvegarder}}
            </a>
            <a class="btn btn-danger btn-sm cmdAction" id="bt_reset">
                <i class="fas fa-trash"></i> {{Reset Emoji}}
            </a>
        </div>
    </div>
</div>
<br/>

<div class="table-responsive">
    <table id="table_emoji" class="table table-bordered table-condensed ui-sortable">
        <thead>
            <tr class="emoji-head">
                <th>{{Cle Emoji}}</th>
                <th>{{Code Emoji}}</th>
                <th style="width: 100px;">{{Actions}}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>

<?php include_file('desktop', 'emoji.discordlink', 'js', 'discordlink'); ?>

<script>
    if (typeof initEmoji === 'function') {
        initEmoji();
    }
</script>
