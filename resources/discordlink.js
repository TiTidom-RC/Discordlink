/* jshint esversion: 9, node: true, -W041: false */

/**
 * Discord Link Bot pour Jeedom
 * Version Discord.js v14
 * Migration effectuée : Janvier 2026
 */

const express = require("express");
const fs = require("fs");
const {
  Client,
  GatewayIntentBits,
  Partials,
  EmbedBuilder,
  AttachmentBuilder,
  ChannelType,
  Events,
  REST,
  Routes,
  SlashCommandBuilder,
} = require("discord.js");

const BASE_INTENTS = [
  GatewayIntentBits.Guilds,
  GatewayIntentBits.GuildMessages,
  GatewayIntentBits.GuildMessageReactions,
  GatewayIntentBits.DirectMessages,
];

const PRIVILEGED_INTENTS = [
  GatewayIntentBits.GuildMembers,
  GatewayIntentBits.GuildPresences,
];

const MESSAGE_CONTENT_INTENT = [
  GatewayIntentBits.MessageContent,
];

let client;

const createClient = (intents) =>
  new Client({
    intents,
    partials: [
      Partials.Message,
      Partials.Channel,
      Partials.Reaction,
    ],
  });

/**
 * Register Slash Commands
 * @param {string} clientId 
 * @param {string} token 
 */
const registerCommands = async (clientId, token) => {
  const commands = [
    new SlashCommandBuilder()
      .setName('jeedom')
      .setDescription('Interagir avec Jeedom')
      .addStringOption(option =>
        option.setName('message')
          .setDescription('Votre demande correspondante à une interaction jeedom')
          .setRequired(true))
  ].map(command => command.toJSON());

  const rest = new REST({ version: '10' }).setToken(token);

  try {
    config.logger('Lancement du rafraîchissement des commandes slash.', 'DEBUG');
    await rest.put(
      Routes.applicationCommands(clientId),
      { body: commands },
    );
    config.logger('Commandes slash rechargées avec succès.', 'INFO');
  } catch (error) {
    config.logger('Erreur lors du rechargement des commandes slash: ' + error.message, 'ERROR');
  }
};

const token = process.argv[3];
const jeedomURL = process.argv[2];
const logLevelLimit = parseInt(process.argv[4]) || 2000; // Par défaut : Aucun log si non défini
const pluginKey = process.argv[6];
const activityStatus = decodeURI(process.argv[7]);
const listeningPort = process.argv[8] || 3466;
const jeedomExtURL = process.argv[9];

// Flag pour indiquer si le client Discord est prêt (évite les erreurs getChannel avant ready)
let discordReady = false;

/**
 * Helper to get current timestamp in Jeedom format (YYYY-MM-DD HH:MM:SS)
 * Using 'sv-SE' locale hack to get ISO 8601 like format
 * @returns {string}
 */
const getTimestamp = (date = new Date()) => date.toLocaleString("sv-SE");

/**
 * Log a message with a specific level to stdout
 * @param {string} text - The message to log
 * @param {string|number} [logLevel='LOG'] - The log level (DEBUG, INFO, WARNING, ERROR, NONE or number)
 */
const logger = (text, logLevel = "LOG") => {
  // Mapping des niveaux de log textuels vers numériques pour comparaison
  const levels = {
    DEBUG: 100,
    INFO: 200,
    WARNING: 300,
    ERROR: 400,
    NONE: 1000,
    LOG: 200, // Default to INFO
  };

  try {
    let levelLabel = logLevel;
    let numericLevel = 200;

    // Si le niveau est fourni sous forme numérique
    if (typeof logLevel === "number") {
      numericLevel = logLevel;
      switch (logLevel) {
        case 100:
          levelLabel = "DEBUG";
          break;
        case 200:
          levelLabel = "INFO";
          break;
        case 300:
          levelLabel = "WARNING";
          break;
        case 400:
          levelLabel = "ERROR";
          break;
        case 1000:
          levelLabel = "NONE";
          break;
        default:
          levelLabel = "LOG";
          break;
      }
    }
    // Si le niveau est fourni sous forme de chaîne (ex: 'DEBUG')
    else if (typeof logLevel === "string") {
      const upperLevel = logLevel.toUpperCase();
      if (levels.hasOwnProperty(upperLevel)) {
        numericLevel = levels[upperLevel];
      }
    }

    // FILTRE : Si le niveau du message est inférieur au niveau configuré, on ne l'affiche pas
    if (numericLevel < logLevelLimit) {
      return;
    }

    console.log(`[${getTimestamp()}][${levelLabel}] ${text}`);
  } catch (e) {
    console.log(arguments[0]);
  }
};

