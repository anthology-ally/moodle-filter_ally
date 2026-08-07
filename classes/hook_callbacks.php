<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace filter_ally;

use core\hook\output\before_http_headers;

/**
 * Hook callbacks for filter_ally.
 *
 * @package   filter_ally
 * @copyright Copyright (c) 2026 Open LMS / Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Conditionally add ally test mode class before output starts.
     * This is used to enable test mode so that developers can see the Ally feedback UI in action
     * without having to actually have Ally enabled for their site.
     *
     * @param before_http_headers $hook
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;

        if (!self::is_ally_test_mode_enabled()) {
            return;
        }

        if ($PAGE->state !== \moodle_page::STATE_BEFORE_HEADER) {
            return;
        }

        $PAGE->add_body_class('ally-test-mode');
    }

    /**
     * Determine whether ally test mode query parameter is truthy.
     *
     * @return bool
     */
    private static function is_ally_test_mode_enabled(): bool {
        $value = optional_param('allytestmode', '', PARAM_ALPHANUM);

        if (trim($value) === '') {
            return false;
        }

        $normalised = strtolower((string)$value);
        return in_array($normalised, ['1', 'true', 'yes', 'on'], true);
    }
}
