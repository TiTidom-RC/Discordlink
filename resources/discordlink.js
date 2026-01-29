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
  ChannelType,
} = require("discord.js");

// Initialisation du client avec les Intents obligatoires
const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
    GatewayIntentBits.MessageContent, // OBLIGATOIRE pour lire les messages
    GatewayIntentBits.GuildMessageReactions,
    GatewayIntentBits.DirectMessages,
    GatewayIntentBits.GuildPresences,
    GatewayIntentBits.GuildMembers,
  ],
  partials: [Partials.Message, Partials.Channel, Partials.Reaction],
});

const token = process.argv[3];
const jeedomURL = process.argv[2];
const logLevelLimit = parseInt(process.argv[4]) || 2000; // Par défaut : Aucun log si non défini
const pluginKey = process.argv[6];
const activityStatus = decodeURI(process.argv[7]);
const listeningPort = process.argv[8] || 3466;

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

let lastServerStart = 0;

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

    const { channelID, message, patch, name } = req.query;
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
          attachment: patch,
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
    } = req.query;

    let userResponse = "null";

    // Ajout QuickReply
    let quickEmoji = null;
    let quickText = null;
    let quickTimeout = 120;

    if (quickreply && quickreplyConf[quickreply]) {
      quickEmoji = quickreplyConf[quickreply].emoji;
      quickText = quickreplyConf[quickreply].text;
      quickTimeout = quickreplyConf[quickreply].timeout || 120;
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

    const m = await channel.send({ embeds: [Embed] });

    // Gestion QuickReply
    if (quickEmoji) {
      await m.react(quickEmoji);

      const filter = (reaction, user) =>
        reaction.emoji.name === quickEmoji && !user.bot;

      if (!quickTimeout || isNaN(quickTimeout) || quickTimeout <= 0) {
        quickTimeout = 120;
      }

      // Discord.js v14: createReactionCollector prend un objet options
      const collector = m.createReactionCollector({
        filter,
        max: 1,
        time: quickTimeout * 1000,
      });

      collector.on("collect", (reaction, user) => {
        m.channel.send(quickText);
      });

      collector.on("end", (collected, reason) => {
        if (reason === "time") {
          const reaction = m.reactions.cache.get(quickEmoji);
          if (reaction) {
            reaction.remove().catch(() => {});
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
              "�": 20,
              "🇻": 21,
              "�": 22,
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
            m.delete().catch(() => {});
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
            msg.react("✅").catch(() => {});

            httpPost("ASK", {
              channelId: m.channel.id,
              response: userResponse,
              request: url,
            });
          })
          .catch(() => {
            m.delete().catch(() => {});
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
      await deleteOldChannelMessages(channel);
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
 * @returns {Promise<void>}
 */
const deleteOldChannelMessages = async (channel) => {
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

    const formattedDate = getTimestamp(new Date(yesterdayTimestamp));

    config.logger("Début du nettoyage du channel " + channel.id, "INFO");
    config.logger("Suppression des messages avant " + formattedDate, "INFO");
    config.logger(
      "Conservation : messages d'aujourd'hui + d'hier (jours calendaires)",
      "INFO",
    );

    while (true) {
      // Récupérer les 100 derniers messages
      const messages = await channel.messages.fetch({
        limit: 100,
        cache: false,
      });

      // Si plus de messages, on arrête
      if (messages.size === 0) {
        break;
      }

      const recentMessages = []; // Avant-hier jusqu'à -14j : suppression en masse
      const ancientMessages = []; // > 14 jours : suppression individuelle

      for (const [msgId, message] of messages) {
        // Supprimer uniquement les messages d'avant-hier et plus anciens
        if (
          message.createdTimestamp < yesterdayTimestamp &&
          message.deletable
        ) {
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
        config.logger(
          recentMessages.length + " messages supprimés en masse",
          "DEBUG",
        );
      }

      // Suppression individuelle (messages > 14 jours)
      if (ancientMessages.length > 0) {
        let deletedInThisBatch = 0;
        for (const message of ancientMessages) {
          try {
            // Discord.js gère automatiquement le Rate Limit (429) en mettant en pause les requêtes
            await message.delete();
            deletedInThisBatch++;
            totalIndividualDeleted++;
            totalDeleted++;
          } catch (e) {
            config.logger(
              "Impossible de supprimer le message " +
                message.id +
                ": " +
                e.message,
              "WARNING",
            );
          }
        }
        config.logger(
          deletedInThisBatch +
            " vieux messages (>14j) supprimés individuellement",
          "DEBUG",
        );
      }
    }

    config.logger("========================================", "INFO");
    config.logger("Nettoyage terminé - Récapitulatif :", "INFO");
    config.logger(
      "- Messages supprimés en masse : " + totalBulkDeleted,
      "INFO",
    );
    config.logger(
      "- Messages supprimés individuellement (>14j) : " +
        totalIndividualDeleted,
      "INFO",
    );
    config.logger("- TOTAL supprimés : " + totalDeleted, "INFO");
    config.logger(
      "- Conservés : aujourd'hui + hier (jours calendaires)",
      "INFO",
    );
    config.logger("========================================", "INFO");
  } catch (error) {
    config.logger(
      "Erreur lors de la suppression des messages: " + error.message,
      "ERROR",
    );
    throw error;
  }
};

/* Gestionnaires d'événements Discord - À définir AVANT client.login() */
client.on("clientReady", async () => {
  config.logger(`Bot connecté :: ${client.user.tag}`, "INFO");

  // Discord.js v14: setActivity prend un objet options
  await client.user.setActivity(activityStatus, { type: 0 }); // 0 = Playing
});

// Discord.js v14: 'message' → 'messageCreate'
client.on("messageCreate", (receivedMessage) => {
  if (receivedMessage.author === client.user) return;
  if (receivedMessage.author.bot) return;

  httpPost("messageReceived", {
    channelId: receivedMessage.channel.id,
    message: receivedMessage.content,
    userId: receivedMessage.author.id,
  });
});

// Gestion des erreurs
client.on("error", (error) => {
  config.logger("Client ERROR :: " + error.message, "ERROR");
  console.error(error);
});

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
  lastServerStart = Date.now();

  config.logger("***** Lancement BOT Discord.js v14 *****", "INFO");

  client.login(config.token).catch((err) => {
    config.logger("FATAL ERROR Login :: " + err.message, "ERROR");
  });

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
    }
  } catch (error) {
    config.logger("Erreur fetch Jeedom: " + error.message, "ERROR");
  }
};

/* Lancement effectif du serveur */
startServer();
