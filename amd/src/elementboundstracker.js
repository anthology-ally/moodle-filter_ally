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
 * Track element bounds changes in a shared polling loop.
 *
 * @package
 * @author    Guy Thomas
 * @copyright Copyright (c) 2026 Open LMS / Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Util from 'filter_ally/util';

class ElementBoundsTracker {
    constructor(fullBoundsCheckLoop = true) {
        this.intervalHandle = null;
        this.fullBoundsCheckLoop = fullBoundsCheckLoop;
        this.intervalMs = 10000; // Every ten seconds for full bounds check.
        this.registry = new Set();
        this.stateByElement = new WeakMap();
        this.resizeObserver = null;
        this.documentResizeObserver = null;
        this.documentResizeObservedElement = null;
        this.documentResizeRafId = null;
        this.visibilityRafId = null;
        this.boundScheduleEvaluateVisibleElements = this.scheduleEvaluateVisibleElements.bind(this);
        this.boundHandleFocusIn = this.handleFocusIn.bind(this);
        this.boundHandlePointerEnter = this.handlePointerEnter.bind(this);
        this.testMode = document.body.classList.contains('ally-test-mode');

        // Allow visibility markers to be ready as soon as elements are tracked.
        this.evaluateVisibleElements();
    }

    /**
     * Register an element to track coordinate changes for.
     * @param {jQuery|Element} $el
     * @param {Function|null} callback
     * @param {jQuery|Element|null} mirrorElement Optional element to mirror tracked bounds to.
     * @param {boolean} mirrorRelativeToContainingBlock Whether mirror position should be relative to the mirror's containing block.
     */
    register($el, callback = null, mirrorElement = null, mirrorRelativeToContainingBlock = true) {
        if (typeof callback !== 'function' && mirrorElement === null) {
            throw new Error('Either a valid callback or a mirror element must be provided.');
        }
        const element = this.getElement($el);
        const mirror = this.getElement(mirrorElement);
        if (!element) {
            return;
        }

        const existingState = this.stateByElement.get(element);
        if (existingState) {
            const mirrorConfigChanged = existingState.mirrorElement !== mirror ||
                existingState.mirrorRelativeToContainingBlock !== mirrorRelativeToContainingBlock;

            existingState.callback = callback;
            existingState.mirrorElement = mirror;
            existingState.mirrorRelativeToContainingBlock = mirrorRelativeToContainingBlock;

            if (mirrorConfigChanged) {
                existingState.throttledMirrorSync = this.createThrottledMirrorSync(
                    mirror,
                    mirrorRelativeToContainingBlock
                );
            }

            this.observeElementResize(element);
            this.start();
            this.evaluateVisibleElements();
            this.checkElement(element);
            return;
        }

        this.registry.add(element);
        this.stateByElement.set(element, {
            callback,
            mirrorElement: mirror,
            mirrorRelativeToContainingBlock,
            throttledMirrorSync: this.createThrottledMirrorSync(mirror, mirrorRelativeToContainingBlock),
            lastCoords: null,
        });

        this.observeElementResize(element);
        this.start();
        this.evaluateVisibleElements();
        this.checkElement(element);
    }

    /**
     * Unregister an element from tracking.
     * @param {jQuery|Element} $el
     */
    unregister($el) {
        const element = this.getElement($el);
        if (!element) {
            return;
        }

        this.unobserveElementResize(element);
        this.registry.delete(element);
        this.stateByElement.delete(element);
        this.setVisibilityClasses(element, false);

        if (!this.registry.size) {
            this.stop();
        }
    }

    /**
     * Start shared tracking interval.
     */
    start() {
        if (this.intervalHandle) {
            return;
        }

        this.observeDocumentResize();
        this.observeVisibilityRelatedEvents();

        if (this.fullBoundsCheckLoop) {
            this.intervalHandle = setInterval(() => {
                this.loop();
            }, this.intervalMs);
        } else {
            this.loop(); // Only loop once.
        }
    }

