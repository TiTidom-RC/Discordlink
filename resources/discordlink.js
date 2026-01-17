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

const token = process.argv[3];
const jeedomIP = process.argv[2];
const pluginKey = process.argv[6];
const activityStatus = decodeURI(process.argv[7]);
const listeningPort = process.argv[8] || 3466;

/* Configuration */
const config = {
    logger: logger,
    token: token,
    listeningPort: listeningPort
};

// Debug: Afficher les arguments reçus (masquer le token pour la sécurité)
console.log('[DEBUG] Arguments reçus:');
console.log('[DEBUG] - argv[2] (jeedomIP):', jeedomIP);
console.log('[DEBUG] - argv[3] (token):', token ? `[PRESENT - ${token.length} caractères]` : '[ABSENT]');
console.log('[DEBUG] - argv[6] (pluginKey):', pluginKey);
console.log('[DEBUG] - argv[7] (activityStatus):', activityStatus);
console.log('[DEBUG] - argv[8] (listeningPort):', listeningPort);

// Charger la configuration quickreply depuis le répertoire data du plugin
const path = require('path');
let quickreplyConf = {};
const quickreplyPath = path.join(__dirname, '..', 'data', 'quickreply.json');

try {
    quickreplyConf = JSON.parse(fs.readFileSync(quickreplyPath, 'utf8'));
} catch (e) {
    console.log("[WARNING] Erreur chargement quickreply.json:", e.message);
}

let lastServerStart = 0;

if (!token) {
    config.logger('DiscordLink-Config: *********************TOKEN NON DEFINI*********************', 'ERROR');
}

function logger(text, logLevel = 'LOG') {
    try {
        let levelLabel;
        
        // Conversion niveau numérique PHP → texte JavaScript
        // PHP: 100=debug | 200=info | 300=warning | 400=error | 1000=none
        if (typeof logLevel === 'number') {
            switch (logLevel) {
                case 100: levelLabel = 'DEBUG'; break;
                case 200: levelLabel = 'INFO'; break;
                case 300: levelLabel = 'WARNING'; break;
                case 400: levelLabel = 'ERROR'; break;
                case 1000: levelLabel = 'NONE'; break;
                default: levelLabel = 'LOG'; break;
            }
        } else {
            levelLabel = logLevel;
        }
        
        // Formater la date/heure au format Jeedom : YYYY-MM-DD HH:MM:SS
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const timestamp = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        
        console.log(`[${timestamp}] [${levelLabel}] ${text}`);
    } catch (e) {
        console.log(arguments[0]);
    }
}

/* Routing */
const app = express();
let server = null;

/***** Stop the server *****/
app.get('/stop', (req, res) => {
    config.logger('DiscordLink: Received stop request via HTTP', 'INFO');
    res.status(200).json({ success: true });
    setTimeout(() => {
        gracefulShutdown('HTTP-API');
    }, 100);
});

