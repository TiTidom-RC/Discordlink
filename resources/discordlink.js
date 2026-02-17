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
          .setRequired(true)),
    new SlashCommandBuilder()
      .setName('deletelastmessages')
      .setDescription('Supprimer les X derniers messages du channel')
      .addStringOption(option =>
        option.setName('nbmessages')
          .setDescription('Nombre de messages à supprimer (à partir du plus récent)')
          .setRequired(true)),
    new SlashCommandBuilder()
      .setName('keeplastdays')
      .setDescription('Conserver les X derniers jours de messages du channel')
      .addStringOption(option =>
        option.setName('nbjours')
          .setDescription('Nombre de jours à conserver. Ex: 1 = aujourd\'hui + hier, etc. -1 pour tout supprimer')
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
// Increase limit for larger payloads (like images or large embeds)
app.use(express.json({ limit: '5mb' }));
app.use(express.urlencoded({ extended: true, limit: '5mb' }));

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

// --- MIGRATION POST ---
/***** Send simple message (POST) *****/
app.post("/sendMsg", async (req, res) => {
  try {
    res.type("json");
    config.logger("DiscordLink: sendMsg (POST)", "INFO");

    const { channelID, message } = req.body;
    const channel = client.channels.cache.get(channelID);

    if (!channel) {
      return res.status(404).json({ error: "Channel non trouvé", channelID });
    }

    await channel.send(message);
    res.status(200).json([{ id: req.body }]);
  } catch (error) {
    config.logger("ERROR sendMsg :: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

// --- MIGRATION POST ---
/***** Send file (POST) *****/
app.post("/sendFile", async (req, res) => {
  try {
    res.type("json");
    config.logger("sendFile (POST)", "INFO");

    const channelID = req.body.channelID;
    const message = req.body.message || "";
    // files defaults to empty array so we can iterate safely
    const files = req.body.files || [];

    const channel = client.channels.cache.get(channelID);
    if (!channel) {
      return res.status(404).json({ error: "Channel non trouvé", channelID });
    }

    const attachments = [];
    if (files && Array.isArray(files) && files.length > 0) {

      // Limit to 4 files
      const filesToSend = files.slice(0, 4);
      if (files.length > 4) {
        config.logger(`WARNING: Only first 4 files will be sent (requested: ${files.length})`, "WARNING");
      }

      for (const filePath of filesToSend) {
        try {
          if (typeof filePath === 'string' && fs.existsSync(filePath)) {
            const attachment = new AttachmentBuilder(filePath);
            attachments.push(attachment);
          } else {
            config.logger(`File not found: ${filePath}`, "WARNING");
          }
        } catch (e) {
          config.logger(`Error processing file ${filePath}: ${e.message}`, "ERROR");
        }
      }
    }

    if (attachments.length === 0 && !message) {
      return res.status(400).json({ error: "No files or message to send" });
    }

    await channel.send({ content: message, files: attachments });
    res.status(200).json({ filesSent: attachments.length, messageSent: !!message });
  } catch (error) {
    config.logger("ERROR sendFile :: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

// --- MIGRATION POST ---
/***** Send TTS message (POST) *****/
app.post("/sendMsgTTS", async (req, res) => {
  try {
    res.type("json");
    config.logger("sendMsgTTS (POST)", "INFO");

    const { channelID, message } = req.body;
    const channel = client.channels.cache.get(channelID);

    if (!channel) {
      config.logger(`Channel not found: ${channelID}`, "ERROR");
      return res.status(404).json({
        error: "Channel non trouvé",
        channelID,
      });
    }

    await channel.send({
      content: message,
      tts: true,
    });

    res.status(200).json({ success: true, message });
  } catch (error) {
    config.logger("ERROR sendMsgTTS :: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

// --- MIGRATION POST ---
/***** Send embed message (POST) *****/
app.post("/sendEmbed", async (req, res) => {
  try {
    res.type("json");
    config.logger("sendEmbed (POST)", "INFO");

    const {
      channelID,
      color,
      title,
      url, // Can be a URL string OR a JSON string/Object for ASK callbacks
      description,
      fields, // Array of objects {name, value, inline}
      footer,
      defaultColor,
      quickreply, // Array of strings
      files, // Array of strings (paths)
      answerCount, // Number or String
      timeout // Number
    } = req.body;

    // Normaliser les valeurs vides ou "null" de manière stricte pour JSON (null/undefined/empty string)
    const isEmpty = (val) =>
      val === undefined || val === null || val === "" || val === "null";

    const channel = client.channels.cache.get(channelID);
    if (!channel) {
      config.logger(`Channel not found: ${channelID}`, "ERROR");
      return res.status(404).json({
        error: "Channel non trouvé",
        channelID,
      });
    }

    // Gestion QuickReply
    let quickReplies = [];
    if (quickreply && Array.isArray(quickreply)) {
      quickReplies = quickreply
        .filter(q => {
          if (!quickreplyConf[q]) {
            config.logger(`QuickReply "${q}" non trouvé dans quickreply.json`, "WARNING");
            return false;
          }
          return true;
        });
    }

    // Valider qu'une URL est bien formée et a un domaine valide
    const isValidUrl = (val) => {
      if (isEmpty(val) || typeof val !== 'string') return false;
      try {
        const urlObj = new URL(val);
        // Vérifier que le hostname contient au moins un point (domaine.tld) ou est localhost
        return urlObj.hostname.includes(".") || urlObj.hostname === "localhost";
      } catch {
        return false;
      }
    };

    let embedColor = color;
    if (isEmpty(embedColor)) embedColor = defaultColor;

    // Discord.js v14: MessageEmbed → EmbedBuilder
    const Embed = new EmbedBuilder().setColor(embedColor).setTimestamp();

    if (!isEmpty(title)) Embed.setTitle(title);

    // Only set URL if it looks like a URL and we are NOT in database/ASK mode (answerCount is empty)
    if (isValidUrl(url) && isEmpty(answerCount)) {
      Embed.setURL(url);
    }

    if (!isEmpty(description)) Embed.setDescription(description);

    // Discord.js v14: setFooter prend un objet
    if (!isEmpty(footer)) {
      Embed.setFooter({ text: footer });
    }

    if (fields && Array.isArray(fields) && fields.length > 0) {
      for (const field of fields) {
        let { name, value, inline } = field;

        // Convert integer 1/0 to boolean if necessary, or string "1"/"0"
        if (inline === 1 || inline === "1" || inline === true) inline = true;
        else inline = false;

        // Discord.js v14: addField → addFields
        Embed.addFields({ name: name, value: value, inline: inline });
      }
    }

    const sendOptions = { embeds: [Embed] };

    // Handle Files
    if (files && Array.isArray(files) && files.length > 0) {
      const existingFiles = [];

      for (const filePath of files) {
        if (typeof filePath === 'string' && fs.existsSync(filePath)) {
          existingFiles.push(filePath);
        } else {
          config.logger(`Fichier introuvable ou inaccessible: ${filePath}`, "WARNING");
        }
      }

      if (existingFiles.length > 0) {
        // Use AttachmentBuilder
        const attachments = existingFiles.map((filePath, index) => {
          let filename = path.basename(filePath);

          // Check for duplicate filenames in the current batch
          const isDuplicate = existingFiles.some((f, i) => i !== index && path.basename(f) === filename);

          if (isDuplicate) {
            filename = `${index}_${filename}`;
          }

          return new AttachmentBuilder(filePath, { name: filename });
        });

        sendOptions.files = attachments;

        // Attach the first file as the Embed Image of the main embed
        if (attachments.length > 0) {
          Embed.setImage(`attachment://${attachments[0].name}`);
        }

        // If multiple images, create a gallery
        // Note: Discord allows up to 10 embeds per message, but only 4 displayed as a grid if they have same URL
        if (attachments.length > 1) {
          const galleryUrl = Embed.data.url || jeedomExtURL || "https://www.jeedom.com";

          // Ensure the main embed has this URL so they group together
          Embed.setURL(galleryUrl);

          for (let i = 1; i < attachments.length; i++) {
            // Create a lightweight embed for the gallery image
            const galleryEmbed = new EmbedBuilder()
              .setURL(galleryUrl) // Must match main embed URL to group
              .setImage(`attachment://${attachments[i].name}`);

            // galleryEmbed.setColor(null); // Try to not set color on secondary embeds to avoid sidebar clutter? 
            // Actually it's better if they don't have color or have same color.
            // But if we want a clean image grid, usually they shouldn't have other properties.

            // Copy color if present on main embed to look consistent
            if (Embed.data.color) galleryEmbed.setColor(Embed.data.color);

            sendOptions.embeds.push(galleryEmbed);

            // Limit checks
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

    // Apply QuickReplies (Reactions)
    for (const q of quickReplies) {
      const conf = quickreplyConf[q];
      if (!conf) continue;

      const emoji = conf.emoji; // e.g. "👍" or custom ID
      const quickText = conf.text;
      let qTimeout = parseInt(conf.timeout, 10);
      if (isNaN(qTimeout) || qTimeout <= 0) qTimeout = 120;

      // Note: Reacting might fail if emoji is invalid or bot lacks permission
      try {
        await m.react(emoji);
      } catch (err) {
        config.logger(`Impossible de réagir avec ${emoji}: ${err.message}`, "error");
        continue;
      }

      const filter = (reaction, user) =>
        (reaction.emoji.name === emoji || reaction.emoji.id === emoji) && !user.bot;

      const collector = m.createReactionCollector({
        filter,
        max: 1,
        time: qTimeout * 1000,
      });

      collector.on('collect', async (reaction, user) => {
        await m.channel.sendTyping();
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
          // Remove bot reaction on timeout
          const reaction = m.reactions.cache.find(r =>
            (r.emoji.id && r.emoji.id === emoji) || (r.emoji.name === emoji)
          );
          if (reaction) {
            reaction.users.remove(client.user.id).catch(() => { });
          }
        }
      });
    }

    // Gestion des réponses ASK (Question/Réponse)
    if (!isEmpty(answerCount) && answerCount !== "0" && answerCount !== 0) {
      let timeoutVal = parseInt(timeout, 10);
      if (isNaN(timeoutVal)) timeoutVal = 60; // default 60s

      const timeoutMs = timeoutVal * 1000;

      // We respond immediately to acknowledge the request
      res.status(200).json({
        success: true,
        type: "ASK",
        timeout: timeoutVal
      });

      // Handle Emoji A-Z selection
      let emojiList = [
        "🇦", "🇧", "🇨", "🇩", "🇪", "🇫", "🇬", "🇭", "🇮", "🇯", "🇰", "🇱", "🇲",
        "🇳", "🇴", "🇵", "🇶", "🇷", "🇸", "🇹", "🇺", "🇻", "🇼", "🇽", "🇾", "🇿",
      ];

      // Convert to Number safely
      const count = parseInt(answerCount, 10);
      let a = 0;
      // React with options
      while (a < count && a < emojiList.length) {
        await m.react(emojiList[a]).catch(e => config.logger("Error reacting: " + e.message, "error"));
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
            "🇦": 0, "🇧": 1, "🇨": 2, "🇩": 3, "🇪": 4, "🇫": 5, "🇬": 6, "🇭": 7, "🇮": 8, "🇯": 9, "🇰": 10, "🇱": 11, "🇲": 12,
            "🇳": 13, "🇴": 14, "🇵": 15, "🇶": 16, "🇷": 17, "🇸": 18, "🇹": 19, "🇺": 20, "🇻": 21, "🇼": 22, "🇽": 23, "🇾": 24, "🇿": 25,
          };

          const userResponseIndex = emojiMap[reaction.emoji.name];

          // Legacy: url was JSON stringified request when using ASK?
          // If new payload sends it as object, we use it directly.
          let requestPayload = url;
          if (typeof url === 'string') {
            try { requestPayload = JSON.parse(url); } catch (e) { }
          }

          httpPost("ASK", {
            channelId: m.channel.id,
            response: userResponseIndex,
            request: requestPayload,
          });
        })
        .catch(() => {
          m.delete().catch(() => { });
        });

    } else if (!isEmpty(answerCount) && (answerCount === "0" || answerCount === 0)) {
      // ASK Mode: Text Response (0 options)
      let timeoutVal = parseInt(timeout, 10);
      if (isNaN(timeoutVal)) timeoutVal = 60;
      const timeoutMs = timeoutVal * 1000;

      res.status(200).json({ success: true, type: "ASK_TEXT" });

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
          const userResponseText = msg.content;
          msg.react("✅").catch(() => { });

          let requestPayload = url;
          if (typeof url === 'string') {
            try { requestPayload = JSON.parse(url); } catch (e) { }
          }

          httpPost("ASK", {
            channelId: m.channel.id,
            response: userResponseText,
            request: requestPayload,
          });
        })
        .catch(() => {
          m.delete().catch(() => { });
        });
    } else {
      // Normal embed (no ASK)
      res.status(200).json({ success: true });
    }
  } catch (error) {
    config.logger("DiscordLink ERROR sendEmbed: " + error.message, "ERROR");
    console.error(error);
    res.status(500).json({ error: error.message });
  }
});

/***** Clear channel messages (POST) *****/
app.post("/clearChannel", async (req, res) => {
  try {
    const channelID = req.body.channelID;
    const daysToKeep = req.body.daysToKeep;

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


/***** Delete last N messages from channel (POST) *****/
app.post("/deleteLastMessages", async (req, res) => {
  try {
    const channelID = req.body.channelID;
    const count = req.body.count;

    if (!channelID) {
      return res.status(400).json({ error: "channelID manquant" });
    }

    if (!count) {
      return res.status(400).json({ error: "count manquant" });
    }

    const channel = client.channels.cache.get(channelID);

    if (!channel) {
      return res.status(404).json({ error: "Channel non trouvé" });
    }

    // Répondre immédiatement pour éviter les timeouts côté Jeedom
    res.status(200).json({
      status: "ok",
      channelID,
      count,
      message: "Suppression en cours...",
    });

    // Effectuer la suppression en arrière-plan
    try {
      await deleteLastMessages(channel, count);
      config.logger(
        "Suppression des derniers messages du channel " + channelID + " terminée avec succès",
        "INFO",
      );
    } catch (error) {
      config.logger(
        "Erreur lors de la suppression des derniers messages du channel " +
        channelID +
        ": " +
        error.message,
        "ERROR",
      );
    }
  } catch (error) {
    config.logger("DiscordLink ERROR deleteLastMessages: " + error.message, "ERROR");
    res.status(500).json({ error: error.message });
  }
});

/**
 * Utility function to delete messages from an array
 * Handles both bulk delete (for messages < 14 days) and individual delete (for older messages)
 * @param {Object} channel - The Discord channel object
 * @param {Array} messagesToDelete - Array of message objects to delete
 * @param {Object} stats - Statistics object to accumulate results { totalDeleted, totalBulkDeleted, totalIndividualDeleted }
 * @returns {Promise<void>}
 */
const performMessageDeletion = async (channel, messagesToDelete, stats) => {
  if (messagesToDelete.length === 0) return;

  const FOURTEEN_DAYS_MS = 14 * 86400000;
  const fourteenDaysAgoTimestamp = Date.now() - FOURTEEN_DAYS_MS + 60000;

  const recentMessages = []; // Messages récents à supprimer en masse (< 14 jours)
  const ancientMessages = []; // > 14 jours : suppression individuelle

  // Trier les messages par date
  for (const message of messagesToDelete) {
    if (!message.deletable) continue;

    if (message.createdTimestamp > fourteenDaysAgoTimestamp) {
      recentMessages.push(message);
    } else {
      ancientMessages.push(message);
    }
  }

  // Suppression en masse (messages récents)
  if (recentMessages.length > 0) {
    try {
      const deleted = await channel.bulkDelete(recentMessages);
      stats.totalBulkDeleted += deleted.size;
      stats.totalDeleted += deleted.size;
      config.logger(deleted.size + " messages supprimés en masse", "DEBUG");
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
        stats.totalIndividualDeleted++;
        stats.totalDeleted++;
      } catch (e) {
        config.logger("Echec suppression message " + message.id + ": " + e.message, "WARNING");
      }
    }
    config.logger(deletedInThisBatch + " vieux messages (>14j) supprimés un par un", "DEBUG");
  }
};

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

    // Timestamps de référence (minuit aujourd'hui en heure locale)
    const nowTimestamp = Date.now();
    const todayTimestamp = new Date().setHours(0, 0, 0, 0);

    // Si daysToKeep == -1 (tout effacer) : on prend nowTimestamp comme limite
    // Sinon calcul classique (ex: 1 -> hier minuit)
    const daysToKeepTimestamp = daysToKeep == -1 ? nowTimestamp : todayTimestamp - (daysToKeep * ONE_DAY_MS);

    let lastMessageId = null; // Curseur pour la pagination
    const stats = { totalDeleted: 0, totalBulkDeleted: 0, totalIndividualDeleted: 0 };

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

      const messagesToDelete = [];

      for (const [msgId, message] of messages) {
        // Supprimer uniquement les messages plus vieux que le timestamp limite
        if (message.createdTimestamp < daysToKeepTimestamp && message.deletable) {
          messagesToDelete.push(message);
        }
      }

      // Effectuer la suppression en réutilisant la fonction utilitaire
      await performMessageDeletion(channel, messagesToDelete, stats);
    }

    config.logger("========================================", "INFO");
    config.logger("Nettoyage terminé - Récapitulatif :", "INFO");
    config.logger("- Messages supprimés en masse : " + stats.totalBulkDeleted, "INFO");
    config.logger("- Messages supprimés individuellement (>14j) : " + stats.totalIndividualDeleted, "INFO");
    config.logger("- TOTAL supprimés : " + stats.totalDeleted, "INFO");
    config.logger("========================================", "INFO");
    return stats.totalDeleted;
  } catch (error) {
    config.logger("Erreur critique lors du nettoyage : " + error.message, "ERROR");
    throw error;
  }
};

/**
 * Delete the last N messages from a channel
 * @param {Object} channel - The Discord channel object
 * @param {number} count - The number of messages to delete
 * @returns {Promise<void>}
 */
const deleteLastMessages = async (channel, count) => {
  try {
    // Sécurisation du type (int) : Base 10
    count = parseInt(count, 10);

    if (count <= 0) {
      config.logger("Nombre de messages invalide: " + count, "WARNING");
      return;
    }

    let messagesToDelete = [];
    let lastMessageId = null;

    config.logger("Début de la suppression des " + count + " derniers messages du channel " + channel.id, "INFO");

    // Récupérer les messages jusqu'à ce qu'on en ait assez
    while (messagesToDelete.length < count) {
      const fetchOptions = { limit: Math.min(100, count - messagesToDelete.length), cache: false };
      if (lastMessageId) {
        fetchOptions.before = lastMessageId;
      }

      const messages = await channel.messages.fetch(fetchOptions);

      if (messages.size === 0) {
        config.logger("Fin de l'historique atteinte. Seulement " + messagesToDelete.length + " messages trouvés.", "DEBUG");
        break;
      }

      lastMessageId = messages.last().id;

      // Ajouter les messages supprimables à la liste
      for (const [msgId, message] of messages) {
        if (message.deletable && messagesToDelete.length < count) {
          messagesToDelete.push(message);
        }
      }
    }

    // Supprimer les messages obtenus
    const stats = { totalDeleted: 0, totalBulkDeleted: 0, totalIndividualDeleted: 0 };
    await performMessageDeletion(channel, messagesToDelete, stats);

    config.logger("========================================", "INFO");
    config.logger("Suppression des derniers messages terminée - Récapitulatif :", "INFO");
    config.logger("- Messages supprimés en masse : " + stats.totalBulkDeleted, "INFO");
    config.logger("- Messages supprimés individuellement (>14j) : " + stats.totalIndividualDeleted, "INFO");
    config.logger("- TOTAL supprimés : " + stats.totalDeleted, "INFO");
    config.logger("========================================", "INFO");
    return stats.totalDeleted;
  } catch (error) {
    config.logger("Erreur critique lors de la suppression des derniers messages : " + error.message, "ERROR");
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

    if (interaction.commandName === "deletelastmessages") {
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

      const nbmessages = interaction.options.getString("nbmessages");
      const channel = client.channels.cache.get(interaction.channelId);
      const channelID = channel.id;

      config.logger("Commande deleteLastMessages reçue pour channel " + channelID + " avec nbmessages=" + nbmessages, "INFO");

      try {
        const deletedCount = await deleteLastMessages(channel, nbmessages);
        config.logger(
          "Suppression des derniers messages du channel " + channelID + " terminée avec succès (" + deletedCount + " messages supprimés)",
          "INFO",
        );
        interaction.editReply("Derniers " + deletedCount + " messages supprimés avec succès !");
      } catch (error) {
        config.logger(
          "Erreur lors de la suppression des derniers messages du channel " +
          channelID +
          ": " +
          error.message,
          "ERROR",
        );
      }
    }

    if (interaction.commandName === "keeplastdays") {
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

      const nbjours = interaction.options.getString("nbjours");
      const channel = client.channels.cache.get(interaction.channelId);
      const channelID = channel.id;

      config.logger("Commande keeplastdays reçue pour channel " + channelID + " avec nbjours=" + nbjours, "INFO");

      try {
        const deletedCount = await deleteOldChannelMessages(channel, nbjours);
        config.logger(
          "Suppression des derniers messages du channel " + channelID + " terminée avec succès (" + deletedCount + " messages supprimés)",
          "INFO",
        );

        let response
        if (nbjours == -1) {
          response = "Tous les messages supprimés avec succès !";
        }
        else if (deletedCount === 0) {
          response = "Aucun message à supprimer, le channel est déjà propre !";
        }
        else {
          response = deletedCount + " messages de plus de " + nbjours + " jours supprimés avec succès !";
        }

        interaction.editReply(response);
      } catch (error) {
        config.logger(
          "Erreur lors de la suppression des derniers messages du channel " +
          channelID +
          ": " +
          error.message,
          "ERROR",
        );
      }
    }

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
