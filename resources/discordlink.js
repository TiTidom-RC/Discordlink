/*jshint esversion: 6,node: true,-W041: false */
//Test : node discordlink.js http://192.168.1.200 NjkzNDU5ODg2NTY2Mjc3MTUw.Xn9Y2A.ldbfL6uAUwGxF-wdU7YOsNkg6ew 100 http://127.0.0.1:80/plugins/discordlink/core/api/jeeDiscordlink.php?apikey=kZxOHfEX aelfgZZWEJaDFnlkhH2wO2pi kZxOHfEXaelfgZZWEJaDFnlkhH2wO2pi Me%20pr%C3%A9pare%20%C3%A0%20faire%20r%C3%A9gner%20la%20terreur

const express = require('express');
const fs = require('fs');
const Discord = require("discord.js");

const client = new Discord.Client();
const fetch = require('node-fetch');
//const request = require('request');

const token = process.argv[3];
const IPJeedom = process.argv[2];
const ClePlugin = process.argv[6];
const joueA = decodeURI(process.argv[7]);


/* Configuration */
const config = {
    logger: console2,
    token: token,
    listeningPort: 3466
};

let quickreplyConf = {};
try {
    quickreplyConf = JSON.parse(fs.readFileSync(__dirname + '/quickreply.json', 'utf8'));
    //console.log('quickreply loaded:', quickreplyConf);
} catch (e) {
    console.log("Erreur chargement quickreply.json", e);
}

let dernierStartServeur = 0;

if (!token) config.logger('DiscordLink-Config: *********************TOKEN NON DEFINI*********************');

function console2(text, level = '') {
    try {
        let niveauLevel;
        switch (level) {
            case "ERROR":
                niveauLevel = 400;
                break;
            case "WARNING":
                niveauLevel = 300;
                break;
            case "INFO":
                niveauLevel = 200;
                break;
            case "DEBUG":
                niveauLevel = 100;
                break;
            default:
                niveauLevel = 400; //pour trouver ce qui n'a pas été affecté à un niveau
                break;
        }
    } catch (e) {
        console.log(arguments[0]);
    }
}

/* Routing */
const app = express();
let server = null;

/***** Stop the server *****/
app.get('/stop', (req, res) => {
    config.logger('DiscordLink: Shutting down');
    res.status(200).json({});
    server.close(() => {
        process.exit(0);
    });
});

/***** Restart server *****/
app.get('/restart', (req, res) => {
    config.logger('DiscordLink: Restart');
    res.status(200).json({});
    config.logger('DiscordLink: ******************************************************************');
    config.logger('DiscordLink: *****************************Relance forcée du Serveur*************');
    config.logger('DiscordLink: ******************************************************************');
    startServer();
});

app.get('/getinvite', (req, res) => {

    res.type('json');
    let toReturn = [];

    config.logger('DiscordLink: GetInvite');
    /*client.generateInvite(["ADMINISTRATOR"]).then(link => {
        toReturn.push({
            'invite': link
        });
        res.status(200).json(toReturn);
    }).catch(console.error);*/

    res.status(200).json(toReturn);
});

app.get('/getchannel', (req, res) => {
    res.type('json');
    let toReturn = [];

    config.logger('DiscordLink: GetChannel');
    let channelsall = client.channels.cache.array();
    for (let b in channelsall) {
        let channel = channelsall[b];
        if (channel.type === "text") {
            toReturn.push({
                'id': channel.id,
                'name': channel.name,
                'guildID': channel.guild.id,
                'guildName': channel.guild.name
            });
        }
    }
    res.status(200).json(toReturn);
});

app.get('/sendMsg', (req, res) => {
    res.type('json');
    let toReturn = [];

    config.logger('DiscordLink: sendMsg');

    toReturn.push({
        'id': req.query
    });
    res.status(200).json(toReturn);

    let channel = client.channels.cache.get(req.query.channelID);
    if (channel != null) channel.send(req.query.message);
});

app.get('/sendFile', (req, res) => {
    res.type('json');
    let toReturn = [];

    config.logger('DiscordLink: sendMsg');

    client.channels.cache.get(req.query.channelID).send(req.query.message, {
        files: [{
            attachment: req.query.patch,
            name: req.query.name
        }]
    });

    toReturn.push({
        'id': req.query
    });
    res.status(200).json(toReturn);
});

