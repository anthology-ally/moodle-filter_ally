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
import Util from 'filter_ally/util';

/**
 * Whether an element with the given computed style is the containing block of its absolutely positioned
 * descendants: it is positioned, or it establishes one through a transform (including the individual translate,
 * rotate and scale properties), perspective, filter, containment (including content-visibility) or
 * will-change. An element that generates no box (display: contents) never is one.
 * @param {CSSStyleDeclaration} style
 * @returns {boolean}
 */
const createsContainingBlock = (style) => {
    if (style.display === 'contents') {
        return false;
    }

    const isSet = (value) => value !== undefined && value !== '' && value !== 'none';
    const willChange = (style.willChange || '').split(',').map((name) => name.trim());

    return style.position !== 'static' ||
        isSet(style.transform) ||
        isSet(style.translate) ||
        isSet(style.rotate) ||
        isSet(style.scale) ||
        isSet(style.perspective) ||
        isSet(style.filter) ||
        isSet(style.backdropFilter) ||
        /\b(layout|paint|strict|content)\b/.test(style.contain || '') ||
        /^(auto|hidden)$/.test(style.contentVisibility || '') ||
        willChange.some((name) => ['transform', 'translate', 'rotate', 'scale', 'perspective', 'filter'].includes(name));
};

/**
 * Find the ancestor that is the containing block of an absolutely positioned element.
 *
 * This is not the offsetParent of the image: a static td, th or table is the offsetParent of an image inside a
 * table cell, but it never is the containing block of an absolutely positioned element.
 * The root element is a candidate too, as it is the containing block when it is positioned. Returns null when
 * the containing block is the initial one (page origin).
 * @param {Element} element
 * @returns {Element|null}
 */
const getContainingBlock = (element) => {
    let ancestor = element.parentElement;
    while (ancestor) {
        if (createsContainingBlock(window.getComputedStyle(ancestor))) {
            return ancestor;
        }
        ancestor = ancestor.parentElement;
    }

    return null;
};

/**
 * Position of an element's top left corner relative to the containing block of an absolutely positioned element,
 * which is what the top and left of that element are measured from.
 *
 * The scroll position is not added when the containing block is the root element, because the offset of an
 * element is already relative to the document.
 * @param {jQuery} $target The element whose position is measured.
 * @param {Element} absoluteElement The absolutely positioned element that is placed at that position.
 * @returns {{top: number, left: number}}
 */
const getPositionInContainingBlock = ($target, absoluteElement) => {
    const offset = $target.offset();
    const containingBlock = getContainingBlock(absoluteElement);
    if (!containingBlock) {
        return {top: offset.top, left: offset.left};
    }

    const $containingBlock = $(containingBlock);
    const blockOffset = $containingBlock.offset();
    const borderTop = parseFloat($containingBlock.css('borderTopWidth')) || 0;
    const borderLeft = parseFloat($containingBlock.css('borderLeftWidth')) || 0;
    const isRoot = containingBlock === document.documentElement;
    const scrollTop = isRoot ? 0 : containingBlock.scrollTop;
    const scrollLeft = isRoot ? 0 : containingBlock.scrollLeft;

    return {
        top: offset.top - blockOffset.top - borderTop + scrollTop,
        left: offset.left - blockOffset.left - borderLeft + scrollLeft
    };
};

class ImageCover {
    #applySizing() {
        $('.ally-image-wrapper').each(function() {
            const wrapper = this;

            // Note - we are using .attr and not .data so that we can observe what is happening to the dom elements.
            if ($(wrapper).attr('data-processed')) {
                return;
            }

            $(wrapper).attr('data-processed', 1);

            const img = $(wrapper).find('img');
            const cover = $(wrapper).find('.ally-image-cover');
            const feedback = $(wrapper).find('.ally-feedback');

            const debounceCoordsChanged = Util.debounce(function(coords) {
                const width = (coords.right - coords.left);
                const height = (coords.bottom - coords.top);
                $(cover)
                    .css('width', width + 'px')
                    .css('height', height + 'px');
                const {top: topPos, left: leftPos} = getPositionInContainingBlock($(img), $(cover)[0]);
                $(cover)
                    .css('top', topPos + 'px')
                    .css('left', leftPos + 'px');
                if (feedback.length) {
                    const feedbackPos = getPositionInContainingBlock($(img), feedback[0]);
                    feedback
                        .css('top', (feedbackPos.top + height - feedback.height()) + 'px')
                        .css('left', feedbackPos.left + 'px');
                }
            }, 1000);

            Util.onCoordsChange(img, function(coords) {
                debounceCoordsChanged(coords);
            });
        });
    }

    init() {
        $(document).ready(this.#applySizing);
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
