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
 * @package
 * @author    Guy Thomas
 * @copyright Copyright (c) 2016 Open LMS / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import ElementBoundsTracker from 'filter_ally/elementboundstracker';
import {eventTypes} from 'core_filters/events';

class ImageCover {
    #wrapperApplySizing(wrapper) {
        // Note - we are using .attr and not .data so that we can observe what is happening to the dom elements.
        if ($(wrapper).attr('data-processed')) {
            return;
        }

        $(wrapper).attr('data-processed', 1);

        const img = $(wrapper).find('img');
        const cover = $(wrapper).find('.ally-image-cover');

        this.tracker.register(img, null, cover, true);
    }

    #applySizing() {
        for (const wrapper of $('.ally-image-wrapper')) {
            this.#wrapperApplySizing(wrapper);
        }
    }

    init() {
        this.tracker = new ElementBoundsTracker(false);
        $(document).ready(() => this.#applySizing());

        // Listen for new content and apply sizing to any new image wrappers.
        document.addEventListener(eventTypes.filterContentUpdated, e => {
            const nodes = e.detail.nodes;
            for (const node of nodes) {
                if ($(node).hasClass('ally-image-wrapper')) {
                    this.#wrapperApplySizing(node);
                }
                for (const wrapper of $(node).find('.ally-image-wrapper')) {
                    this.#wrapperApplySizing(wrapper);
                }
            }
            // Also reApply sizing to all visible wrappers.
            this.tracker.evaluateVisibleElements();
        });

        const targetNode = document;
        const observerOptions = {
            childList: true,
            attributes: true,
            subtree: true
        };
        /**
         *  By using an event combined with a mutation observer that disconnects itself,
         *  we can manage to have a mutation observer that works after page content lazy loaded by loaded in snap.
         *  the interval is added as a redundancy to prevent calculation errors by correcting the indicator position.
         * */
        $(document).on('snap-course-content-loaded', () => {
            const observer = new MutationObserver(() => {
                let count = 0;
                let interval = setInterval(() => {
                    if (count < 5) {
                        this.#applySizing();
                        count++;
                    } else {
                        clearTimeout(interval);
                    }
                }, 500);
                observer.disconnect();
            });
            observer.observe(targetNode, observerOptions);
        });
    }
}

export default new ImageCover();