app.get('/sendMsgTTS', (req, res) => {
    res.type('json');
    let toReturn = [];

    config.logger('DiscordLink: sendMsgTTS');

    client.channels.cache.get(req.query.channelID).send(req.query.message, {
        tts: true
    });

    toReturn.push({
        'id': req.query
    });
    res.status(200).json(toReturn);
});

app.get('/sendEmbed', (req, res) => {
    res.type('json');
    let toReturn = [];

    config.logger('DiscordLink: sendEmbed');

    let color = req.query.color;
    let title = req.query.title;
    let url = req.query.url;
    let description = req.query.description;
    let countanswer = req.query.countanswer;
    let fields = req.query.field;
    let footer = req.query.footer;
    let defaultColor = req.query.defaultColor;
    let reponse = "null";

    // Ajout QuickReply
    let quickreply = req.query.quickreply;
    let quickEmoji = null;
    let quickText = null;
    if (quickreply && quickreplyConf[quickreply]) {
        quickEmoji = quickreplyConf[quickreply].emoji;
        quickText = quickreplyConf[quickreply].text;
        quickTimeout = quickreplyConf[quickreply].timeout || 120; // valeur par défaut 120 secondes
    }

    if (color == '' || color === "null") color = defaultColor;

    const Embed = new Discord.MessageEmbed()
        .setColor(color)
        .setTimestamp();
    //Embed.setThumbnail("https://st.depositphotos.com/1428083/2946/i/600/depositphotos_29460297-stock-photo-bird-cage.jpg");
    if (title !== "null") Embed.setTitle(title);
    if (url !== "null" && countanswer === "null") Embed.setURL(url);
    if (description !== "null") Embed.setDescription(description);
    if (footer !== "null") Embed.setFooter(footer);
    if (fields !== "null") {
        fields = JSON.parse(fields);
        for (let field in fields) {
            let name = fields[field]['name'];
            let value = fields[field]['value'];
            let inline = fields[field]['inline'];

            inline = inline === 1;

            console.log(fields[field])
            console.log("Name : " + name + " | Value : " + value)

            Embed.addField(name, value, inline)
        }
    }

    client.channels.cache.get(req.query.channelID).send(Embed).then(async m => {

        // Ajout de l'emoji quickreply si demandé
        if (quickEmoji) {
            await m.react(quickEmoji);

            // Création du collector pour l'emoji quickreply
            const filter = (reaction, user) => reaction.emoji.name === quickEmoji && !user.bot;
            if (!quickTimeout || isNaN(quickTimeout) || quickTimeout <= 0) {
                quickTimeout = 120; // valeur par défaut 120 secondes
            }
            const collector = m.createReactionCollector(filter, { max: 1, time: quickTimeout *1000 }); 

            collector.on('collect', (reaction, user) => {
                m.channel.send(quickText);
            });

            collector.on('end', (collected, reason) => {
                if (reason === 'time') {
                    // Supprimer uniquement la réaction quickreply
                    const reaction = m.reactions.cache.get(quickEmoji);
                    if (reaction) {
                        reaction.remove().catch(() => {});
                    }
                    // display a message in the channel to indicate time is up (optional)
                    // m.channel.send("⏰ Temps écoulé pour répondre !").then(msg => {
                    //     setTimeout(() => msg.delete().catch(() => {}), 5000); 
                    // });
                }
            });
        }

        if (countanswer !== "null") {
            let timecalcul = (req.query.timeout * 1000);
            toReturn.push({
                'querry': req.query,
                'timeout': req.query.timeout,
                'timecalcul': timecalcul
            });
            res.status(200).json(toReturn);

            if (countanswer !== "0") {
                let emojy = ["🇦", "🇧", "🇨", "🇩", "🇪", "🇫", "🇬", "🇭", "🇮", "🇯", "🇰", "🇱", "🇲", "🇳", "🇴", "🇵", "🇶", "🇷", "🇸", "🇹", "🇺", "🇻", "🇼", "🇽", "🇾", "🇿"];
                let a = 0;
                while (a < countanswer) {
                    await m.react(emojy[a]);
                    a++;
                }
                const filter = (reaction, user) => {
                    return ["🇦", "🇧", "🇨", "🇩", "🇪", "🇫", "🇬", "🇭", "🇮", "🇯", "🇰", "🇱", "🇲", "🇳", "🇴", "🇵", "🇶", "🇷", "🇸", "🇹", "🇺", "🇻", "🇼", "🇽", "🇾", "🇿"].includes(reaction.emoji.name) && user.id !== m.author.id;
                };
                m.awaitReactions(filter, {max: 1, time: timecalcul, errors: ['time']})
                    .then(collected => {
                        const reaction = collected.first();
                        if (reaction.emoji.name === '🇦') reponse = 0;
                        else if (reaction.emoji.name === '🇧') reponse = 1;
                        else if (reaction.emoji.name === '🇨') reponse = 2;
                        else if (reaction.emoji.name === '🇩') reponse = 3;
                        else if (reaction.emoji.name === '🇪') reponse = 4;
                        else if (reaction.emoji.name === '🇫') reponse = 5;
                        else if (reaction.emoji.name === '🇬') reponse = 6;
                        else if (reaction.emoji.name === '🇭') reponse = 7;
                        else if (reaction.emoji.name === '🇮') reponse = 8;
                        else if (reaction.emoji.name === '🇯') reponse = 9;
                        else if (reaction.emoji.name === '🇰') reponse = 10;
                        else if (reaction.emoji.name === '🇱') reponse = 11;
                        else if (reaction.emoji.name === '🇲') reponse = 12;
                        else if (reaction.emoji.name === '🇳') reponse = 13;
                        else if (reaction.emoji.name === '🇴') reponse = 14;
                        else if (reaction.emoji.name === '🇵') reponse = 15;
                        else if (reaction.emoji.name === '🇶') reponse = 16;
                        else if (reaction.emoji.name === '🇷') reponse = 17;
                        else if (reaction.emoji.name === '🇸') reponse = 18;
                        else if (reaction.emoji.name === '🇹') reponse = 19;
                        else if (reaction.emoji.name === '🇺') reponse = 20;
                        else if (reaction.emoji.name === '🇻') reponse = 21;
                        else if (reaction.emoji.name === '🇼') reponse = 22;
                        else if (reaction.emoji.name === '🇽') reponse = 23;
                        else if (reaction.emoji.name === '🇾') reponse = 24;
                        else if (reaction.emoji.name === '🇿') reponse = 25;

                        url = JSON.parse(url);

                        httpPost("ASK", {
                            idchannel: m.channel.id,
                            reponse: reponse,
                            demande: url
                        });
                    })
                    .catch(() => {
                        m.delete();
                    });
            } else {
                let filter = m => m.author.bot === false
                m.channel.awaitMessages(filter, {
                    max: 1,
                    time: timecalcul,
                    errors: ['time']
                })
                .then(message => {
                    let msg = message.first();
                    reponse = msg.content;
                    msg.react("✅");

                    httpPost("ASK", {
                        idchannel: m.channel.id,
                        reponse: reponse,
                        demande: url
                    });
                })
                .catch(collected => {
                    m.delete();
                });
            }
        }
    }).catch(console.error);
    if (countanswer === "null") {
        toReturn.push({
            'querry': req.query
        });
        res.status(200).json(toReturn);
    }
});

