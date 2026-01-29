/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

(function () {
  'use strict';

  const AJAX_URL = 'plugins/discordlink/core/ajax/discordlink.ajax.php';

  function initEmoji() {
    domUtils.ajax({
      type: "POST",
      url: AJAX_URL,
      data: {
        action: "getEmoji",
      },
      dataType: "json",
      error: function (request, status, error) {
        handleAjaxError(request, status, error, document.getElementById("div_EmojiAlert"));
      },
      success: function (data) {
        if (data.state != "ok") {
          jeedomUtils.showAlert({ message: "ERROR", level: "danger" });
          return;
        }
        for (var i in data.result) {
          addEmojiToTable(data.result[i]);
        }
      },
    });
  }

  function addEmojiToTable(_cmd) {
    if (typeof _cmd.configuration === 'undefined') {
      _cmd.configuration = {};
    }

    const rowHtml = `<td>
      <div class="row">
        <div class="col-lg-8">
          <input class="emojiAttr form-control input-sm" data-l1key="keyEmoji">
        </div>
      </div>
    </td>
    <td>
      <div class="row">
        <div class="col-lg-8">
          <input class="emojiAttr form-control input-sm" data-l1key="codeEmoji">
        </div>
      </div>
    </td>
    <td>
      <i class="fas fa-minus-circle pull-right emojiAction cursor" data-action="remove"></i>
    </td>`;

    const newRow = document.createElement('tr');
    newRow.className = 'emoji-row';
    newRow.innerHTML = rowHtml;

    // Set values
    const inputs = newRow.querySelectorAll('.emojiAttr');
    inputs.forEach(input => {
      const key = input.getAttribute('data-l1key');
      if (key && _cmd[key] !== undefined) {
        input.value = _cmd[key];
      }
    });

    const tableBody = document.querySelector("#table_emoji tbody");
    if (tableBody) {
      tableBody.appendChild(newRow);
    }
  }

  function getAllEmojiValues() {
    const rows = document.querySelectorAll('#table_emoji tbody .emoji-row');
    const result = [];
    rows.forEach(row => {
      const item = {};
      const inputs = row.querySelectorAll('.emojiAttr');
      
      inputs.forEach(input => {
        const key = input.getAttribute('data-l1key');
        if (key) {
          item[key] = input.value;
        }
      });
      result.push(item);
    });
    return result;
  }

  function cleanupEmoji() {
    // Remove event listeners to prevent memory leaks
    if (window.discordLinkEmojiEventHandlers && window.discordLinkEmojiEventHandlers.onClick) {
      document.body.removeEventListener('click', window.discordLinkEmojiEventHandlers.onClick);
      window.discordLinkEmojiEventHandlers.onClick = null;
    }
  }

  // Event Delegation
  if (!window.discordLinkEmojiEventHandlers) {
    window.discordLinkEmojiEventHandlers = {};
  }

  if (!window.discordLinkEmojiEventHandlers.onClick) {
    window.discordLinkEmojiEventHandlers.onClick = function (e) {
      // Save Emoji
      if (e.target.closest('.eqLogicAction[data-action=saveEmoji]')) {
        const emojiArray = getAllEmojiValues();

        domUtils.ajax({
          type: "POST",
          url: AJAX_URL,
          data: {
            action: "saveEmoji",
            arrayEmoji: JSON.stringify(emojiArray),
          },
          dataType: "json",
          error: function (request, status, error) {
            handleAjaxError(request, status, error, document.getElementById("div_EmojiAlert"));
          },
          success: function (data) {
            if (data.state != "ok") {
              jeedomUtils.showAlert({ message: "ERROR", level: "danger" });
              return;
            }
          },
        });
      }

      // Add Emoji
      if (e.target.closest('#bt_addEmoji')) {
        let _cmd = {};
        addEmojiToTable(_cmd);
      }

      // Reset Emoji
      if (e.target.closest('#bt_reset')) {
        domUtils.ajax({
          type: "POST",
          url: AJAX_URL,
          data: {
            action: "resetEmoji",
          },
          dataType: "json",
          error: function (request, status, error) {
            handleAjaxError(request, status, error, document.getElementById("div_EmojiAlert"));
          },
          success: function (data) {
            if (data.state != "ok") {
              jeedomUtils.showAlert({ message: "ERROR", level: "danger" });
              return;
            }
            // Refresh table instead of page reload
            const tableBody = document.querySelector("#table_emoji tbody");
            if (tableBody) tableBody.innerHTML = '';
            initEmoji();
          },
        });
      }

      // Remove Emoji
      if (e.target.closest('.emoji-row .emojiAction[data-action=remove]')) {
        if (typeof modifyWithoutSave !== 'undefined') modifyWithoutSave = true;
        const tr = e.target.closest('tr');
        if (tr) tr.remove();
      }
    };
  }

  document.body.removeEventListener('click', window.discordLinkEmojiEventHandlers.onClick);
  document.body.addEventListener('click', window.discordLinkEmojiEventHandlers.onClick);

  // Global exposure
  window.initEmoji = initEmoji;
  window.cleanupEmoji = cleanupEmoji;

})();