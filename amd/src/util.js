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
 * @package   filter_ally
 * @author    Guy Thomas / Branden Visser
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return new function() {

        /**
         * When evaluateFunction returns true.
         * @author Guy Thomas
         * @param evaluateFunction
         * @param maxIterations
         * @returns {promise} jQuery promise
         */
        this.whenTrue = function(evaluateFunction, maxIterations) {

            maxIterations = !maxIterations ? 10 : maxIterations;

            var dfd = $.Deferred();
            var i = 0;

            setInterval(function() {
                i = !i ? 0 : i + 1;
                if (i > maxIterations) {
                    dfd.reject();
                }
                if (evaluateFunction()) {
                    dfd.resolve();
                }
            }, 200);

            return dfd.promise();
        };

        /**
         * Listen for the offset/size of a given element to change. Whenever it changes, invoke the given function.
         * @author Branden Visser
         * @param  {jQuery}     $el                     The element to watch
         * @param  {Function}   callback                The function that is invoked when the coords change
         * @param  {Object}     callback.coords         The new set of coords
         * @param  {Number}     callback.coords.top     The top offset of the element
         * @param  {Number}     callback.coords.right   The right offset of the element
         * @param  {Number}     callback.coords.bottom  The bottom offset of the element
         * @param  {Number}     callback.coords.left    The left offset of the element
         * @api private
         */
        this.onCoordsChange = function($el, callback) {

            // Maintains the last known set of coords
            var lastCoords = {};

            // Maintains a handle to the interval timer, so it can be cleaned up when the element is removed
            var intervalHandle = null;

            /*!
             * The function that is continuously run to determine if there was a change in coords
             */
            var _loop = function() {
                var offset = $el.offset();
                var width = $el.width();
                var height = $el.height();

                var currCoords = {
                    'top': offset.top,
                    'right': offset.left + width,
                    'bottom': offset.top + height,
                    'left': offset.left
                };

                // Only continue if the coordinates have changed. Otherwise we do nothing
                if (currCoords.top !== lastCoords.top || currCoords.right !== lastCoords.right ||
                    currCoords.bottom !== lastCoords.bottom || currCoords.left !== lastCoords.left) {
                    // Set the new set of coords
                    lastCoords = currCoords;

                    // First ensure the element is still on the DOM. If not, we're going to clean everything up here
                    if (!$.contains(document.documentElement, $el[0])) {
                        if (intervalHandle) {
                            clearInterval(intervalHandle);
                            intervalHandle = null;
                        }
                        return;
                    }

                    // Finally, run the callback
                    return callback(lastCoords);
                }
            };

            // Start the interval timer
            intervalHandle = setInterval(_loop, 200);

            // Perform an immediate initial run
            _loop();
        };

        /**
         * Builds an object which contains all the parameters passed in a URL.
         * @param url URL which has parameters
         * @returns {Object}
         */
        this.getQuery = function(url) {
            var query = {};

            url.replace(/[?&](.+?)=([^&#]*)/g, function (_, key, value) {
                query[key] = decodeURI(value).replace(/\+/g, ' ');
            });

            return query;
        };

        /**
         * Taken from underscore.js - debounce function to prevent function spamming on event triggers.
         * Modified by GThomas to implement deferred.
         * @param function func
         * @param int wait
         * @param boolean immediate
         * @returns Deferred
         */
        this.debounce = function (func, wait, immediate) {
            var timeout;
            return function() {
                var dfd = $.Deferred();
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) { dfd.resolve(func.apply(context, args)); }
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) {
                    dfd.resolve(func.apply(context, args));
                }
                return dfd;
            };
        };
    };
});
