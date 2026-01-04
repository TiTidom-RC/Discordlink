/*jshint esversion: 8,node: true,-W041: false */
// Discord Link Bot pour Jeedom - Version Discord.js v14
// Migration effectuée : Janvier 2026

const express = require('express');
const fs = require('fs');
const { 
    Client, 
    GatewayIntentBits, 
    Partials,
    EmbedBuilder,
    ChannelType
} = require('discord.js');

// Initialisation du client avec les Intents obligatoires
const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,      // OBLIGATOIRE pour lire les messages
        GatewayIntentBits.GuildMessageReactions,
        GatewayIntentBits.DirectMessages,
        GatewayIntentBits.GuildPresences,
        GatewayIntentBits.GuildMembers
    ],
    partials: [
        Partials.Message,
        Partials.Channel,
        Partials.Reaction
    ]
});

// Utiliser fetch natif si Node.js >= 18, sinon fallback
let fetch;
if (typeof globalThis.fetch === 'function') {
    fetch = globalThis.fetch;
} else {
    try {
        fetch = require('node-fetch');
    } catch (e) {
        console.error('[ERROR] Fetch non disponible. Installez node-fetch ou utilisez Node.js 18+');
        process.exit(1);
    }
}

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
} catch (e) {
    console.log("[WARNING] Erreur chargement quickreply.json:", e.message);
}

let dernierStartServeur = 0;

if (!token) {
    config.logger('DiscordLink-Config: *********************TOKEN NON DEFINI*********************', 'ERROR');
}

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
                niveauLevel = 400;
                break;
        }
        console.log(`[${level || 'LOG'}] ${text}`);
    } catch (e) {
        console.log(arguments[0]);
    }
}

/* Routing */
const app = express();
let server = null;

/***** Stop the server *****/
app.get('/stop', (req, res) => {
    config.logger('DiscordLink: Shutting down', 'INFO');
    res.status(200).json({});
    server.close(() => {
        process.exit(0);
    });
});

/***** Restart server *****/
app.get('/restart', (req, res) => {
    config.logger('DiscordLink: Restart', 'INFO');
    res.status(200).json({});
    config.logger('DiscordLink: ******************************************************************', 'INFO');
    config.logger('DiscordLink: *****************************Relance forcée du Serveur*************', 'INFO');
    config.logger('DiscordLink: ******************************************************************', 'INFO');
    startServer();
});

/***** Get channels *****/
app.get('/getchannel', async (req, res) => {
    try {
        res.type('json');
        let toReturn = [];

        config.logger('DiscordLink: GetChannel', 'INFO');
        
        // Discord.js v14: .cache.array() n'existe plus
        const channelsall = Array.from(client.channels.cache.values());
        
        for (let channel of channelsall) {
            // ChannelType.GuildText remplace "text"
            if (channel.type === ChannelType.GuildText) {
                toReturn.push({
                    'id': channel.id,
                    'name': channel.name,
                    'guildID': channel.guild.id,
                    'guildName': channel.guild.name
                });
            }
        }
        
        res.status(200).json(toReturn);
        
    } catch (error) {
        config.logger('DiscordLink ERROR getchannel: ' + error.message, 'ERROR');
        res.status(500).json({ error: error.message });
    }
});

/***** Send simple message *****/
app.get('/sendMsg', async (req, res) => {
    try {
        res.type('json');
        let toReturn = [];

        config.logger('DiscordLink: sendMsg', 'INFO');

        const channel = client.channels.cache.get(req.query.channelID);
        
        if (!channel) {
            return res.status(404).json({ 
                error: 'Channel non trouvé',
                channelID: req.query.channelID 
            });
        }
        
        await channel.send(req.query.message);
        
        toReturn.push({ id: req.query });
        res.status(200).json(toReturn);
        
    } catch (error) {
        config.logger('DiscordLink ERROR sendMsg: ' + error.message, 'ERROR');
        res.status(500).json({ error: error.message });
    }
});

/***** Send file *****/
app.get('/sendFile', async (req, res) => {
    try {
        res.type('json');
        let toReturn = [];

        config.logger('DiscordLink: sendFile', 'INFO');

        const channel = client.channels.cache.get(req.query.channelID);
        
        if (!channel) {
            return res.status(404).json({ 
                error: 'Channel non trouvé',
                channelID: req.query.channelID 
            });
        }
        
        // Discord.js v14: syntaxe identique pour les fichiers
        await channel.send({
            content: req.query.message,
            files: [{
                attachment: req.query.patch,
                name: req.query.name
            }]
        });

        toReturn.push({ id: req.query });
        res.status(200).json(toReturn);
        
    } catch (error) {
        config.logger('DiscordLink ERROR sendFile: ' + error.message, 'ERROR');
        res.status(500).json({ error: error.message });
    }
});