/* Configuration */
const config = {
  logger: logger,
  token: token,
  listeningPort: listeningPort,
};

// Debug: Afficher les arguments reçus (masquer le token pour la sécurité)
config.logger("Arguments reçus:", "DEBUG");
config.logger(" - argv[2] (jeedomURL): " + jeedomURL, "DEBUG");
config.logger(
  " - argv[3] (token): " +
  (token ? `[PRESENT - ${token.length} caractères]` : "[ABSENT]"),
  "DEBUG",
);
config.logger(" - argv[4] (logLevel): " + logLevelLimit, "DEBUG");
config.logger(" - argv[6] (pluginKey): " + pluginKey, "DEBUG");
config.logger(" - argv[7] (activityStatus): " + activityStatus, "DEBUG");
config.logger(" - argv[8] (listeningPort): " + listeningPort, "DEBUG");

// Charger la configuration quickreply depuis le répertoire data du plugin
const path = require("path");
let quickreplyConf = {};
const quickreplyPath = path.join(__dirname, "..", "data", "quickreply.json");

try {
  quickreplyConf = JSON.parse(fs.readFileSync(quickreplyPath, "utf8"));
} catch (e) {
  config.logger("Erreur chargement quickreply.json: " + e.message, "WARNING");
}

if (!token) {
  config.logger("Config: ***** TOKEN NON DEFINI *****", "ERROR");
}

/* Routing */
const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

let server = null;

/***** Stop the server *****/
app.get("/stop", (req, res) => {
  config.logger("Received stop request via HTTP", "INFO");
  res.status(200).json({ success: true });
  setTimeout(() => {
    gracefulShutdown("HTTP-API");
  }, 100);
});

/**
 * Gracefully stop the server and destroy the Discord client
 * @param {string} signal - The signal received (SIGTERM, SIGINT, etc.)
 */
const gracefulShutdown = (signal) => {
  config.logger(`Received ${signal}, shutting down...`, "INFO");

  // Cleanly destroy the Discord client
  if (client) {
    try {
      client.destroy();
      config.logger("Discord Client destroyed", "DEBUG");
    } catch (e) {
      config.logger("Error destroying Discord Client: " + e, "ERROR");
    }
  }

  if (server) {
    server.close(() => {
      config.logger("Server closed", "DEBUG");
      process.exit(0);
    });

    // Force exit if server.close() hangs (e.g. keep-alive connections)
    setTimeout(() => {
      config.logger("Forcing shutdown after timeout", "WARNING");
      process.exit(0);
    }, 2000);
  } else {
    process.exit(0);
  }
};

process.on("SIGTERM", () => gracefulShutdown("SIGTERM"));
process.on("SIGINT", () => gracefulShutdown("SIGINT"));

/***** Restart server *****/
app.get("/restart", (req, res) => {
  config.logger("Restart", "INFO");
  res.status(200).json({});
  config.logger("***** Relance forcée du Serveur *****", "INFO");
  startServer();
});

/***** Heartbeat *****/
app.get("/heartbeat", (req, res) => {
  res.status(200).json({ status: "ok", uptime: process.uptime() });
});