app.get('/clearChannel', async (req, res) => {
    const channelID = req.query.channelID;
    if (!channelID) {
        return res.status(400).json({ error: "channelID manquant" });
    }
    const channel = client.channels.cache.get(channelID);
    if (!channel) {
        return res.status(404).json({ error: "Channel non trouvé" });
    }
    // Répondre immédiatement pour éviter les timeouts côté Jeedom
    res.status(200).json({ status: "ok", channelID, message: "Nettoyage en cours..." });
    
    // Effectuer le nettoyage en arrière-plan
    const fakeMessage = { channel: channel };
    try {
        await deletemessagechannel(fakeMessage);
        console.log('[INFO] Nettoyage du channel ' + channelID + ' terminé avec succès');
    } catch (error) {
        console.log('[ERROR] Erreur lors du nettoyage du channel ' + channelID + ': ' + error.message);
    }
});

async function deletemessagechannel(message) {
    try {
        let date = new Date();
        let timestamp = date.getTime();
        let mindaytimestamp = timestamp - 86400000;      // -1 jour (24h)
        let maxbulkdeletetimestamp = timestamp - 1209600000; // -14 jours (limite API Discord pour bulkDelete)
        let allDelete = true;
        let totalDeleted = 0;
        let totalBulkDeleted = 0;
        let totalIndividualDeleted = 0;
        
        console.log('[INFO] Début du nettoyage du channel ' + message.channel.id);
        
        while (allDelete) {
            const fetched = await message.channel.messages.fetch({force: true, limit: 100});
            const bulkDeleteMessages = [];
            const oldMessages = [];
            
            for (const msg of fetched) {
                // Messages de plus de 1 jour
                if (msg[1].createdTimestamp <= mindaytimestamp) {
                    if (msg[1].deletable) {
                        // Messages de 1 à 14 jours : suppression en masse
                        if (msg[1].createdTimestamp > maxbulkdeletetimestamp) {
                            bulkDeleteMessages.push(msg[1]);
                        } else {
                            // Messages de plus de 14 jours : suppression individuelle
                            oldMessages.push(msg[1]);
                        }
                    }
                }
            }
            
            // Suppression en masse (messages < 14 jours)
            if (bulkDeleteMessages.length > 0) {
                await message.channel.bulkDelete(bulkDeleteMessages);
                totalBulkDeleted += bulkDeleteMessages.length;
                totalDeleted += bulkDeleteMessages.length;
                console.log('[DEBUG] ' + bulkDeleteMessages.length + ' messages supprimés en masse');
            }
            
            // Suppression individuelle (messages > 14 jours)
            if (oldMessages.length > 0) {
                let deletedInThisBatch = 0;
                for (const oldMsg of oldMessages) {
                    try {
                        await oldMsg.delete();
                        deletedInThisBatch++;
                        totalIndividualDeleted++;
                        totalDeleted++;
                    } catch (e) {
                        console.log('[WARNING] Impossible de supprimer le message ' + oldMsg.id + ': ' + e.message);
                    }
                }
                console.log('[DEBUG] ' + deletedInThisBatch + ' vieux messages (>14j) supprimés individuellement');
            }
            
            if (bulkDeleteMessages.length === 0 && oldMessages.length === 0) {
                allDelete = false;
            }
        }
        
        console.log('[INFO] ========================================');
        console.log('[INFO] Nettoyage terminé - Récapitulatif :');
        console.log('[INFO] - Messages supprimés en masse (<14j) : ' + totalBulkDeleted);
        console.log('[INFO] - Messages supprimés individuellement (>14j) : ' + totalIndividualDeleted);
        console.log('[INFO] - TOTAL supprimé : ' + totalDeleted);
        console.log('[INFO] ========================================');
    } catch (error) {
        console.log('[ERROR] Erreur lors de la suppression des messages: ' + error.message);
        throw error;
    }
}