const gracefulShutdown = (signal) => {
    config.logger(`Received ${signal}, shutting down...`, 'INFO');
    
    // Cleanly destroy the Discord client
    if (client) {
        try {
            client.destroy();
            config.logger('Discord Client destroyed', 'DEBUG');
        } catch (e) {
            config.logger('Error destroying Discord Client: ' + e, 'ERROR');
        }
    }

    if (server) {
        server.close(() => {
            config.logger('Server closed', 'DEBUG');
            process.exit(0);
        });
        
        // Force exit if server.close() hangs (e.g. keep-alive connections)
        setTimeout(() => {
            config.logger('Forcing shutdown after timeout', 'WARNING');
            process.exit(0);
        }, 2000);
    } else {
        process.exit(0);
    }
};

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));

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

        config.logger('DiscordLink: GetChannel', 'DEBUG');
        
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
        let answerCount = req.query.countanswer;
        let fields = req.query.field;
        let footer = req.query.footer;
        let defaultColor = req.query.defaultColor;
        let userResponse = "null";

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

        // Normaliser les valeurs vides ou "null"
        const isEmpty = (val) => !val || val === "null" || val === "undefined" || val.trim() === "";
        
        // Valider qu'une URL est bien formée et a un domaine valide
        const isValidUrl = (val) => {
            if (isEmpty(val)) return false;
            try {
                const urlObj = new URL(val);
                // Vérifier que le hostname contient au moins un point (domaine.tld) ou est localhost
                return urlObj.hostname.includes('.') || urlObj.hostname === 'localhost';
            } catch {
                return false;
            }
        };
        
        if (isEmpty(color)) color = defaultColor;

        // Discord.js v14: MessageEmbed → EmbedBuilder
        const Embed = new EmbedBuilder()
            .setColor(color)
            .setTimestamp();

        if (!isEmpty(title)) Embed.setTitle(title);
        if (isValidUrl(url) && isEmpty(answerCount)) {
            Embed.setURL(url);
        }
        if (!isEmpty(description)) Embed.setDescription(description);
        
        // Discord.js v14: setFooter prend un objet
        if (!isEmpty(footer)) {
            Embed.setFooter({ text: footer });
        }
        
        if (!isEmpty(fields)) {
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
        if (!isEmpty(answerCount)) {
            let timeoutMs = (req.query.timeout * 1000);
            toReturn.push({
                'query': req.query,
                'timeout': req.query.timeout,
                'timeoutMs': timeoutMs
            });
            res.status(200).json(toReturn);

            if (answerCount !== "0") {
                // Réponses avec emojis A-Z
                let emojiList = ["🇦", "🇧", "🇨", "🇩", "🇪", "🇫", "🇬", "🇭", "🇮", "🇯", "🇰", "🇱", "🇲", "🇳", "🇴", "🇵", "🇶", "🇷", "🇸", "🇹", "🇺", "🇻", "🇼", "🇽", "🇾", "🇿"];
                let a = 0;
                while (a < answerCount) {
                    await m.react(emojiList[a]);
                    a++;
                }
                
                const emojiFilter = (reaction, user) => {
                    return emojiList.includes(reaction.emoji.name) && user.id !== m.author.id;
                };
                
                m.awaitReactions({ 
                    filter: emojiFilter, 
                    max: 1, 
                    time: timeoutMs, 
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
                        
                        userResponse = emojiMap[reaction.emoji.name];
                        url = JSON.parse(url);

                        httpPost("ASK", {
                            channelId: m.channel.id,
                            response: userResponse,
                            request: url
                        });
                    })
                    .catch(() => {
                        m.delete().catch(() => {});
                    });
            } else {
                // Réponse textuelle
                const messageFilter = msg => msg.author.bot === false;
                
                m.channel.awaitMessages({ 
                    filter: messageFilter, 
                    max: 1, 
                    time: timeoutMs, 
                    errors: ['time'] 
                })
                    .then(collected => {
                        let msg = collected.first();
                        userResponse = msg.content;
                        msg.react("✅").catch(() => {});

                        httpPost("ASK", {
                            channelId: m.channel.id,
                            response: userResponse,
                            request: url
                        });
                    })
                    .catch(() => {
                        m.delete().catch(() => {});
                    });
            }
        } else {
            toReturn.push({ 'query': req.query });
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
        try {
            await deleteOldChannelMessages(channel);
            console.log('[INFO] Nettoyage du channel ' + channelID + ' terminé avec succès');
        } catch (error) {
            console.log('[ERROR] Erreur lors du nettoyage du channel ' + channelID + ': ' + error.message);
        }
        
    } catch (error) {
        config.logger('DiscordLink ERROR clearChannel: ' + error.message, 'ERROR');
        res.status(500).json({ error: error.message });
    }
});

