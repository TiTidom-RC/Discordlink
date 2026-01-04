// updateinvite(); // Fonctionnalité obsolète - désactivée

$('.bt_getinvite').off('click').on('click', function() {
    var win = window.open(invitebotdiscord, '_blank');
    win.focus();
});

$('.bt_errorinvite').off('click').on('click', function() {

    console.log("https://discord.gg/Ac9Rwfh");
    var win = window.open("https://discord.gg/Ac9Rwfh", '_blank');
    win.focus();
});

function hideall(){
    $('.bt_getinvite').hide();
    $('.bt_errorinvite').hide();
}

function updateinvite() {
    $.ajax({
        type: 'POST',
        url: 'plugins/discordlink/core/ajax/discordlink.ajax.php',
        data: {
          action: 'getinvite'
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
          hideall();
          if (data.result == "null") {
            $('.bt_errorinvite').show();
            // setTimeout(function(){updateinvite();}, 10000); // Boucle désactivée - fonctionnalité obsolète
          } else {
            $('.bt_getinvite').show();
            invitebotdiscord = data.result;
          }
        }
    });
}