    /**
     * Stop shared tracking interval.
     */
    stop() {
        if (this.documentResizeRafId !== null) {
            cancelAnimationFrame(this.documentResizeRafId);
            this.documentResizeRafId = null;
        }

        if (this.visibilityRafId !== null) {
            cancelAnimationFrame(this.visibilityRafId);
            this.visibilityRafId = null;
        }

        if (!this.intervalHandle) {
            this.maybeDisconnectResizeObserver();
            this.maybeDisconnectDocumentResizeObserver();
            this.unobserveVisibilityRelatedEvents();
            return;
        }

        clearInterval(this.intervalHandle);
        this.intervalHandle = null;
        this.maybeDisconnectResizeObserver();
        this.maybeDisconnectDocumentResizeObserver();
        this.unobserveVisibilityRelatedEvents();
    }

    /**
     * Iterate all tracked elements and invoke callbacks when bounds change.
     */
    loop() {
        this.registry.forEach((element) => {
            if (!this.stateByElement.has(element)) {
                this.registry.delete(element);
                return;
            }

            this.checkElement(element);
        });

        if (!this.registry.size) {
            this.stop();
        }
    }

    /**
     * Check one element and notify if bounds changed, or resync mirror if it drifted.
     * @param {Element} element
     */
    checkElement(element) {
        const state = this.stateByElement.get(element);
        if (!state) {
            this.unobserveElementResize(element);
            this.registry.delete(element);
            return;
        }

        if (!$.contains(document.documentElement, element)) {
            this.unobserveElementResize(element);
            this.registry.delete(element);
            this.stateByElement.delete(element);
            return;
        }

        const $element = $(element);
        const currCoords = this.getCoords($element);

        if (!currCoords) {
            return;
        }

        const coordsChanged = this.hasCoordsChanged(state.lastCoords, currCoords);
        const mirrorOutOfSync = this.isMirrorOutOfSync(
            state.mirrorElement,
            currCoords,
            state.mirrorRelativeToContainingBlock
        );

        if (!coordsChanged && !mirrorOutOfSync) {
            return;
        }

        if (coordsChanged) {
            state.lastCoords = currCoords;
        }

        if (state.throttledMirrorSync) {
            state.throttledMirrorSync(currCoords);
        }

        if (coordsChanged && typeof state.callback === 'function') {
            state.callback(currCoords);
        }
    }

    /**
     * Create a per-element throttled mirror sync function (leading + trailing).
     * @param {Element|null} mirrorElement
     * @param {boolean} mirrorRelativeToContainingBlock
     * @returns {Function|null}
     */
    createThrottledMirrorSync(mirrorElement, mirrorRelativeToContainingBlock) {
        if (!mirrorElement) {
            return null;
        }

        return Util.throttle((coords) => {
            this.syncMirrorElementBounds(mirrorElement, coords, mirrorRelativeToContainingBlock);
        }, 500);
    }

