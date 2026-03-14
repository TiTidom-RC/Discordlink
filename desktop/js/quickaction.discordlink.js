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

    function initQuickAction(quickActionData) {

        quickActionData.forEach(function (item) {
            // console.log('Adding quick action item:', item);
            addItem(item)
        });

        document.getElementById('addItem').addEventListener('click', function () {
            addItem({ type: 'interaction', timeout: 120 });
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('.removeItem')) {
                e.target.closest('.quickActionItem').remove();
            }
        });

        document.addEventListener('change', function (e) {
            if (e.target.closest('select.typeSelect')) {
                const typeSelect = e.target.closest('select.typeSelect');

                if (typeSelect.value === 'interaction') {
                    typeSelect.closest('.quickActionItem').querySelector('.valueInput').setAttribute('placeholder', '{{Texte de l\'interaction}}');
                }
                else if (typeSelect.value === 'command') {
                    typeSelect.closest('.quickActionItem').querySelector('.valueInput').setAttribute('placeholder', '{{ID de la commande Jeedom (ex: 1234)}}');
                }
                else if (typeSelect.value === 'scenario') {
                    typeSelect.closest('.quickActionItem').querySelector('.valueInput').setAttribute('placeholder', '{{ID du scénario Jeedom (ex: 17)}}');
                }
            }
        });

        document.getElementById('saveQuickAction').addEventListener('click', function () {
            const quickActionArray = [];
            let isValid = true;
            document.querySelectorAll('.quickActionItem').forEach(function (item) {
                const key = item.querySelector('.keyInput').value.trim();
                const label = item.querySelector('.labelInput').value.trim();
                const emoji = item.querySelector('.emojiInput').value.trim();
                const type = item.querySelector('.typeSelect').value || 'interaction';
                const timeout = parseInt(item.querySelector('.timeoutInput').value);
                const value = item.querySelector('.valueInput').value.trim();

                if (!key || !emoji || !label || isNaN(timeout) || !type || !value) {
                    isValid = false;
                    return;
                }

                if (!/^[a-z0-9_]+$/.test(key)) {
                    jeedomUtils.showAlert({ message: key + ' : {{Cette clé doit être en minuscules et ne contenir que des lettres, des chiffres ou des underscores.}}', level: 'danger' });
                    isValid = false;
                    return;
                }

                if (quickActionArray.filter(item => item.key === key).length > 0) {
                    jeedomUtils.showAlert({ message: '{{Clé}} "' + key + '" {{déjà utilisée.}}', level: 'danger' });
                    isValid = false;
                    return;
                }

                quickActionArray.push({
                    key: key,
                    emoji: emoji,
                    label: label,
                    timeout: timeout,
                    type: type,
                    value: value
                });
            });

            if (!isValid) {
                jeedomUtils.showAlert({ message: '{{Veuillez corriger les erreurs dans le formulaire.}}', level: 'danger' });
                return;
            }

            domUtils.ajax({
                type: "POST",
                url: AJAX_URL,
                data: {
                    action: "saveQuickAction",
                    quickActionData: JSON.stringify(quickActionArray)
                },
                dataType: 'json',
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function (data) {
                    if (data.state != 'ok') {
                        jeedomUtils.showAlert({ message: data.result, level: 'danger' });
                        return;
                    }
                    jeedomUtils.showAlert({ message: '{{Sauvegarde réussie}}', level: 'success' });
                }
            });
        });
    }

    function addItem(item) {
        const container = document.getElementById('quickActionContainer');
        const newItem = `
            <tr class="quickActionItem">
                <td>
                    <input type="text" class="form-control keyInput" placeholder="{{Clé}}" value="${item.key || ''}" required>
                </td>
                <td>
                    <input type="text" class="form-control labelInput" placeholder="{{Libellé à afficher}}" value="${item.label || ''}" required>
                </td>
                <td>
                    <input type="text" class="form-control emojiInput" placeholder="{{Émoji}}" value="${item.emoji || ''}" required>
                </td>
                <td>
                    <select class="form-control typeSelect">
                        <option value="interaction" ${item.type === 'interaction' ? 'selected' : ''}>{{Interaction}}</option>
                        <option value="command" ${item.type === 'command' ? 'selected' : ''}>{{Commande}}</option>
                        <option value="scenario" ${item.type === 'scenario' ? 'selected' : ''}>{{Scénario}}</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control valueInput" placeholder="{{Texte de l'interaction}}" value="${item.value || ''}" required>
                </td>
                <td>
                    <input type="number" class="form-control timeoutInput" placeholder="{{Timeout (en secondes)}}" value="${item.timeout || 120}" min="0" required>
                </td>
                <td style="text-align: right;">
                    <i class="fas fa-minus-circle removeItem icon_red" title="{{Supprimer l'action}}"></i>
                </td>
            </tr>
        `;
        container.insertAdjacentHTML('beforeend', newItem);
    }

    window.initQuickAction = initQuickAction;
})();