/***** Send TTS message *****/
app.get('/sendMsgTTS', async (req, res) => {
    try {
        res.type('json');
        let toReturn = [];

        config.logger('DiscordLink: sendMsgTTS', 'INFO');

        const channel = client.channels.cache.get(req.query.channelID);
        
        if (!channel) {
            return res.status(404).json({ 
                error: 'Channel non trouvé',
                channelID: req.query.channelID 
            });
        }
        
        await channel.send({
            content: req.query.message,
            tts: true
        });

        toReturn.push({ id: req.query });
        res.status(200).json(toReturn);
        
    } catch (error) {
        config.logger('DiscordLink ERROR sendMsgTTS: ' + error.message, 'ERROR');
        res.status(500).json({ error: error.message });
    }
});

/***** Send embed message *****/
app.get('/sendEmbed', async (req, res) => {
    try {
        res.type('json');
        let toReturn = [];

        config.logger('DiscordLink: sendEmbed', 'INFO');

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
        let quickTimeout = 120;
        
        if (quickreply && quickreplyConf[quickreply]) {
            quickEmoji = quickreplyConf[quickreply].emoji;
            quickText = quickreplyConf[quickreply].text;
            quickTimeout = quickreplyConf[quickreply].timeout || 120;
        }

        if (color === '' || color === "null") color = defaultColor;

        // Discord.js v14: MessageEmbed → EmbedBuilder
        const Embed = new EmbedBuilder()
            .setColor(color)
            .setTimestamp();

        if (title !== "null") Embed.setTitle(title);
        if (url !== "null" && countanswer === "null") Embed.setURL(url);
        if (description !== "null") Embed.setDescription(description);
        
        // Discord.js v14: setFooter prend un objet
        if (footer !== "null") {
            Embed.setFooter({ text: footer });
        }
        
        if (fields !== "null") {
            fields = JSON.parse(fields);
            for (let field in fields) {
                let name = fields[field]['name'];
                let value = fields[field]['value'];
                let inline = fields[field]['inline'];

                inline = inline === 1;

                console.log(fields[field]);
                console.log("Name : " + name + " | Value : " + value);

                // Discord.js v14: addField → addFields
                Embed.addFields({ name: name, value: value, inline: inline });
            }
        }

        const channel = client.channels.cache.get(req.query.channelID);
        
        if (!channel) {
            return res.status(404).json({ 
                error: 'Channel non trouvé',
                channelID: req.query.channelID 
            });
        }

        const m = await channel.send({ embeds: [Embed] });

        // Gestion QuickReply
        if (quickEmoji) {
            await m.react(quickEmoji);

            const filter = (reaction, user) => reaction.emoji.name === quickEmoji && !user.bot;
            
            if (!quickTimeout || isNaN(quickTimeout) || quickTimeout <= 0) {
                quickTimeout = 120;
            }
            
            // Discord.js v14: createReactionCollector prend un objet options
            const collector = m.createReactionCollector({ 
                filter, 
                max: 1, 
                time: quickTimeout * 1000 
            });

            collector.on('collect', (reaction, user) => {
                m.channel.send(quickText);
            });

            collector.on('end', (collected, reason) => {
                if (reason === 'time') {
                    const reaction = m.reactions.cache.get(quickEmoji);
                    if (reaction) {
                        reaction.remove().catch(() => {});
                    }
                }
            });
        }

        // Gestion des réponses ASK
        if (countanswer !== "null") {
            let timecalcul = (req.query.timeout * 1000);
            toReturn.push({
                'querry': req.query,
                'timeout': req.query.timeout,
                'timecalcul': timecalcul
            });
            res.status(200).json(toReturn);

            if (countanswer !== "0") {
                // Réponses avec emojis A-Z
                let emojy = ["🇦", "🇧", "🇨", "🇩", "🇪", "🇫", "🇬", "🇭", "🇮", "🇯", "🇰", "🇱", "🇲", "🇳", "🇴", "🇵", "🇶", "🇷", "🇸", "🇹", "🇺", "🇻", "🇼", "🇽", "🇾", "🇿"];
                let a = 0;
                while (a < countanswer) {
                    await m.react(emojy[a]);
                    a++;
                }
                
                const filter = (reaction, user) => {
                    return emojy.includes(reaction.emoji.name) && user.id !== m.author.id;
                };
                
                // Discord.js v14: awaitReactions prend un objet options
                m.awaitReactions({ 
                    filter, 
                    max: 1, 
                    time: timecalcul, 
                    errors: ['time'] 
                })
                    .then(collected => {
                        const reaction = collected.first();
                        const emojiMap = {
                            '🇦': 0, '🇧': 1, '🇨': 2, '🇩': 3, '🇪': 4, '🇫': 5,
                            '🇬': 6, '🇭': 7, '🇮': 8, '🇯': 9, '🇰': 10, '🇱': 11,
                            '🇲': 12, '🇳': 13, '🇴': 14, '🇵': 15, '🇶': 16, '🇷': 17,
                            '🇸': 18, '🇹': 19, '🇺': 20, '🇻': 21, '🇼': 22, '🇽': 23,
                            '🇾': 24, '🇿': 25
                        };
                        
                        reponse = emojiMap[reaction.emoji.name];
                        url = JSON.parse(url);

                        httpPost("ASK", {
                            idchannel: m.channel.id,
                            reponse: reponse,
                            demande: url
                        });
                    })
                    .catch(() => {
                        m.delete().catch(() => {});
                    });
            } else {
                // Réponse textuelle
                let filter = msg => msg.author.bot === false;
                
                m.channel.awaitMessages({ 
                    filter, 
                    max: 1, 
                    time: timecalcul, 
                    errors: ['time'] 
                })
                    .then(collected => {
                        let msg = collected.first();
                        reponse = msg.content;
                        msg.react("✅").catch(() => {});

                        httpPost("ASK", {
                            idchannel: m.channel.id,
                            reponse: reponse,
                            demande: url
                        });
                    })
                    .catch(() => {
                        m.delete().catch(() => {});
                    });
            }
        } else {
            toReturn.push({ 'querry': req.query });
            res.status(200).json(toReturn);
        }
        
    } catch (error) {
        config.logger('DiscordLink ERROR sendEmbed: ' + error.message, 'ERROR');
        console.error(error);
        res.status(500).json({ error: error.message });
    }
});