    /**
     * Lazily initialize the shared ResizeObserver.
     */
    ensureResizeObserver() {
        if (this.resizeObserver || typeof ResizeObserver === 'undefined') {
            return;
        }

        this.resizeObserver = new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                const element = entry.target;
                if (!this.registry.has(element)) {
                    return;
                }

                this.checkElement(element);
            });
        });
    }

    /**
     * Observe element size changes when ResizeObserver is available.
     * @param {Element} element
     */
    observeElementResize(element) {
        this.ensureResizeObserver();
        if (!this.resizeObserver) {
            return;
        }

        this.resizeObserver.observe(element);
    }

    /**
     * Stop observing element size changes.
     * @param {Element} element
     */
    unobserveElementResize(element) {
        if (!this.resizeObserver) {
            return;
        }

        this.resizeObserver.unobserve(element);
        this.maybeDisconnectResizeObserver();
    }

    /**
     * Disconnect shared ResizeObserver when no tracked elements remain.
     */
    maybeDisconnectResizeObserver() {
        if (this.registry.size || !this.resizeObserver) {
            return;
        }

        this.resizeObserver.disconnect();
        this.resizeObserver = null;
    }

    /**
     * Lazily initialize the document-level ResizeObserver.
     */
    ensureDocumentResizeObserver() {
        if (this.documentResizeObserver || typeof ResizeObserver === 'undefined') {
            return;
        }

        this.documentResizeObserver = new ResizeObserver(() => {
            this.scheduleCheckAll();
        });
    }

    /**
     * Observe document-level resize changes.
     */
    observeDocumentResize() {
        this.ensureDocumentResizeObserver();
        if (!this.documentResizeObserver) {
            return;
        }

        const element = document.documentElement || document.body;
        if (!element || this.documentResizeObservedElement === element) {
            return;
        }

        if (this.documentResizeObservedElement) {
            this.documentResizeObserver.unobserve(this.documentResizeObservedElement);
        }

        this.documentResizeObserver.observe(element);
        this.documentResizeObservedElement = element;
    }

    /**
     * Disconnect document-level resize observer when no tracked elements remain.
     */
    maybeDisconnectDocumentResizeObserver() {
        if (this.registry.size || !this.documentResizeObserver) {
            return;
        }

        if (this.documentResizeObservedElement) {
            this.documentResizeObserver.unobserve(this.documentResizeObservedElement);
            this.documentResizeObservedElement = null;
        }

        this.documentResizeObserver.disconnect();
        this.documentResizeObserver = null;
    }

    /**
     * Batch a full check pass into one animation frame.
     */
    scheduleCheckAll() {
        if (this.documentResizeRafId !== null) {
            return;
        }

        this.documentResizeRafId = requestAnimationFrame(() => {
            this.documentResizeRafId = null;
            this.loop();
        });
    }

    /**
     * Schedule a visibility evaluation in one animation frame.
     */
    scheduleEvaluateVisibleElements() {
        if (this.visibilityRafId !== null) {
            return;
        }

        this.visibilityRafId = requestAnimationFrame(() => {
            this.visibilityRafId = null;
            this.evaluateVisibleElements();
        });
    }

    /**
     * Evaluate tracked element visibility, update debug classes and sync visible elements.
     */
    evaluateVisibleElements() {
        this.registry.forEach((element) => {
            if (!this.stateByElement.has(element)) {
                this.registry.delete(element);
                this.setVisibilityClasses(element, false);
                return;
            }

            if (!$.contains(document.documentElement, element)) {
                this.unobserveElementResize(element);
                this.registry.delete(element);
                this.stateByElement.delete(element);
                this.setVisibilityClasses(element, false);
                return;
            }

            const isVisible = this.isVisibleOrHalfOutOfBounds(element);
            this.setVisibilityClasses(element, isVisible);

            if (isVisible) {
                this.checkElement(element);
            }
        });
    }

    /**
     * Determine whether an element is visible in viewport or up to half out of viewport bounds.
     * @param {Element} element
     * @returns {boolean}
     */
    isVisibleOrHalfOutOfBounds(element) {
        const rect = element.getBoundingClientRect();
        const width = rect.width;
        const height = rect.height;
        if (!width || !height) {
            return false;
        }

        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const horizontalBuffer = width / 2;
        const verticalBuffer = height / 2;

        return rect.right >= -horizontalBuffer &&
            rect.left <= viewportWidth + horizontalBuffer &&
            rect.bottom >= -verticalBuffer &&
            rect.top <= viewportHeight + verticalBuffer;
    }

    /**
     * In test mode, set visibility debug classes on tracked elements.
     * @param {Element} element
     * @param {boolean} isVisible
     */
    setVisibilityClasses(element, isVisible) {
        if (!this.testMode) {
            return;
        }
        if (!element || !element.classList) {
            return;
        }

        element.classList.toggle('ally-el-visible', isVisible);
        element.classList.toggle('ally-el-not-visible', !isVisible);
    }

    /**
     * Observe visibility-related events.
     */
    observeVisibilityRelatedEvents() {
        document.addEventListener('focusin', this.boundHandleFocusIn, true);
        document.addEventListener('pointerenter', this.boundHandlePointerEnter, true);
        document.addEventListener('scroll', this.boundScheduleEvaluateVisibleElements, true);
        window.addEventListener('resize', this.boundScheduleEvaluateVisibleElements);
    }

    /**
     * Stop observing visibility-related events.
     */
    unobserveVisibilityRelatedEvents() {
        document.removeEventListener('focusin', this.boundHandleFocusIn, true);
        document.removeEventListener('pointerenter', this.boundHandlePointerEnter, true);
        document.removeEventListener('scroll', this.boundScheduleEvaluateVisibleElements, true);
        window.removeEventListener('resize', this.boundScheduleEvaluateVisibleElements);
    }

    /**
     * On focus, immediately re-evaluate and sync tracked element visibility.
     * @param {FocusEvent} event
     */
    handleFocusIn(event) {
        const target = event.target;
        if (!target || target.nodeType !== 1) {
            return;
        }

        const tracked = this.findTrackedElementFromTarget(target);
        if (!tracked) {
            return;
        }

        this.setVisibilityClasses(tracked, true);
        this.checkElement(tracked);
    }

    /**
     * On pointer enter, immediately re-evaluate and sync tracked element visibility.
     * @param {PointerEvent} event
     */
    handlePointerEnter(event) {
        const target = event.target;
        if (!target || target.nodeType !== 1) {
            return;
        }

        const tracked = this.findTrackedElementFromTarget(target);
        if (!tracked) {
            return;
        }

        this.setVisibilityClasses(tracked, true);
        this.checkElement(tracked);
    }

    /**
     * Resolve a tracked element from an event target by direct match or containment.
     * @param {Element} target
     * @returns {Element|null}
     */
    findTrackedElementFromTarget(target) {
        if (this.registry.has(target)) {
            return target;
        }

        let found = null;
        this.registry.forEach((element) => {
            if (!found && element.contains(target)) {
                found = element;
            }
        });

        return found;
    }

    /**
     * Update mirror element bounds to match tracked element coordinates.
     * @param {Element|null} mirrorElement
     * @param {Object} coords
     * @param {boolean} mirrorRelativeToContainingBlock
     */
    syncMirrorElementBounds(mirrorElement, coords, mirrorRelativeToContainingBlock) {
        if (!mirrorElement) {
            return;
        }

        const mirrorPosition = this.calculateMirroredPosition(
            mirrorElement,
            coords,
            mirrorRelativeToContainingBlock
        );

        $(mirrorElement)
            .css('left', mirrorPosition.left + 'px')
            .css('top', mirrorPosition.top + 'px')
            .css('width', mirrorPosition.width + 'px')
            .css('height', mirrorPosition.height + 'px');
    }

    /**
     * Whether an element with the given computed style is the containing block of its absolutely positioned
     * descendants: it is positioned, or it establishes one through a transform (including the individual translate,
     * rotate and scale properties), perspective, filter, containment (including content-visibility) or
     * will-change. An element that generates no box (display: contents) never is one, and transforms, perspective and
     * containment do not apply to a non-replaced inline box, which only is one when positioned or filtered.
     * @param {CSSStyleDeclaration} style
     * @returns {boolean}
     */
    createsContainingBlock(style) {
        if (style.display === 'contents') {
            return false;
        }

        const isSet = (value) => value !== undefined && value !== '' && value !== 'none';
        const willChange = (style.willChange || '').split(',').map((name) => name.trim());
        const isInline = style.display === 'inline';

        return style.position !== 'static' ||
            isSet(style.filter) ||
            isSet(style.backdropFilter) ||
            willChange.includes('filter') ||
            (!isInline && (
                isSet(style.transform) ||
                isSet(style.translate) ||
                isSet(style.rotate) ||
                isSet(style.scale) ||
                isSet(style.perspective) ||
                /\b(layout|paint|strict|content)\b/.test(style.contain || '') ||
                /^(auto|hidden)$/.test(style.contentVisibility || '') ||
                willChange.some((name) => ['transform', 'translate', 'rotate', 'scale', 'perspective'].includes(name))
            ));
    }

    /**
     * Find the ancestor that is the containing block of an absolutely positioned element.
     *
     * This is not the same as the offsetParent of the tracked source element: a static td, th or table is the
     * offsetParent of an element inside a table cell, but it never is the containing block of an absolutely
     * positioned element. The root element is a candidate too, as it is the containing block when it is
     * positioned. Returns null when the containing block is the initial one (page origin).
     * @param {Element} element
     * @returns {Element|null}
     */
    getContainingBlock(element) {
        let ancestor = element.parentElement;
        while (ancestor) {
            if (this.createsContainingBlock(window.getComputedStyle(ancestor))) {
                return ancestor;
            }
            ancestor = ancestor.parentElement;
        }

        return null;
    }

    /**
     * Calculate the expected mirror position and size.
     * @param {Element} mirrorElement
     * @param {Object} coords
     * @param {boolean} mirrorRelativeToContainingBlock Whether the position is relative to the mirror's containing block
     *                                          instead of the page.
     * @returns {Object}
     */
    calculateMirroredPosition(mirrorElement, coords, mirrorRelativeToContainingBlock) {
        const width = (coords.right - coords.left);
        const height = (coords.bottom - coords.top);
        let top = coords.top;
        let left = coords.left;

        if (mirrorRelativeToContainingBlock) {
            const containingBlock = this.getContainingBlock(mirrorElement);
            if (containingBlock) {
                const $containingBlock = $(containingBlock);
                const blockOffset = $containingBlock.offset();
                const borderTop = parseFloat($containingBlock.css('borderTopWidth')) || 0;
                const borderLeft = parseFloat($containingBlock.css('borderLeftWidth')) || 0;
                // The offset is already relative to the document, so the scroll position of the root is not added.
                const isRoot = containingBlock === document.documentElement;
                top = coords.top - blockOffset.top - borderTop + (isRoot ? 0 : containingBlock.scrollTop);
                left = coords.left - blockOffset.left - borderLeft + (isRoot ? 0 : containingBlock.scrollLeft);
            }
        }

        return {left, top, width, height};
    }

    /**
     * Check whether mirror element differs from expected source-aligned bounds.
     * @param {Element|null} mirrorElement
     * @param {Object} coords
     * @param {boolean} mirrorRelativeToContainingBlock
     * @returns {boolean}
     */
    isMirrorOutOfSync(mirrorElement, coords, mirrorRelativeToContainingBlock) {
        if (!mirrorElement) {
            return false;
        }

        const expected = this.calculateMirroredPosition(mirrorElement, coords, mirrorRelativeToContainingBlock);
        const actualLeft = parseFloat($(mirrorElement).css('left'));
        const actualTop = parseFloat($(mirrorElement).css('top'));
        const actualWidth = parseFloat($(mirrorElement).css('width'));
        const actualHeight = parseFloat($(mirrorElement).css('height'));

        if (Number.isNaN(actualLeft) || Number.isNaN(actualTop) || Number.isNaN(actualWidth) || Number.isNaN(actualHeight)) {
            return true;
        }

        return Math.abs(actualLeft - expected.left) > 0.5 ||
            Math.abs(actualTop - expected.top) > 0.5 ||
            Math.abs(actualWidth - expected.width) > 0.5 ||
            Math.abs(actualHeight - expected.height) > 0.5;
    }

    /**
     * Normalize jQuery/Element input into a DOM element.
     * @param {jQuery|Element} $el
     * @returns {Element|null}
     */
    getElement($el) {
        if (!$el) {
            return null;
        }

        if ($el.jquery) {
            return $el[0] || null;
        }

        if ($el.nodeType === 1) {
            return $el;
        }

        return null;
    }

    /**
     * Compute top/right/bottom/left bounds for a tracked element.
     * @param {jQuery} $el
     * @returns {Object|null}
     */
    getCoords($el) {
        const offset = $el.offset();
        if (!offset) {
            return null;
        }

        const width = $el.width();
        const height = $el.height();

        return {
            'top': offset.top,
            'right': offset.left + width,
            'bottom': offset.top + height,
            'left': offset.left
        };
    }

    /**
     * Compare last and current bounds.
     * @param {Object|null} lastCoords
     * @param {Object} currCoords
     * @returns {boolean}
     */
    hasCoordsChanged(lastCoords, currCoords) {
        if (!lastCoords) {
            return true;
        }

        return currCoords.top !== lastCoords.top || currCoords.right !== lastCoords.right ||
            currCoords.bottom !== lastCoords.bottom || currCoords.left !== lastCoords.left;
    }
}

export default ElementBoundsTracker;