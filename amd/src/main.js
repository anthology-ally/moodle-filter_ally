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
         */
        var renderTemplate = function(data, pathHash, targetEl) {
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
                });
        };

        /**
         * Add place holders for assign module additional files.
         * @param assignFileMapping
         */
        var placeHoldAssignModule = function(assignFileMapping) {
            $(document).ready(function() {
                Util.whenTrue(function() {
                    return $('div[id*="assign_files_tree"] .ygtvitem').length > 0;
                }, 10)
                    .done(function() {
                        $('div[id*="assign_files_tree"] a[href*="pluginfile.php"]').each(function() {
                            var href = $(this).attr('href');
                            var regex;
                            if (href.indexOf('?') > -1) {
                                regex = /pluginfile.php\/(\d*)\/(.*)(\?)/;
                            } else {
                                regex = /pluginfile.php\/(\d*)\/(.*)/;
                            }
                            var match = href.match(regex);
                            var path = match[1] + '/' + match[2];
                            path = decodeURIComponent(path);
                            var pathHash = assignFileMapping[path];

                            var data = {
                                isimage: false,
                                fileid: pathHash,
                                url: href
                            };

                            renderTemplate(data, pathHash, $(this));
                        });
                    });
            });
        };

        /**
         * Add place holders for resource module.
         * @param moduleFileMapping
         */
        var placeHoldResourceModule = function(moduleFileMapping) {
            $(document).ready(function() {
                for (var moduleId in moduleFileMapping) {
                    var pathHash = moduleFileMapping[moduleId];
                    var moduleEl = $('#module-' + moduleId + ' .activityinstance');
                    var data = {
                        isimage: false,
                        fileid: pathHash,
                        url: $(moduleEl).attr('href')
                    };
                    renderTemplate(data, pathHash, moduleEl);
                }
            });
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
                if (Object.keys(ally_module_maps.file_resources).length > 0) {
                    placeHoldResourceModule(ally_module_maps.file_resources);
                }
                if (Object.keys(ally_module_maps.assignment_files).length > 0) {
                    placeHoldAssignModule(ally_module_maps.assignment_files);
                }
            }
            Ally.init(jwt);
            ImageCover.init();
        };
    };
});