/***** Clear channel messages *****/
app.get('/clearChannel', async (req, res) => {
    try {
        const channelID = req.query.channelID;
        
        if (!channelID) {
            return res.status(400).json({ error: "channelID manquant" });
        }
        
        const channel = client.channels.cache.get(channelID);
        
        if (!channel) {
            return res.status(404).json({ error: "Channel non trouvé" });
        }
        
        // Répondre immédiatement pour éviter les timeouts côté Jeedom
        res.status(200).json({ 
            status: "ok", 
            channelID, 
            message: "Nettoyage en cours..." 
        });
        
        // Effectuer le nettoyage en arrière-plan
        const fakeMessage = { channel: channel };
        try {
            await deletemessagechannel(fakeMessage);
            console.log('[INFO] Nettoyage du channel ' + channelID + ' terminé avec succès');
        } catch (error) {
            console.log('[ERROR] Erreur lors du nettoyage du channel ' + channelID + ': ' + error.message);
        }
        
    } catch (error) {
        config.logger('DiscordLink ERROR clearChannel: ' + error.message, 'ERROR');
        res.status(500).json({ error: error.message });
    }
});

/***** Delete messages in channel *****/
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
            // Discord.js v14: force n'existe plus, remplacé par cache
            const fetched = await message.channel.messages.fetch({ 
                limit: 100,
                cache: false 
            });
            
            const bulkDeleteMessages = [];
            const oldMessages = [];
            
            for (const [msgId, msg] of fetched) {
                // Messages de plus de 1 jour
                if (msg.createdTimestamp <= mindaytimestamp) {
                    if (msg.deletable) {
                        // Messages de 1 à 14 jours : suppression en masse
                        if (msg.createdTimestamp > maxbulkdeletetimestamp) {
                            bulkDeleteMessages.push(msg);
                        } else {
                            // Messages de plus de 14 jours : suppression individuelle
                            oldMessages.push(msg);
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

    config.logger('DiscordLink:    ******************** Lancement BOT Discord.js v14 ***********************', 'INFO');

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
    console.log("jsonaenvoyer : " + jsonaenvoyer);
    config.logger && config.logger('DATA envoyé:' + jsonaenvoyer, 'DEBUG');

    fetch(url, {
        method: 'post', 
        body: JSON.stringify(jsonaenvoyer),
        headers: { 'Content-Type': 'application/json' }
    })
        .then(res => {
            if (!res.ok) {
                console.log("[ERROR] Erreur lors du contact de votre Jeedom");
            }
        })
        .catch(error => {
            console.log("[ERROR] Erreur fetch Jeedom:", error.message);
        });
}

/***** Client Events *****/

client.on("ready", async () => {
    config.logger('DiscordLink: Bot connecté en tant que ' + client.user.tag, 'INFO');
    
    // Discord.js v14: setActivity prend un objet options
    await client.user.setActivity(joueA, { type: 0 }); // 0 = Playing
});

// Discord.js v14: 'message' → 'messageCreate'
client.on('messageCreate', (receivedMessage) => {
    if (receivedMessage.author === client.user) return;
    if (receivedMessage.author.bot) return;

    httpPost("messagerecu", {
        idchannel: receivedMessage.channel.id,
        message: receivedMessage.content,
        iduser: receivedMessage.author.id
    });
});

// Gestion des erreurs
client.on('error', error => {
    config.logger('DiscordLink Client ERROR: ' + error.message, 'ERROR');
    console.error(error);
});

process.on('unhandledRejection', error => {
    config.logger('Unhandled promise rejection: ' + error.message, 'ERROR');
    console.error(error);
});
