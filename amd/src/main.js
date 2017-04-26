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

define(['jquery', 'filter_ally/ally', 'filter_ally/imagecover'], function($, Ally, ImageCover) {
    return new function() {

        /**
         * Rewrite the DOM to include file ids for file module.
         * @param moduleFileIdMapping
         */
        var addModuleFileIds = function(moduleFileIdMapping) {

        };

        this.init = function(moduleFileIdMapping, jwt) {
            addModuleFileIds(moduleFileIdMapping);
            Ally.init(jwt);
            ImageCover.init();
        };
    };
});
