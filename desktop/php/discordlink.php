<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('discordlink');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<style>
    /* Décalage des options liées (fréquence) vers la droite */
    .daemon_freq .form-group,
    .dependency_freq .form-group {
        margin-left: 10px;
    }
</style>

<div class="row row-overflow">
    <div class="col-sm-12 eqLogicThumbnailDisplay">
        <div class="row">
            <div class="col-sm-10">
                <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
                <div class="eqLogicThumbnailContainer">
                    <div class="cursor eqLogicAction logoPrimary" data-action="add">
                        <i class="fas fa-plus-circle"></i>
                        <br>
                        <span>{{Ajouter}}</span>
                    </div>
                    <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                        <i class="fas fa-wrench"></i>
                        <br>
                        <span>{{Configuration}}</span>
                    </div>
                    <div class="cursor eqLogicAction logoSecondary" data-action="emojiSettings">
                        <i class="fab fa-discord icon_blue"></i>
                        <br>
                        <span>{{Emojis}}</span>
                    </div>
                </div>
            </div>

            <div class="col-sm-2">
                <legend><i class=" fas fa-comments"></i> {{Community}}</legend>
                <div class="eqLogicThumbnailContainer">
                    <div class="cursor eqLogicAction logoSecondary" data-action="createCommunityPost">
                        <i class="fas fa-ambulance icon_blue"></i>
                        <br>
                        <span style="color:var(--txt-color)">{{Créer un post Community}}</span>
                    </div>
                </div>
            </div>

        </div>


        <legend><i class="fas fa-table"></i> {{Mes Channels}}</legend>
        <!-- Champ de recherche -->
        <div class="input-group" style="margin:5px;">
            <input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
            <div class="input-group-btn">
                <a id="bt_resetSearch" class="btn roundedRight" style="width:30px"><i class="fas fa-times"></i></a>
            </div>
        </div>
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <div class="col-xs-12 eqLogic" style="display: none;">
        <div class="input-group pull-right" style="display:inline-flex">
            <span class="input-group-btn">
                <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
                </a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
                </a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
                </a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
                </a>
            </span>
        </div>
        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fab fa-discord"></i> {{DiscordLink}}</a></li>
            <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
        </ul>
        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="eqlogictab">
                <form class="form-horizontal">
                    <fieldset>
                        <div class="col-lg-6">
                            <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
                                <div class="col-sm-6">
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom du channel}}" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Objet parent}}</label>
                                <div class="col-sm-6">
                                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                        <option value="">{{Aucun}}</option>
                                        <?php
                                        $options = '';
                                        foreach ((jeeObject::buildTree(null, false)) as $object) {
                                            $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                                        }
                                        echo $options;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                                <div class="col-sm-6">
                                    <?php
                                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                                        echo '<label class="checkbox-inline">';
                                        echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                                        echo '</label>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Options}}</label>
                                <div class="col-sm-6">
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" title="Activer l'équipement" checked />{{Activer}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" title="Rendre l'équipement visible" checked />{{Visible}}</label>
                                    <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" title="Activer les interactions avec Jeedom" data-l2key="interactionJeedom" />{{Interactions avec Jeedom}}</label>
                                </div>
                            </div>

                            <fieldset>
                                <legend><i class="fab fa-discord"></i> {{Configuration Discord}}</legend>
                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Channel}}</label>
                                    <div class="col-sm-6">
                                        <div class="input-group">
                                            <select class="form-control eqLogicAttr roundedLeft" data-l1key="configuration" data-l2key="channelId">
                                                <?php
                                                $channels = config::byKey('channels', 'discordlink', 'null');
                                                $deamon = discordlink::deamon_info();
                                                $i = 0;
                                                if ($deamon['state'] == 'ok') {
                                                    $channels = discordlink::getChannel();
                                                    foreach ($channels as $channel) {
                                                        echo '<option value="' . $channel['id'] . '">(' . $channel['guildName'] . ') ' . $channel['name'] . '</option>';
                                                        $i++;
                                                    }
                                                }

                                                if ($i == 0) {
                                                    echo '<option value="null">Pas de channel disponible</option>';
                                                }
                                                ?>
                                            </select>
                                            <span class="input-group-btn">
                                                <a class="btn btn-default cursor roundedRight" id="bt_refreshChannels" title="{{Rafraîchir les channels}}">
                                                    <i class="fas fa-sync"></i>
                                                </a>
                                            </span>
                                        </div>
                                    </div>
                            </fieldset>

                            <fieldset>
                                <legend><i class="fas fa-heartbeat"></i> {{Notifications}}</legend>
                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Démons}}
                                        <sup><i class="fas fa-question-circle" title="{{Surveille l'état de tous les démons Jeedom et envoie un état des lieux}}"></i></sup>
                                    </label>
                                    <div class="col-sm-6">
                                        <label class="checkbox-inline"><input id="daemonCheck" type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="daemonCheck" />{{Activer la Vérification}}</label>
                                    </div>
                                </div>
                                <div class="daemon_freq" style="display:none;">
                                    <div class="form-group">
                                        <label class="col-sm-4 control-label">{{Cron}}
                                            <sup><i class="fas fa-question-circle" title="{{Fréquence de vérification des démons (format cron)}}"></i></sup>
                                        </label>
                                        <div class="col-sm-6">
                                            <div class="input-group">
                                                <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="autoRefreshDaemon" placeholder="{{Cron de vérification}}" />
                                                <span class="input-group-btn">
                                                    <a class="btn btn-default cursor jeeHelper roundedRight" id="bt_cronDaemonGenerator" data-helper="cron" title="Assistant cron">
                                                        <i class="fas fa-question-circle"></i>
                                                    </a>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Dépendances}}
                                        <sup><i class="fas fa-question-circle" title="{{Surveille l'installation des dépendances de tous les plugins Jeedom et envoie un état des lieux}}"></i></sup>
                                    </label>
                                    <div class="col-sm-6">
                                        <label class="checkbox-inline"><input id="dependencyCheck" type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="dependencyCheck" />{{Activer la Vérification}}</label>
                                    </div>
                                </div>
                                <div class="dependency_freq" style="display:none;">
                                    <div class="form-group">
                                        <label class="col-sm-4 control-label">{{Cron}}
                                            <sup><i class="fas fa-question-circle" title="{{Fréquence de vérification des dépendances (format cron)}}"></i></sup>
                                        </label>
                                        <div class="col-sm-6">
                                            <div class="input-group">
                                                <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="autoRefreshDependency" placeholder="{{Cron de vérification}}" />
                                                <span class="input-group-btn">
                                                    <a class="btn btn-default cursor jeeHelper roundedRight" id="bt_cronDependencyGenerator" data-helper="cron" title="Assistant cron">
                                                        <i class="fas fa-question-circle"></i>
                                                    </a>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Connexions}}
                                        <sup><i class="fas fa-question-circle" title="{{Annonce sur Discord les connexions des utilisateurs Jeedom}}"></i></sup>
                                    </label>
                                    <div class="col-sm-6">
                                        <label class="checkbox-inline"><input id="connectionCheck" type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="connectionCheck" />{{Annoncer les connexions}}</label>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend><i class="fas fa-broom"></i> {{Nettoyage}}</legend>
                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Nettoyage automatique}}
                                        <sup><i class="fas fa-question-circle" title="{{Efface automatiquement les messages trop anciens}}"></i></sup>
                                    </label>
                                    <div class="col-sm-6">
                                        <label class="checkbox-inline"><input id="clearChannel" type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="clearChannel" />{{Activer}}</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-sm-4 control-label">{{Conserver les messages pendant}}
                                        <sup><i class="fas fa-question-circle" title="{{Lors du nettoyage, conserve les messages des X derniers jours<br/>-1 permet de tout supprimer}}"></i></sup>
                                    </label>
                                    <div class="col-sm-6">
                                        <input id="dayToKeep" type="number" class="eqLogicAttr" min="-1" data-l1key="configuration" data-l2key="dayToKeep" />
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset>
                                <legend><i class="fas fa-palette"></i> {{Personnalisation}}</legend>
                                <div class="form-group">
                                    <label class="col-sm-4 control-label">
                                        {{Couleur par défaut}}
                                        <sup>
                                            <i class="fas fa-question-circle" title="{{Couleur que prendra un message enrichi par défaut}}"></i>
                                        </sup>
                                    </label>
                                    <div class="col-sm-6">
                                        <input type="color" class="eqLogicAttr form-control input-sm cursor" data-l1key="configuration" data-l2key="defaultColor" data-type="background-color" style="width: 80px; display: inline-block;" value="#ff0000">
                                    </div>
                                </div>
                            </fieldset>
                        </div>

                        <!-- Partie droite de l'onglet "Équipement" -->
                        <div class="col-lg-6">
                            <legend><i class="fas fa-info"></i> {{Informations}}</legend>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Description}}</label>
                                <div class="col-sm-6">
                                    <textarea class="form-control eqLogicAttr autogrow" data-l1key="comment"></textarea>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div role="tabpanel" class="tab-pane" id="commandtab">
                <!-- <a class="btn btn-default btn-sm pull-right cmdAction" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a> -->
                <br /><br />
                <div class="table-responsive">
                    <table id="table_cmd" class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th class="hidden-xs" style="min-width:50px;width:70px;">ID</th>
                                <th style="min-width:150px;width:250px;">{{Nom}}</th>
                                <th style="min-width:300px;">{{Commandes}}</th>
                                <th style="min-width:150px;width:200px;">{{Options}}</th>
                                <th style="min-width:80px;width:150px;">{{Etat}}</th>
                                <th style="min-width:130px;width:150px;">{{Actions}}</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_file('desktop', 'discordlink', 'js', 'discordlink'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>