/* Main */

startServer();

function startServer() {
    dernierStartServeur = Date.now();

    config.logger('DiscordLink:    ******************** Lancement BOT ***********************', 'INFO');

    client.login(config.token);

    server = app.listen(config.listeningPort, () => {
        config.logger('DiscordLink:    **************************************************************', 'INFO');
        config.logger('DiscordLink:    ************** Server OK listening on port ' + server.address().port + ' **************', 'INFO');
        config.logger('DiscordLink:    **************************************************************', 'INFO');

    });
}

function httpPost(nom, jsonaenvoyer) {

    let url = IPJeedom + "/plugins/discordlink/core/php/jeediscordlink.php?apikey=" + ClePlugin + "&nom=" + nom;

    config.logger && config.logger('URL envoyée: ' + url, "DEBUG");

    console.log("jsonaenvoyer : "+ jsonaenvoyer)
    config.logger && config.logger('DATA envoyé:' + jsonaenvoyer, 'DEBUG');

    fetch(url, {method: 'post', body: JSON.stringify(jsonaenvoyer)})
        .then(res => {
            if (!res.ok) {
                console.log("Erreur lors du contact de votre JeeDom")
            }
        })
}

client.on("ready", async () => {
    await client.user.setActivity(joueA);
});

client.on('message', (receivedMessage) => {


    if (receivedMessage.author === client.user) return;
    if (receivedMessage.author.bot) return;

    httpPost("messagerecu", {
        idchannel: receivedMessage.channel.id,
        message: receivedMessage.content,
        iduser: receivedMessage.author.id
    });

});