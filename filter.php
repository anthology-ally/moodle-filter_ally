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

/**
 * Filter for processing file links for Ally accessibility enhancements.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @package   filter_ally
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

/**
 * Filter for processing file links for Ally accessibility enhancements.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @package   filter_ally
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_ally extends moodle_text_filter {

    /**
     * Set up the filter using settings provided in the admin settings page.
     *
     * @param $page
     * @param $context
     */
    public function setup($page, $context) {
        // This only requires execution once per request.
        static $jsinitialised = false;
        if (!$jsinitialised) {
            // Add code to include AMD module here.
            $jsinitialised = true;
        }
    }

    /**
     * Filters the given HTML text, looking for links pointing to files so that the file id data attribute can
     * be injected.
     *
     * @param $text HTML to be processed.
     * @param $options
     * @return string String containing processed HTML.
     */
    public function filter($text, array $options = array()) {
       return $text;
    }
}