/***** Get channels *****/
app.get("/getchannel", async (req, res) => {
  try {
    res.type("json");

    // Vérifier si le client Discord est prêt
    if (!discordReady) {
      config.logger("GetChannel demandé mais Discord pas encore prêt", "WARNING");
      return res.status(503).json({ error: "Discord not ready yet" });
    }

    let toReturn = [];

    config.logger("GetChannel", "DEBUG");

    // Discord.js v14: .cache.array() n'existe plus
    const allChannels = Array.from(client.channels.cache.values());

    for (let channel of allChannels) {
      // ChannelType.GuildText remplace "text"
      if (channel.type === ChannelType.GuildText) {
        toReturn.push({
          id: channel.id,
          name: channel.name,
          guildID: channel.guild.id,
          guildName: channel.guild.name,
        });
      }
    }

    config.logger("GetChannel : " + toReturn.length + " channel(s) trouvé(s)", "DEBUG");
    res.status(200).json(toReturn);
  } catch (error) {
    config.logger("DiscordLink ERROR getchannel: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

/***** Send simple message *****/
app.get("/sendMsg", async (req, res) => {
  try {
    res.type("json");
    let toReturn = [];

    config.logger("DiscordLink: sendMsg", "INFO");

    const { channelID, message } = req.query;
    const channel = client.channels.cache.get(channelID);

    if (!channel) {
      return res.status(404).json({
        error: "Channel non trouvé",
        channelID,
      });
    }

    await channel.send(message);

    toReturn.push({ id: req.query });
    res.status(200).json(toReturn);
  } catch (error) {
    config.logger("ERROR sendMsg :: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

/***** Send file *****/
app.get("/sendFile", async (req, res) => {
  try {
    res.type("json");
    let toReturn = [];

    config.logger("sendFile", "INFO");

    const { channelID, message, path, name } = req.query;
    const channel = client.channels.cache.get(channelID);

    if (!channel) {
      return res.status(404).json({
        error: "Channel non trouvé",
        channelID,
      });
    }

    // Discord.js v14: syntaxe identique pour les fichiers
    await channel.send({
      content: message,
      files: [
        {
          attachment: path,
          name: name,
        },
      ],
    });

    toReturn.push({ id: req.query });
    res.status(200).json(toReturn);
  } catch (error) {
    config.logger("ERROR sendFile :: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

/***** Send TTS message *****/
app.get("/sendMsgTTS", async (req, res) => {
  try {
    res.type("json");
    let toReturn = [];

    config.logger("sendMsgTTS", "INFO");

    const { channelID, message } = req.query;
    const channel = client.channels.cache.get(channelID);

    if (!channel) {
      return res.status(404).json({
        error: "Channel non trouvé",
        channelID,
      });
    }

    await channel.send({
      content: message,
      tts: true,
    });

    toReturn.push({ id: req.query });
    res.status(200).json(toReturn);
  } catch (error) {
    config.logger("ERROR sendMsgTTS :: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

/***** Send embed message *****/
app.get("/sendEmbed", async (req, res) => {
  try {
    res.type("json");
    let toReturn = [];

    config.logger("sendEmbed", "INFO");

    let {
      color,
      title,
      url,
      description,
      countanswer: answerCount,
      field: fields,
      footer,
      defaultColor,
      quickreply,
      files
    } = req.query;

    let userResponse = "null";

    // Ajout QuickReply
    let quickReplies = [];
    if (quickreply && quickreply !== "null") {
      quickReplies = quickreply
        .split(',')
        .map(q => q.trim())
        .filter(q => {
          if (!quickreplyConf[q]) {
            config.logger(`QuickReply "${q}" non trouvé dans quickreply.json`, "WARNING");
            return false;
          }
          return true;
        });
    }

    // Normaliser les valeurs vides ou "null"
    const isEmpty = (val) =>
      !val || val === "null" || val === "undefined" || val.trim() === "";

    // Valider qu'une URL est bien formée et a un domaine valide
    const isValidUrl = (val) => {
      if (isEmpty(val)) return false;
      try {
        const urlObj = new URL(val);
        // Vérifier que le hostname contient au moins un point (domaine.tld) ou est localhost
        return urlObj.hostname.includes(".") || urlObj.hostname === "localhost";
      } catch {
        return false;
      }
    };

    if (isEmpty(color)) color = defaultColor;

    // Discord.js v14: MessageEmbed → EmbedBuilder
    const Embed = new EmbedBuilder().setColor(color).setTimestamp();

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
        let name = fields[field]["name"];
        let value = fields[field]["value"];
        let inline = fields[field]["inline"];

        inline = inline === 1;

        config.logger(JSON.stringify(fields[field]), "DEBUG");
        config.logger("Name : " + name + " | Value : " + value, "DEBUG");

        // Discord.js v14: addField → addFields
        Embed.addFields({ name: name, value: value, inline: inline });
      }
    }

    const channel = client.channels.cache.get(req.query.channelID);

    if (!channel) {
      return res.status(404).json({
        error: "Channel non trouvé",
        channelID: req.query.channelID,
      });
    }

    const sendOptions = { embeds: [Embed] };
    if (!isEmpty(files)) {
      // Split by comma, trim, and filter
      const fileList = files
        .split(',')
        .map(f => f.trim())
        .filter(f => f.length > 0);
      
      // Verify files exist before sending to avoid DiscordAPIError if file not found
      const existingFiles = [];
      
      for (const filePath of fileList) {
        if (fs.existsSync(filePath)) {
          existingFiles.push(filePath);
        } else {
          config.logger(`Fichier introuvable ou inaccessible: ${filePath}`, "WARNING");
        }
      }
      
      if (existingFiles.length > 0) {
        // Use AttachmentBuilder for better control
        const attachments = existingFiles.map(filePath => {
          const filename = path.basename(filePath);
          return new AttachmentBuilder(filePath, { name: filename });
        });
        
        sendOptions.files = attachments;
        
        // Attach the first file as the Embed Image of the main embed
        if (attachments.length > 0) {
           Embed.setImage(`attachment://${attachments[0].name}`);
        }

        // If multiple images, create a gallery by adding additional embeds
        if (attachments.length > 1) {
          // To force grouping in a gallery, all embeds should share the same URL
          // If no URL is defined, we add a dummy one (Jeedom URL) to all embeds if available
          if (!Embed.data.url && jeedomExtURL) {
             Embed.setURL(jeedomExtURL);
          }
          
          for (let i = 1; i < attachments.length; i++) {
             // Create a simple embed for subsequent images
             const galleryEmbed = new EmbedBuilder()
               .setImage(`attachment://${attachments[i].name}`);
             
             // Must match the first embed URL if it exists
             if (Embed.data.url) {
                galleryEmbed.setURL(Embed.data.url);
             }

             // Copy color if present
             if (Embed.data.color) {
                galleryEmbed.setColor(Embed.data.color);
             }

             sendOptions.embeds.push(galleryEmbed);
             
             // Limit to 4 embeds total (1 main + 3 others) for grid view aesthetic
             if (sendOptions.embeds.length >= 4) {
               if (i < attachments.length - 1) {
                 config.logger(`Limite de 4 images atteinte pour la galerie. ${attachments.length - 4} image(s) ignorée(s).`, "WARNING");
               }
               break;
             }
          }
        }
        
        config.logger(`Envoi de ${existingFiles.length} fichier(s) en galerie`, "INFO");
      }
    }

    const m = await channel.send(sendOptions);

    // Gestion QuickReply
    // Ajout de tous les emojis quickreply demandés
    for (const q of quickReplies) {
      const conf = quickreplyConf[q];
      if (!conf) continue;

      const emoji = conf.emoji;
      const quickText = conf.text;
      let timeout = parseInt(conf.timeout, 10);
      if (isNaN(timeout) || timeout <= 0) timeout = 120;

      await m.react(emoji);

      const filter = (reaction, user) =>
        reaction.emoji.name === emoji && !user.bot;

      const collector = m.createReactionCollector({
        filter,
        max: 1,
        time: timeout * 1000,
      });

      collector.on('collect', async (reaction, user) => {
        // Indiquer que le bot réfléchit
        await m.channel.sendTyping();

        // Traiter comme une vraie commande slash
        await handleSlashCommand({
          channelId: m.channel.id,
          userId: user.id,
          request: quickText,
          username: user.username,
          callback: (response) => m.channel.send(response),
        });
      });

      collector.on('end', (collected, reason) => {
        if (reason === 'time') {
          const reaction = m.reactions.cache.find(r =>
            (r.emoji.id && r.emoji.id === emoji) ||
            (r.emoji.name === emoji)
          );

          if (reaction) {
            reaction.users.remove(client.user.id).catch(() => { });
          }
        }
      });
    }

    // Gestion des réponses ASK
    if (!isEmpty(answerCount)) {
      let timeoutMs = req.query.timeout * 1000;
      toReturn.push({
        query: req.query,
        timeout: req.query.timeout,
        timeoutMs: timeoutMs,
      });
      res.status(200).json(toReturn);

      if (answerCount !== "0") {
        // Réponses avec emojis A-Z
        let emojiList = [
          "🇦",
          "🇧",
          "🇨",
          "🇩",
          "🇪",
          "🇫",
          "🇬",
          "🇭",
          "🇮",
          "🇯",
          "🇰",
          "🇱",
          "🇲",
          "🇳",
          "🇴",
          "🇵",
          "🇶",
          "🇷",
          "🇸",
          "🇹",
          "🇺",
          "🇻",
          "🇼",
          "🇽",
          "🇾",
          "🇿",
        ];
        let a = 0;
        while (a < answerCount) {
          await m.react(emojiList[a]);
          a++;
        }

        const emojiFilter = (reaction, user) => {
          return (
            emojiList.includes(reaction.emoji.name) && user.id !== m.author.id
          );
        };

        m.awaitReactions({
          filter: emojiFilter,
          max: 1,
          time: timeoutMs,
          errors: ["time"],
        })
          .then((collected) => {
            const reaction = collected.first();
            const emojiMap = {
              "🇦": 0,
              "🇧": 1,
              "🇨": 2,
              "🇩": 3,
              "🇪": 4,
              "🇫": 5,
              "🇬": 6,
              "🇭": 7,
              "🇮": 8,
              "🇯": 9,
              "🇰": 10,
              "🇱": 11,
              "🇲": 12,
              "🇳": 13,
              "🇴": 14,
              "🇵": 15,
              "🇶": 16,
              "🇷": 17,
              "🇸": 18,
              "🇹": 19,
              "🇺": 20,
              "🇻": 21,
              "🇼": 22,
              "🇽": 23,
              "🇾": 24,
              "🇿": 25,
            };

            userResponse = emojiMap[reaction.emoji.name];
            url = JSON.parse(url);

            httpPost("ASK", {
              channelId: m.channel.id,
              response: userResponse,
              request: url,
            });
          })
          .catch(() => {
            m.delete().catch(() => { });
          });
      } else {
        // Réponse textuelle
        const messageFilter = (msg) => msg.author.bot === false;

        m.channel
          .awaitMessages({
            filter: messageFilter,
            max: 1,
            time: timeoutMs,
            errors: ["time"],
          })
          .then((collected) => {
            let msg = collected.first();
            userResponse = msg.content;
            msg.react("✅").catch(() => { });

            httpPost("ASK", {
              channelId: m.channel.id,
              response: userResponse,
              request: url,
            });
          })
          .catch(() => {
            m.delete().catch(() => { });
          });
      }
    } else {
      toReturn.push({ query: req.query });
      res.status(200).json(toReturn);
    }
  } catch (error) {
    config.logger("DiscordLink ERROR sendEmbed: " + error.message, "ERROR");
    console.error(error);
    res.status(500).json({ error: error.message });
  }
});

/***** Clear channel messages *****/
app.get("/clearChannel", async (req, res) => {
  try {
    const channelID = req.query.channelID;
    const daysToKeep = req.query.daysToKeep;

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
      message: "Nettoyage en cours...",
    });

    // Effectuer le nettoyage en arrière-plan
    try {
      await deleteOldChannelMessages(channel, daysToKeep);
      config.logger(
        "Nettoyage du channel " + channelID + " terminé avec succès",
        "INFO",
      );
    } catch (error) {
      config.logger(
        "Erreur lors du nettoyage du channel " +
        channelID +
        ": " +
        error.message,
        "ERROR",
      );
    }
  } catch (error) {
    config.logger("DiscordLink ERROR clearChannel: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

/**
 * Delete messages older than 24 hours in a channel
 * Keeps messages from today and yesterday
 * @param {Object} channel - The Discord channel object
 * @param {number} daysToKeep - The number of days to keep messages
 * @returns {Promise<void>}
 */
const deleteOldChannelMessages = async (channel, daysToKeep) => {
  try {
    // Sécurisation du type (int) : Base 10, valeur par défaut 2
    daysToKeep = parseInt(daysToKeep, 10);

    // Constantes de durée
    const ONE_DAY_MS = 86400000;
    const FOURTEEN_DAYS_MS = 14 * ONE_DAY_MS;

    // Timestamps de référence (minuit aujourd'hui en heure locale)
    const nowTimestamp = Date.now();
    const todayTimestamp = new Date().setHours(0, 0, 0, 0);

    // Pour bulkDelete, la limite est de 14 jours EXACTS par rapport à maintenant, non pas minuit.
    // On prend une marge de sécurité de 1 minute pour éviter les effets de bord temps réseau.
    const fourteenDaysAgoTimestamp = nowTimestamp - FOURTEEN_DAYS_MS + 60000;

    // Si daysToKeep == -1 (tout effacer) : on prend nowTimestamp comme limite
    // Sinon calcul classique (ex: 1 -> hier minuit)
    const daysToKeepTimestamp = daysToKeep == -1 ? nowTimestamp : todayTimestamp - (daysToKeep * ONE_DAY_MS);

    let totalDeleted = 0;
    let totalBulkDeleted = 0;
    let totalIndividualDeleted = 0;
    let lastMessageId = null; // Curseur pour la pagination

    const formattedDate = getTimestamp(new Date(daysToKeepTimestamp));

    config.logger("Début du nettoyage du channel " + channel.id, "INFO");

    if (daysToKeep == -1) {
      config.logger("Suppression de tous les messages", "INFO");
    } else {
      config.logger("Suppression des messages avant " + formattedDate, "INFO");
      config.logger(
        "Conservation : Aujourd'hui + les " + daysToKeep + " derniers jours",
        "INFO",
      );
    }

    while (true) {
      // Options de récupération
      const fetchOptions = { limit: 100, cache: false };
      // Si on a déjà récupéré un lot, on demande la suite (messages plus vieux que le dernier vu)
      if (lastMessageId) {
        fetchOptions.before = lastMessageId;
      }

      // Récupérer les messages
      const messages = await channel.messages.fetch(fetchOptions);

      // Si Discord ne renvoie plus rien, on a atteint la fin du salon (ou le début de l'histoire)
      if (messages.size === 0) {
        config.logger("Fin de l'historique du salon atteinte.", "DEBUG");
        break;
      }

      config.logger("Traitement de " + messages.size + " messages", "DEBUG");

      // On met à jour le curseur pour le prochain tour (le plus vieux message de ce lot)
      lastMessageId = messages.last().id;

      const recentMessages = []; // Messages récents à supprimer en masse
      const ancientMessages = []; // > 14 jours : suppression individuelle

      for (const [msgId, message] of messages) {
        // Supprimer uniquement les messages plus vieux que le timestamp limite
        if (
          message.createdTimestamp < daysToKeepTimestamp &&
          message.deletable
        ) {
          if (message.createdTimestamp > fourteenDaysAgoTimestamp) {
            recentMessages.push(message);
          } else {
            ancientMessages.push(message);
          }
        }
      }

      // Note : On ne 'break' plus si les tableaux sont vides.
      // On continue la boucle pour aller chercher les messages plus anciens (batch suivant).

      // Suppression en masse (messages récents mais à supprimer)
      if (recentMessages.length > 0) {
        try {
          const deleted = await channel.bulkDelete(recentMessages);
          totalBulkDeleted += deleted.size;
          totalDeleted += deleted.size;
          config.logger(
            deleted.size + " messages supprimés en masse",
            "DEBUG",
          );
        } catch (e) {
          config.logger("Erreur bulkDelete: " + e.message, "WARNING");
        }
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
          } catch (e) {
            config.logger("Echec suppression message " + message.id + ": " + e.message, "WARNING");
          }
        }
        config.logger(deletedInThisBatch + " vieux messages (>14j) supprimés un par un", "DEBUG");
      }
    }

    config.logger("========================================", "INFO");
    config.logger("Nettoyage terminé - Récapitulatif :", "INFO");
    config.logger("- Messages supprimés en masse : " + totalBulkDeleted, "INFO");
    config.logger("- Messages supprimés individuellement (>14j) : " + totalIndividualDeleted, "INFO");
    config.logger("- TOTAL supprimés : " + totalDeleted, "INFO");
    config.logger("========================================", "INFO");
  } catch (error) {
    config.logger("Erreur critique lors du nettoyage : " + error.message, "ERROR");
    throw error;
  }
};

/**
 * Traite une commande slash Jeedom
 * Logique réutilisée pour les vraies interactions slash et les quickreplies
 * @param {Object} params - Les paramètres
 * @param {string} params.channelId - L'ID du channel
 * @param {string} params.userId - L'ID de l'utilisateur
 * @param {string} params.request - La requête/message
 * @param {string} params.username - Le nom d'utilisateur
 * @param {Object} params.callback - Fonction pour envoyer la réponse
 */
const handleSlashCommand = async ({ channelId, userId, request, username, callback }) => {
  try {
    config.logger(`SlashCommand: "${request}" from user ${userId}`, "DEBUG");

    const response = await httpPost("slashCommand", {
      channelId,
      userId,
      request,
      username,
    });

    if (response && response.trim() !== '') {
      await callback(response.substring(0, 2000));
    } else {
      config.logger("Réponse vide ou nulle reçue de Jeedom pour la commande slash", "WARNING");
      await callback("Jeedom a reçu la commande mais n'a rien renvoyé.");
    }
  } catch (e) {
    config.logger("Erreur lors du traitement de la commande slash: " + e.message, "ERROR");
    await callback("Erreur lors du traitement de la commande.");
  }
};

const attachDiscordEvents = () => {
  // Discord.js v14: 'message' → 'messageCreate'
  client.on("messageCreate", (receivedMessage) => {
    // if (receivedMessage.author === client.user) return;
    if (receivedMessage.author?.bot && !receivedMessage.webhookId) {
      // config.logger('⛔ message bot NON autorisé webhookID → ignoré', "DEBUG");
      return;
    }

    httpPost("messageReceived", {
      channelId: receivedMessage.channel.id,
      message: receivedMessage.content,
      userId: receivedMessage.author.id,
    });
  });

  client.on(Events.InteractionCreate, async (interaction) => {
    if (!interaction.isChatInputCommand()) return;

    if (interaction.commandName === "jeedom") {
      try {
        await interaction.deferReply();
      } catch (error) {
        // Ignorer l'erreur si l'interaction est déjà morte ou inconnue (délai dépassé ou race condition)
        if (error.code === 10062) {
          config.logger("Interaction expirée ou inconnue avant traitement (Ignoré)", "DEBUG");
          return;
        }
        config.logger("Erreur lors du deferReply: " + error.message, "ERROR");
        return;
      }

      const request = interaction.options.getString("message");

      await handleSlashCommand({
        channelId: interaction.channelId,
        userId: interaction.user.id,
        request: request,
        username: interaction.user.username,
        callback: (response) => interaction.editReply(response),
      });
    }
  });

  // Gestion des erreurs
  client.on("error", (error) => {
    config.logger("Client ERROR :: " + error.message, "ERROR");
    console.error(error);
  });

};

process.on("unhandledRejection", (error) => {
  config.logger("Unhandled promise rejection: " + error.message, "ERROR");
  console.error(error);
});

process.on("uncaughtException", (error) => {
  config.logger("Uncaught Exception: " + error.message, "ERROR");
  console.error(error);
  process.exit(1);
});

/* Main */

/**
 * Initialize the Discord client and start the Express server
 */
const startServer = () => {
  discordReady = false;

  config.logger("***** Lancement BOT Discord.js v14 *****", "INFO");

  /**
   * Helper interne pour créer + connecter le client
   */
  const loginClient = async (intents, label) => {
    client = createClient(intents);
    attachDiscordEvents();

    // READY = SEUL MOMENT FIABLE
    client.once(Events.ClientReady, async () => {
      discordReady = true;

      // Enregistrement des commandes slash
      await registerCommands(client.user.id, token);

      config.logger(`Bot READY (${label}) :: ${client.user.tag}`, "INFO");

      try {
        await client.user.setActivity(activityStatus, { type: 0 });
      } catch (e) {
        config.logger("Erreur setActivity: " + e.message, "WARNING");
      }

      // Pré-chargement des guilds & channels (important pour getChannel) 
      // ... Avec timeout pour éviter de bloquer le bot indéfiniment en cas de gros serveur ou de problème réseau
      try {
        const PRELOAD_TIMEOUT = 15000; // 15 secondes max
        let preloadState = { phase: 'starting', guildsLoaded: 0, channelsLoaded: 0 };

        const preloadPromise = (async () => {
          preloadState.phase = 'fetching_guilds';
          await client.guilds.fetch();
          preloadState.guildsLoaded = client.guilds.cache.size;
          preloadState.phase = 'fetching_channels';

          config.logger(`${client.guilds.cache.size} guilds récupérées`, "DEBUG");

          // Paralléliser les fetch de channels
          const channelFetchPromises = Array.from(client.guilds.cache.values()).map(
            guild => guild.channels.fetch()
              .then(() => {
                // Compter uniquement les channels texte
                preloadState.channelsLoaded += guild.channels.cache.filter(c => c.type === ChannelType.GuildText).size;
              })
              .catch(err => {
                config.logger(`Erreur fetch channels ${guild.name}: ${err.message}`, "DEBUG");
                return null; // Continue même si un serveur échoue
              })
          );

          await Promise.all(channelFetchPromises);
          preloadState.phase = 'completed';
        })();

        const timeoutPromise = new Promise((_, reject) => {
          setTimeout(() => {
            const err = new Error('Timeout préchargement');
            err.errorType = 'PRELOAD_TIMEOUT';
            err.duration = PRELOAD_TIMEOUT;
            // Capturer l'état au moment exact du timeout
            err.state = { ...preloadState };
            reject(err);
          }, PRELOAD_TIMEOUT);
        });

        await Promise.race([preloadPromise, timeoutPromise]);

        // Compte les channels texte chargés dans le cache
        const totalTextChannels = Array.from(client.guilds.cache.values())
          .reduce((acc, guild) => acc + guild.channels.cache.filter(c => c.type === ChannelType.GuildText).size, 0);

        config.logger(`Guilds & channels préchargés (${totalTextChannels} channels texte)`, "DEBUG");

      } catch (e) {
        // Gestion par errorType pour différencier timeout vs autres erreurs
        if (e.errorType === 'PRELOAD_TIMEOUT') {
          const state = e.state;
          let message = `Timeout préchargement (${e.duration}ms) pendant "${state.phase}"`;

          if (state.guildsLoaded > 0) {
            message += ` - ${state.guildsLoaded} guilds, ${state.channelsLoaded} channels texte chargés`;
          } else {
            message += ` - Chargement initial en cours`;
          }

          config.logger(message, "WARNING");
        } else {
          config.logger(`Erreur preload channels: ${e.message}`, "WARNING");
        }
      }
    });

    await client.login(config.token);
  };

  /**
   * Tentative 1 : avec TOUS les intents (Membres + Présence + Contenu)
   * Idéal pour un fonctionnement optimal
   */
  loginClient([...BASE_INTENTS, ...PRIVILEGED_INTENTS, ...MESSAGE_CONTENT_INTENT], "Full Intents")
    .then(() => {
      config.logger("[Login Discord] Connexion réussie :: Intents Standards & Privilégiés", "INFO");
    })
    .catch((err) => {
      const isIntentError = err.code === 'DisallowedIntents' || (err.message && err.message.toLowerCase().includes('disallowed intents'));

      if (!isIntentError) {
        config.logger("[Login Discord] Echec critique (1) lors de la connexion (Token invalide ou erreur réseau) :: " + err.message, "ERROR");
        process.exit(1);
      }

      config.logger("[Login Discord] Echec de la connexion (Intents privilégiés manquants ?). Tentative en mode dégradé...", "WARNING");
      config.logger("[Login Discord] Détail erreur :: " + err.message, "DEBUG");

      /**
       * Tentative 2 : Standard + MessageContent (Sans Membres/Présence)
       * Mode dégradé acceptable : on perd juste des infos utilisateurs mais le bot parle/écoute
       */
      loginClient([...BASE_INTENTS, ...MESSAGE_CONTENT_INTENT], "Standard + Content")
        .then(() => {
          config.logger("[Login Discord] Connexion (Mode dégradé) réussie :: Mode Standard + Content", "INFO");
          const warningMsg = "ATTENTION : Connexion réussie mais certains intents privilégiés sont manquants. Le plugin fonctionne en mode dégradé. Voir la documentation.";
          config.logger("[Login Discord] " + warningMsg, "WARNING");
          httpPost("createJeedomMessage", { msg: warningMsg });
        })
        .catch((err2) => {
          const isIntentError2 = err2.code === 'DisallowedIntents' || (err2.message && err2.message.toLowerCase().includes('disallowed intents'));

          if (!isIntentError2) {
            config.logger("[Login Discord] Echec critique (2) lors de la connexion (Token invalide ou erreur réseau) :: " + err2.message, "ERROR");
            process.exit(1);
          }

          config.logger("[Login Discord] Echec de la connexion (Intent privilégié 'Message Content' manquant ?). Tentative en mode notifications...", "WARNING");
          config.logger("[Login Discord] Détail erreur :: " + err2.message, "DEBUG");

          /**
           * Tentative 3 : Standard uniquement (Sans rien de privilégié)
           * Mode Survie : Le bot peut envoyer des messages mais est sourd (ne lit pas les retours)
           */
          loginClient(BASE_INTENTS, "Mode Notifications")
            .then(() => {
              const diagMsg = "ATTENTION : Connexion réussie mais tous les intents privilégiés sont manquants. Le plugin fonctionne en mode notifications uniquement. Voir la documentation.";
              config.logger("[Login Discord] " + diagMsg, "WARNING");
              httpPost("createJeedomMessage", { msg: diagMsg });
            })
            .catch((err3) => {
              config.logger("[Login Discord] Echec critique (3) lors de la connexion (Token invalide ou erreur réseau) :: " + err3.message, "ERROR");
              process.exit(1);
            });
        });
    });

  /**
   * Lancement du serveur HTTP (indépendant de Discord)
   */
  server = app.listen(config.listeningPort, () => {
    config.logger(
      "***** Démon :: OK - Listening on port :: " +
      server.address().port +
      " *****",
      "INFO",
    );
  });

  server.on("error", (e) => {
    if (e.code === "EADDRINUSE") {
      config.logger(
        `FATAL ERROR: Port ${config.listeningPort} is already in use`,
        "ERROR",
      );
      process.exit(1);
    } else {
      config.logger("Server error: " + e.message, "ERROR");
    }
  });
};

/**
 * Send data to Jeedom via HTTP POST
 * @param {string} name - The name of the event/action
 * @param {Object} jsonData - The data to send
 */
const httpPost = async (name, jsonData) => {
  let url =
    jeedomURL +
    "/plugins/discordlink/core/php/jeediscordlink.php?apikey=" +
    pluginKey +
    "&name=" +
    name;

  config.logger("URL envoyée :: " + url, "DEBUG");
  config.logger("DATA envoyées :: " + JSON.stringify(jsonData), "DEBUG");

  try {
    const res = await fetch(url, {
      method: "post",
      body: JSON.stringify(jsonData),
      headers: { "Content-Type": "application/json" },
    });

    if (!res.ok) {
      config.logger(
        "Erreur lors du contact de votre Jeedom: " +
        res.status +
        " " +
        res.statusText,
        "ERROR",
      );
      return null;
    }
    return await res.text();
  } catch (error) {
    config.logger("Erreur fetch Jeedom: " + error.message, "ERROR");
    return null;
  }
};

/* Lancement effectif du serveur */
startServer();
