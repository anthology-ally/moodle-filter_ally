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
 * Library to add image covers to prevent seizure inducing images from showing.
 *
 * @package   filter_ally
 * @author    Guy Thomas <osdev@blackboard.com>
 * @copyright Copyright (c) 2016 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'filter_ally/util'], function($, Util) {
    return new function() {

        var applySizing = function() {
            $('.ally-image-wrapper').each(function(){
                var wrapper = this;
                var img = $(wrapper).find('img');
                var cover = $(wrapper).find('.ally-image-cover');
                var feedback = $(wrapper).find('.ally-feedback');
                var marginTop = parseInt($(img).css('marginTop'));
                var marginLeft = parseInt($(img).css('marginLeft'));

                var debounceCoordsChanged = Util.debounce(function(coords) {
                    var width = (coords.right - coords.left);
                    var height = (coords.bottom - coords.top);
                    $(cover)
                        .css('width', width + 'px')
                        .css('height', height + 'px');
                    var topPos = $(img).position().top + marginTop;
                    var leftPos = $(img).position().left + marginLeft;
                    $(cover)
                        .css('top', topPos + 'px')
                        .css('left', leftPos + 'px');
                    if (feedback.length) {
                        feedback
                            .css('top', (topPos + height - feedback.height()) + 'px')
                            .css('left', leftPos + 'px');
                    }
                }, 1000);

                Util.onCoordsChange(img, function(coords) {
                    debounceCoordsChanged(coords);
                });
            });
        };

        this.init = function() {
            $(document).ready(applySizing);
            var targetNode = document;
            var observerOptions = {
                childList: true,
                attributes: true,
                subtree: true
            };
            /**
             *  By using the an event combined with a mutation observer that disconnects itself,
             *  we can manage to have a mutation observer that works after page content lazy loaded by loaded in snap.
             *  the interval is added as a redundancy to prevent calculation errors by correcting the indicator position.
             * */
            $(document).on('snap-course-content-loaded', function() {
                var observer = new MutationObserver(() => {
                    let count = 0;
                    let interval = setInterval(function(){
                        if (count < 5) {
                            applySizing();
                            count++;
                        } else {
                            clearTimeout(interval);
                        }
                    },500);
                    observer.disconnect();
                });
                observer.observe(targetNode, observerOptions);
            });
        };
    };
});
