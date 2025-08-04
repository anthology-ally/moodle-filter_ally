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
 * Unit tests for the get_module_maps external API.
 *
 * @package    filter_ally
 * @category   test
 * @author     Guy Thomas
 * @copyright  Copyright (c) 2017 Open LMS / 2025 Anthology Inc. and its affiliates
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_ally\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use externallib_advanced_testcase;

/**
 * Unit tests for the get_module_maps external API.
 *
 * @package    filter_ally
 * @category   test
 * @author     Guy Thomas
 * @copyright  Copyright (c) 2017 Open LMS / 2025 Anthology Inc. and its affiliates
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @group     text_filter
 * @group     ally
 */
final class get_module_maps_test extends externallib_advanced_testcase {

    /**
     * Test the get_module_maps web service.
     */
    public function test_get_module_maps() {
        $this->resetAfterTest(true);

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a user and enrol them in the course.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        // Set the user.
        $this->setUser($user);

        // Test the web service.
        $result = get_module_maps::execute($course->id);

        // Validate the response structure.
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('modulemaps', $result);
        $this->assertArrayHasKey('sectionmaps', $result);
        $this->assertArrayHasKey('annotationmaps', $result);

        // Check that it was successful.
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['message']);

        // Validate modulemaps structure.
        $this->assertIsArray($result['modulemaps']);
        foreach ($result['modulemaps'] as $modulemap) {
            $this->assertArrayHasKey('maptype', $modulemap);
            $this->assertArrayHasKey('mapdata', $modulemap);
            $this->assertIsString($modulemap['maptype']);
            $this->assertIsString($modulemap['mapdata']);

            // Validate that mapdata is valid JSON.
            $decoded = json_decode($modulemap['mapdata'], true);
            $this->assertNotNull($decoded, 'Map data should be valid JSON');
        }

        // Validate sectionmaps structure.
        $this->assertIsArray($result['sectionmaps']);
        foreach ($result['sectionmaps'] as $sectionmap) {
            $this->assertArrayHasKey('sectionkey', $sectionmap);
            $this->assertArrayHasKey('sectionid', $sectionmap);
            $this->assertIsString($sectionmap['sectionkey']);
            $this->assertIsInt($sectionmap['sectionid']);
        }

        // Validate annotationmaps is a string (JSON).
        $this->assertIsString($result['annotationmaps']);

        // Validate that annotationmaps is valid JSON.
        $annotations = json_decode($result['annotationmaps'], true);
        $this->assertNotNull($annotations, 'Annotation maps should be valid JSON');
    }

    /**
     * Test get_module_maps with invalid course ID.
     */
    public function test_get_module_maps_invalid_course() {
        $this->resetAfterTest(true);

        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        // Test with non-existent course ID.
        $this->expectException(\dml_missing_record_exception::class);
        get_module_maps::execute(99999);
    }

    /**
     * Test get_module_maps without required capability.
     */
    public function test_get_module_maps_no_capability() {
        $this->resetAfterTest(true);

        // Create a course.
        $course = $this->getDataGenerator()->create_course();

        // Create a user without the required capability.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        // Set the user.
        $this->setUser($user);

        // Test the web service - should throw an exception.
        $this->expectException(\required_capability_exception::class);
        get_module_maps::execute($course->id);
    }

    /**
     * Test the web service return structure.
     */
    public function test_get_module_maps_returns() {
        // Test that the return structure is correctly defined.
        $returns = get_module_maps::execute_returns();
        $this->assertInstanceOf(\external_single_structure::class, $returns);

        // Test the structure keys.
        $keys = $returns->keys;
        $this->assertArrayHasKey('success', $keys);
        $this->assertArrayHasKey('message', $keys);
        $this->assertArrayHasKey('modulemaps', $keys);
        $this->assertArrayHasKey('sectionmaps', $keys);
        $this->assertArrayHasKey('annotationmaps', $keys);
    }

    /**
     * Test the web service parameters.
     */
    public function test_get_module_maps_parameters() {
        // Test that the parameters are correctly defined.
        $params = get_module_maps::execute_parameters();
        $this->assertInstanceOf(\external_function_parameters::class, $params);

        // Test the parameter keys.
        $keys = $params->keys;
        $this->assertArrayHasKey('courseid', $keys);
        $this->assertEquals(PARAM_INT, $keys['courseid']->type);
    }
}
