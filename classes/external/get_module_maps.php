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
 * External API for getting module maps.
 *
 * @package    filter_ally
 * @category   external
 * @author     Guy Thomas
 * @copyright  Copyright (c) 2017 Open LMS / 2025 Anthology Inc. and its affiliates
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_ally\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use context_course;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use filter_ally\local\entity_mapper;
use invalid_parameter_exception;
use moodle_exception;

/**
 * External API for getting module maps.
 *
 * @package    filter_ally
 * @category   external
 * @author     Guy Thomas
 * @copyright  Copyright (c) 2017 Open LMS / 2025 Anthology Inc. and its affiliates
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_module_maps extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get module maps for the specified course.
     *
     * @param int $courseid The course ID
     * @return array Module maps data
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function execute(int $courseid): array {
        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);

        // Validate context and capabilities.
        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        // Check if user has the required capability.
        require_capability('filter/ally:viewfeedback', $context);

        try {
            // Create entity mapper instance with courseid and get maps.
            $entitymapper = new entity_mapper($params['courseid']);
            $maps = $entitymapper->get_maps();

            return [
                'modulemaps' => self::format_module_maps($maps->modulemaps),
                'sectionmaps' => self::format_section_maps($maps->sectionmaps),
                'annotationmaps' => json_encode($maps->annotationmaps),
                'success' => true,
                'message' => '',
            ];

        } catch (\Exception $e) {
            return [
                'modulemaps' => [],
                'sectionmaps' => [],
                'annotationmaps' => [],
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format module maps for external API response.
     *
     * @param array $modulemaps Raw module maps
     * @return array Formatted module maps
     */
    private static function format_module_maps(array $modulemaps): array {
        $formatted = [];

        foreach ($modulemaps as $maptype => $mapdata) {
            $formatted[] = [
                'maptype' => $maptype,
                'mapdata' => json_encode($mapdata),
            ];
        }

        return $formatted;
    }

    /**
     * Format section maps for external API response.
     *
     * @param array $sectionmaps Raw section maps
     * @return array Formatted section maps
     */
    private static function format_section_maps(array $sectionmaps): array {
        $formatted = [];

        foreach ($sectionmaps as $sectionkey => $sectionid) {
            $formatted[] = [
                'sectionkey' => $sectionkey,
                'sectionid' => $sectionid,
            ];
        }

        return $formatted;
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'modulemaps' => new external_multiple_structure(
                new external_single_structure([
                    'maptype' => new external_value(PARAM_TEXT, 'Type of module map'),
                    'mapdata' => new external_value(PARAM_RAW, 'JSON encoded map data'),
                ]), 'Module maps data'
            ),
            'sectionmaps' => new external_multiple_structure(
                new external_single_structure([
                    'sectionkey' => new external_value(PARAM_TEXT, 'Section key identifier'),
                    'sectionid' => new external_value(PARAM_INT, 'Section ID'),
                ]), 'Section maps data'
            ),
            'annotationmaps' => new external_value(PARAM_RAW, 'JSON encoded annotation maps'),
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message or error message'),
        ]);
    }
}
