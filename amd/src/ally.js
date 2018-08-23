/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Ally AX library.
 *
 * @package   filter_ally
 * @author    Guy Thomas <osdev@blackboard.com>
 * @copyright Copyright (c) 2016 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/log'], function($, log) {
    return new function() {
        var _config = null;
        var _token = null;
        var _baseUrl = null;

        /**
         * Get the base URL for a given url.
         *
         * e.g.,  given `https://ally.local/api/v1/20/lti/institution`, this function will return `https://ally.local`.
         *
         * @param  {String} url A full URL
         * @return {String} The base URL of the given `url`.
         */
        var getBaseUrl = function(url) {
            var parser = document.createElement('a');
            parser.href = url;

            var baseUrl = parser.protocol + '//' + parser.hostname;
            if (parser.port) {
                baseUrl += ':' + parser.port;
            }

            return baseUrl;
        };

        /**
         * Initialize the AMD module with the necessary data
         * @param  {String} jwt    The JWT token
         * @param  {Object} config The Ally configuration containing the Ally client id and admin URL
         */
        this.init = function(jwt, config) {
            _token = jwt;
            _config = config;
            if (!config.adminurl) {
                // Do not localise - just a debug message.
                log.info('The Ally admin tool is not configured with a Launch URL. Aborting JS load.');
                return;
            }
            _baseUrl = getBaseUrl(config.adminurl);

            // Load up the Ally script.
            // Note - this is not to be cached as it is just a loader script.
            // The target script below loads up the latest version of the amd module which does get cached.
            $.getScript(_baseUrl + '/integration/moodlerooms/ally.js')
                .fail(function() {
                    log.error('Failed to load Ally JS');
                });
        };

        /**
         * Get the JWT token that can be used to authenticate the current user
         * @return {String} The JWT token
         */
        this.token = function() {
            return _token;
        };

        /**
         * Get the Ally configuration containing the Ally client id and base URL
         * @return {Object} The Ally configuration
         */
        this.config = function() {
            return _config;
        };

        /**
         * Get the Ally base URL
         * @return {String} The Ally base URL
         */
        this.getAllyBaseUrl = function() {
            return _baseUrl;
        };
    };
});
