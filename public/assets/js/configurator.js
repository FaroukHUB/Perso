/**
 * PERSONNALY - Configurateur Produit
 * Version: 3.0 (Minimal)
 * Date: 2026-01-19
 *
 * NOTE: L'interactivité du configurateur (drag & drop, sélection couleur/police/technique)
 * est gérée par le script inline dans product.php.
 *
 * Ce fichier expose uniquement des utilitaires optionnels.
 */

(function() {
    'use strict';

    /**
     * Utilitaires exposés globalement
     */
    window.PersonnalyConfigurator = {
        version: '3.0',

        /**
         * Formater un prix en euros
         * @param {number} price
         * @returns {string}
         */
        formatPrice: function(price) {
            return price.toFixed(2).replace('.', ',') + ' €';
        },

        /**
         * Générer un ID unique
         * @returns {string}
         */
        generateId: function() {
            return 'el_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Debounce une fonction
         * @param {Function} func
         * @param {number} wait
         * @returns {Function}
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        /**
         * Afficher une notification simple
         * @param {string} message
         * @param {string} type - 'success', 'error', 'info'
         */
        notify: function(message, type) {
            console.log('[Configurator]', type + ':', message);
        }
    };

    console.log('[Configurator] v3.0 loaded');

})();
