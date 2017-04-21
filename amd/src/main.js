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

define(['jquery', 'core/templates', 'filter_ally/ally', 'filter_ally/imagecover'], function($, Templates, Ally, ImageCover) {
    return new function() {

        var self = this;

        self.canViewFeedback = false;
        self.canDownload = false;

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
                        isimage : false,
                        fileid: pathHash,
                        url: $(moduleEl).attr('href'),
                        // Too expensive to do at module level - this is a course level capability.
                        canviewfeedback : self.canViewFeedback,
                        candownload : self.canDownload,
                        html: '<span id="content-target-'+pathHash+'"></span>'
                    };
                    renderTemplate(data, pathHash, moduleId);
                }
            });
        };

        /**
         * Render template and insert result in appropriate place for modules.
         */
        var renderTemplate = function(data, pathHash, moduleId) {
            Templates.render('filter_ally/wrapper', data)
                .done(function(result) {
                    var moduleEl = $('#module-' + moduleId + ' .activityinstance');
                    $(moduleEl).after(result);
                    // We are inserting the module element next to the target as opposed to replacing the
                    // target as we want to ensure any listeners attributed to the module element persist.
                    $('#content-target-' + pathHash).after(moduleEl);
                    $('#content-target-' + pathHash).remove();
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
            if (Object.keys(ally_module_maps.file_resources).length > 0 && (canViewFeedback || canDownload)) {
                placeHoldResourceModule(ally_module_maps.file_resources);
            }
            Ally.init(jwt);
            ImageCover.init();
        };
    };
});
