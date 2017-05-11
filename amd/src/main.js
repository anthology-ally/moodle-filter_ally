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
 * Main library.
 *
 * @package   filter_ally
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2016 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/templates', 'filter_ally/ally', 'filter_ally/imagecover', 'filter_ally/util'],
function($, Templates, Ally, ImageCover, Util) {
    return new function() {

        var self = this;

        self.canViewFeedback = false;
        self.canDownload = false;

        /**
         * Render template and insert result in appropriate place.
         * @return {promise}
         */
        var renderTemplate = function(data, pathHash, targetEl) {
            var dfd = $.Deferred();

            // Too expensive to do at module level - this is a course level capability.
            data.canviewfeedback = self.canViewFeedback;
            data.candownload = self.canDownload;
            data.html = '<span id="content-target-' + pathHash + '"></span>';

            Templates.render('filter_ally/wrapper', data)
                .done(function(result) {
                    $(targetEl).after(result);
                    // We are inserting the module element next to the target as opposed to replacing the
                    // target as we want to ensure any listeners attributed to the module element persist.
                    $('#content-target-' + pathHash).after(targetEl);
                    $('#content-target-' + pathHash).remove();

                    dfd.resolve();
                });

            return dfd.promise();
        };

        /**
         * Place holder items that are matched by selector.
         * @param {string} selector
         * @param {string} map
         * @return {promise}
         */
        var placeHoldSelector = function(selector, map) {
            var dfd = $.Deferred();
            $(selector).each(function() {
                var url,
                    type;

                if ($(this).prop("tagName").toLowerCase() === 'a') {
                    url = $(this).attr('href');
                    type = 'a';
                } else {
                    url = $(this).attr('src');
                    type = 'img';
                }
                var regex;
                if (url.indexOf('?') > -1) {
                    regex = /pluginfile.php\/(\d*)\/(.*)(\?)/;
                } else {
                    regex = /pluginfile.php\/(\d*)\/(.*)/;
                }
                var match = url.match(regex);
                var path = match[1] + '/' + match[2];
                path = decodeURIComponent(path);
                var pathHash = map[path];
                if (pathHash === undefined) {
                    dfd.reject();
                    return dfd.promise();
                }

                var data = {
                    isimage: type === 'img',
                    fileid: pathHash,
                    url: url
                };

                renderTemplate(data, pathHash, $(this))
                    .done(function(){
                        dfd.resolve();
                    });
            });
            return dfd.promise();
        };

        /**
         * Add place holders for forum module image attachments (note, regular files are covered by php).
         * @param {array}
         * @return {promise}
         */
        var placeHoldForumModule = function(forumFileMapping) {
            var dfd = $.Deferred();
            placeHoldSelector('.forumpost .attachedimages img[src*="pluginfile.php"]', forumFileMapping)
                .done(function(){
                    dfd.resolve();
                });
            return dfd.promise();
        };

        /**
         * Add place holders for assign module additional files.
         * @param {array}
         * @return {promise}
         */
        var placeHoldAssignModule = function(assignFileMapping) {
            var dfd = $.Deferred();
            Util.whenTrue(function() {
                return $('div[id*="assign_files_tree"] .ygtvitem').length > 0;
            }, 10)
                .done(function() {
                    placeHoldSelector('div[id*="assign_files_tree"] a[href*="pluginfile.php"]', assignFileMapping);
                    dfd.resolve();
                });
            return dfd.promise();
        };

        /**
         * Add place holders for resource module.
         * @param moduleFileMapping
         * @return {promise}
         */
        var placeHoldResourceModule = function(moduleFileMapping) {
            var dfd = $.Deferred();
            var c = 0;

            /**
             * Once template promise resolved, resolve promise for this function.
             */
            var onTemplateRendered = function() {
                c++;
                // All resource modules have been dealt with.
                if (c >= moduleFileMapping.length) {
                    dfd.resolve();
                }
            };

            for (var moduleId in moduleFileMapping) {
                var pathHash = moduleFileMapping[moduleId];
                var moduleEl = $('#module-' + moduleId + ' .activityinstance');
                var data = {
                    isimage: false,
                    fileid: pathHash,
                    url: $(moduleEl).attr('href')
                };
                renderTemplate(data, pathHash, moduleEl)
                    .done(onTemplateRendered());
            }
            return dfd.promise();
        };

        /**
         * Apply place holders.
         * @return {promise}
         */
        var applyPlaceHolders = function() {
            var dfd = $.Deferred();

            var tasks = [
                {
                    mapVar: ally_module_maps.file_resources,
                    method: placeHoldResourceModule
                },
                {
                    mapVar: ally_module_maps.assignment_files,
                    method: placeHoldAssignModule
                },
                {
                    mapVar: ally_module_maps.forum_files,
                    method: placeHoldForumModule
                }
            ];

            $(document).ready(function() {
                var completed = 0;

                /**
                 * Run this once a task has resolved.
                 */
                var onTaskComplete = function() {
                    completed++;
                    if (completed === tasks.length) {
                        // All tasks completed.
                        dfd.resolve();
                    }
                };

                for (var t in tasks) {
                    var task = tasks[t];
                    if (Object.keys(task.mapVar).length > 0) {
                        task.method(task.mapVar)
                            .done(onTaskComplete);
                    } else {
                        // Skipped this task because mappings are empty.
                        onTaskComplete();
                    }
                }
            });
            return dfd.promise();
        };

        /**
         * Init function.
         * @param jwt
         * @param canViewFeedback
         * @param canDownload
         * @param moduleFileMapping
         */
        this.init = function(jwt, canViewFeedback, canDownload) {
            self.canViewFeedback = canViewFeedback;
            self.canDownload = canDownload;
            if (canViewFeedback || canDownload) {
                applyPlaceHolders()
                    .done(function() {
                        Ally.init(jwt);
                        ImageCover.init();
                    });
            }

        };
    };
});