/***** Delete old messages in channel *****/
async function deleteOldChannelMessages(channel) {
    try {
        // Constantes de durée
        const ONE_DAY_MS = 86400000;
        const FOURTEEN_DAYS_MS = 14 * ONE_DAY_MS;
        
        // Timestamps de référence (minuit aujourd'hui en heure locale)
        const todayTimestamp = new Date().setHours(0, 0, 0, 0);
        const yesterdayTimestamp = todayTimestamp - ONE_DAY_MS;
        const fourteenDaysAgoTimestamp = todayTimestamp - FOURTEEN_DAYS_MS;
        
        let totalDeleted = 0;
        let totalBulkDeleted = 0;
        let totalIndividualDeleted = 0;
        
        console.log('[INFO] Début du nettoyage du channel ' + channel.id);
        console.log('[INFO] Suppression des messages avant ' + new Date(yesterdayTimestamp).toISOString());
        console.log('[INFO] Conservation : messages d\'aujourd\'hui + d\'hier (jours calendaires)');
        
        while (true) {
            // Récupérer les 100 derniers messages
            const messages = await channel.messages.fetch({ 
                limit: 100,
                cache: false 
            });
            
            // Si plus de messages, on arrête
            if (messages.size === 0) {
                break;
            }
            
            const recentMessages = [];      // Avant-hier jusqu'à -14j : suppression en masse
            const ancientMessages = [];     // > 14 jours : suppression individuelle
            
            for (const [msgId, message] of messages) {
                // Supprimer uniquement les messages d'avant-hier et plus anciens
                if (message.createdTimestamp < yesterdayTimestamp && message.deletable) {
                    if (message.createdTimestamp > fourteenDaysAgoTimestamp) {
                        recentMessages.push(message);
                    } else {
                        ancientMessages.push(message);
                    }
                }
            }
            
            // Aucun message à supprimer dans ce batch
            if (recentMessages.length === 0 && ancientMessages.length === 0) {
                break;
            }
            
            // Suppression en masse (messages avant-hier jusqu'à -14j)
            if (recentMessages.length > 0) {
                await channel.bulkDelete(recentMessages);
                totalBulkDeleted += recentMessages.length;
                totalDeleted += recentMessages.length;
                console.log('[DEBUG] ' + recentMessages.length + ' messages supprimés en masse');
            }
            
            // Suppression individuelle (messages > 14 jours)
            if (ancientMessages.length > 0) {
                let deletedInThisBatch = 0;
                for (const message of ancientMessages) {
                    try {
                        await message.delete();
                        deletedInThisBatch++;
                        totalIndividualDeleted++;
                        totalDeleted++;
                        
                        // Petit délai pour éviter le rate limiting Discord
                        await new Promise(resolve => setTimeout(resolve, 100));
                    } catch (e) {
                        console.log('[WARNING] Impossible de supprimer le message ' + message.id + ': ' + e.message);
                    }
                }
                console.log('[DEBUG] ' + deletedInThisBatch + ' vieux messages (>14j) supprimés individuellement');
            }
        }
        
        console.log('[INFO] ========================================');
        console.log('[INFO] Nettoyage terminé - Récapitulatif :');
        console.log('[INFO] - Messages supprimés en masse : ' + totalBulkDeleted);
        console.log('[INFO] - Messages supprimés individuellement (>14j) : ' + totalIndividualDeleted);
        console.log('[INFO] - TOTAL supprimé : ' + totalDeleted);
        console.log('[INFO] - Conservés : aujourd\'hui + hier (jours calendaires)');
        console.log('[INFO] ========================================');
        
    } catch (error) {
        console.log('[ERROR] Erreur lors de la suppression des messages: ' + error.message);
        throw error;
    }
}

/* Gestionnaires d'événements Discord - À définir AVANT client.login() */
client.on("clientReady", async () => {
    config.logger(`DiscordLink: Bot connecté: ${client.user.tag}`, 'INFO');
    
    // Discord.js v14: setActivity prend un objet options
    await client.user.setActivity(activityStatus, { type: 0 }); // 0 = Playing
});

// Discord.js v14: 'message' → 'messageCreate'
client.on('messageCreate', (receivedMessage) => {
    if (receivedMessage.author === client.user) return;
    if (receivedMessage.author.bot) return;

    httpPost("messageReceived", {
        channelId: receivedMessage.channel.id,
        message: receivedMessage.content,
        userId: receivedMessage.author.id
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

/* Main */
startServer();

function startServer() {
    lastServerStart = Date.now();

    config.logger('DiscordLink:    ******************** Lancement BOT Discord.js v14 ***********************', 'INFO');

    client.login(config.token).catch(err => {
        config.logger('DiscordLink FATAL ERROR Login: ' + err.message, 'ERROR');
    });

    server = app.listen(config.listeningPort, () => {
        config.logger('DiscordLink:    **************************************************************', 'INFO');
        config.logger('DiscordLink:    ************** Server OK listening on port ' + server.address().port + ' **************', 'INFO');
        config.logger('DiscordLink:    **************************************************************', 'INFO');
    });
}

function httpPost(name, jsonData) {
    let url = jeedomIP + "/plugins/discordlink/core/php/jeediscordlink.php?apikey=" + pluginKey + "&name=" + name;

    config.logger && config.logger('URL envoyée: ' + url, "DEBUG");
    console.log("jsonData : " + JSON.stringify(jsonData));
    config.logger && config.logger('DATA envoyé:' + JSON.stringify(jsonData), 'DEBUG');

    fetch(url, {
        method: 'post', 
        body: JSON.stringify(jsonData),
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
