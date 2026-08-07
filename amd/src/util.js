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
 * Utility lib.
 *
 * @package
 * @author    Guy Thomas / Branden Visser
 * @copyright Copyright (c) 2017 Open LMS / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';

class Util {

    /**
     * When evaluateFunction returns true.
     * @author Guy Thomas
     * @param {function} evaluateFunction
     * @param {integer} maxIterations
     * @returns {promise} jQuery promise
     */
    whenTrue(evaluateFunction, maxIterations) {

        maxIterations = !maxIterations ? 10 : maxIterations;

        const dfd = $.Deferred();
        let i = 0;

        // Maintains a handle to the interval timer, so it can be cleaned up when the element is removed.
        let intervalHandle = null;

        /**
         * The function that will be used to try the evaluation repeatedly.
         */
        const loop = function() {
            i++;
            if (i > maxIterations) {
                dfd.reject();
                if (intervalHandle) {
                    // Cleanup the interval.
                    clearInterval(intervalHandle);
                    intervalHandle = null;
                }
                return;
            }
            if (evaluateFunction()) {
                dfd.resolve();
                if (intervalHandle) {
                    // Cleanup the interval.
                    clearInterval(intervalHandle);
                    intervalHandle = null;
                }
                return;
            }
        };

        intervalHandle = setInterval(loop, 200);

        return dfd.promise();
    }

    /**
     * Builds an object which contains all the parameters passed in a URL.
     * @param {string} url URL which has parameters
     * @returns {Object}
     */
    getQuery(url) {
        const query = {};

        url.replace(/[?&](.+?)=([^&#]*)/g, function(_, key, value) {
            query[key] = decodeURI(value).replace(/\+/g, ' ');
        });

        return query;
    }

    /**
     * Taken from underscore.js - debounce function to prevent function spamming on event triggers.
     * Modified by GThomas to implement deferred.
     * @param {function} func
     * @param {int} wait
     * @param {boolean} immediate
     * @returns {Promise}
     */
    debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const dfd = $.Deferred();
            const context = this,
                args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) {
                    dfd.resolve(func.apply(context, args));
                }
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) {
                dfd.resolve(func.apply(context, args));
            }
            return dfd;
        };
    }

    /**
     * Throttle a function while preserving the latest call arguments for the trailing run.
     *
     * We intentionally keep this local implementation for Ally image cover positioning and
     * do not rely on core throttle behavior, so that trailing executions always receive
     * the latest args/context observed during the cooldown window.
     *
     * @param {function} func
     * @param {int} wait
     * @returns {Function}
     */
    throttle(func, wait) {
        let onCooldown = false;
        let hasQueuedCall = false;
        let queuedArgs = null;
        let queuedContext = null;

        const run = (context, args) => {
            func.apply(context, args);
            onCooldown = true;

            setTimeout(() => {
                if (!hasQueuedCall) {
                    onCooldown = false;
                    return;
                }

                const latestContext = queuedContext;
                const latestArgs = queuedArgs;
                hasQueuedCall = false;
                queuedArgs = null;
                queuedContext = null;
                run(latestContext, latestArgs);
            }, wait);
        };

        return function(...args) {
            if (!onCooldown) {
                run(this, args);
                return;
            }

            hasQueuedCall = true;
            queuedArgs = args;
            queuedContext = this;
        };
    }
}

export default new Util();
