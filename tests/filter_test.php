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
 * Test filter lib.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class filter_ally_testcase extends advanced_testcase {

    public $filter;

    public function setUp() {
        require_once(__DIR__.'/../filter.php');
        global $PAGE, $CFG;

        $PAGE->set_url($CFG->wwwroot.'/course/view.php');
        $context = context_system::instance();
        $this->filter = new filter_ally($context, []);
        $this->filter->setup($PAGE, $context);
    }

    public function test_is_course_page() {
        global $PAGE, $CFG;

        $this->resetAfterTest();

        $PAGE->set_url($CFG->wwwroot.'/course/view.php');
        $iscoursepage = phpunit_util::call_internal_method($this->filter, 'is_course_page', [], 'filter_ally');
        $this->assertTrue($iscoursepage);
        $PAGE->set_url($CFG->wwwroot.'/user/view.php');
        $iscoursepage = phpunit_util::call_internal_method($this->filter, 'is_course_page', [], 'filter_ally');
        $this->assertFalse($iscoursepage);
    }

    public function test_map_assignment_file_paths_to_pathhash() {
        global $PAGE, $CFG;

        $this->resetAfterTest();

        $gen = $this->getDataGenerator();

        $map = phpunit_util::call_internal_method(
            $this->filter, 'map_assignment_file_paths_to_pathhash', [], 'filter_ally'
        );
        $this->assertEmpty($map);

        $map = phpunit_util::call_internal_method(
            $this->filter, 'map_assignment_file_paths_to_pathhash', [], 'filter_ally'
        );
        $this->assertEmpty($map);

        $course = $gen->create_course();
        $data = (object) [
            'course' => $course->id
        ];
        $assign = $gen->create_module('assign', $data);

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $files = scandir($fixturedir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $file = trim($file);
            $fixturepath = $fixturedir.$file;

            // Add actual file there.
            $filerecord = ['component' => 'mod_assign', 'filearea' => 'introattachment',
                'contextid' => context_module::instance($assign->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/'];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
        }

        $map = phpunit_util::call_internal_method(
            $this->filter, 'map_assignment_file_paths_to_pathhash', [], 'filter_ally'
        );
        $this->assertEmpty($map);

        $PAGE->set_pagetype('mod-assign-view');
        $_GET['id'] = $assign->cmid;
        $map = phpunit_util::call_internal_method(
            $this->filter, 'map_assignment_file_paths_to_pathhash', [], 'filter_ally'
        );
        $this->assertNotEmpty($map);
    }

    public function test_map_moduleid_to_pathhash() {
        global $PAGE, $CFG;

        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();

        $map = phpunit_util::call_internal_method(
            $this->filter, 'map_moduleid_to_pathhash', [$course], 'filter_ally'
        );
        $this->assertEmpty($map);

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $files = scandir($fixturedir);

        $this->setAdminUser();

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $file = trim($file);
            $fixturepath = $fixturedir.$file;

            $data = (object) [
                'course' => $course->id,
                'name' => $file
            ];

            $resource = $gen->create_module('resource', $data);

            // Add actual file there.
            $filerecord = ['component' => 'mod_assign', 'filearea' => 'introattachment',
                'contextid' => context_module::instance($resource->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/'];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
        }

        $map = phpunit_util::call_internal_method(
            $this->filter, 'map_moduleid_to_pathhash', [$course], 'filter_ally'
        );
        $this->assertNotEmpty($map);

        $PAGE->set_url($CFG->wwwroot.'/user/view.php');
        $map = phpunit_util::call_internal_method(
            $this->filter, 'map_moduleid_to_pathhash', [$course], 'filter_ally'
        );

        $this->assertEmpty($map);
    }

    public function test_filter_img() {
        global $PAGE, $CFG;
        $this->resetAfterTest();

        $PAGE->set_url($CFG->wwwroot.'/course/view.php');

        $gen = $this->getDataGenerator();

        $course = $gen->create_course();
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'teacher');

        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => context_course::instance($course->id)->id,
            'component' => 'test',
            'filearea' => 'intro',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'test.png'
        );
        $teststring = 'moodletest';
        $fs->create_file_from_string($filerecord, $teststring);
        $path = str_replace('//', '', implode('/', $filerecord));

        $this->setUser($student);

        $text = <<<EOF
        <p>
            <span>text</span>
            写埋ルがンい未50要スぱ指6<img src="$CFG->wwwroot/pluginfile.php/$path"/>more more text
        </p>
        <img src="$CFG->wwwroot/pluginfile.php/$path">Here's that image again but void without closing tag.
EOF;
        $filteredtext = $this->filter->filter($text);
        // Make sure seizure guard image cover exists.
        $this->assertContains('<span class="ally-image-cover"', $filteredtext);
        // As we are not logged in as a teacher, we shouldn't get the feedback placeholder.
        $this->assertNotContains('<span class="ally-feedback"', $filteredtext);
        // Make sure both images were processed.
        $substr = '<span class="filter-ally-wrapper ally-image-wrapper">'.
            '<img src="'.$CFG->wwwroot.'/pluginfile.php/'.$path.'"';
        $count = substr_count($filteredtext, $substr);
        $this->assertEquals(2, $count);
        $substr = '<span class="ally-image-cover"';
        $count = substr_count($filteredtext, $substr);
        $this->assertEquals(2, $count);

        $this->setUser($teacher);
        // Make sure teachers get seizure guard and feedback place holder.
        $filteredtext = $this->filter->filter($text);
        $this->assertContains('<span class="ally-image-cover"', $filteredtext);
        // As we are logged in as a teacher, we should get the feedback placeholder.
        $this->assertContains('<span class="ally-feedback"', $filteredtext);
        // Make sure both images were processed.
        $substr = '<span class="filter-ally-wrapper ally-image-wrapper">'.
            '<img src="'.$CFG->wwwroot.'/pluginfile.php/'.$path.'"';
        $count = substr_count($filteredtext, $substr);
        $this->assertEquals(2, $count);
        $substr = '<span class="ally-image-cover"';
        $count = substr_count($filteredtext, $substr);
        $this->assertEquals(2, $count);
        $substr = '<span class="ally-feedback"';
        $count = substr_count($filteredtext, $substr);
        $this->assertEquals(2, $count);
    }

    public function test_filter_img_blacklistedcontexts() {
        global $PAGE, $CFG, $USER;
        $this->resetAfterTest();

        $this->setAdminUser();

        $PAGE->set_url($CFG->wwwroot.'/course/view.php');

        $gen = $this->getDataGenerator();

        $category = $gen->create_category();

        $blacklistedcontexts = [
            context_coursecat::instance($category->id),
            context_system::instance(),
            context_user::instance($USER->id)
        ];

        foreach ($blacklistedcontexts as $context) {
            $fs = get_file_storage();
            $filerecord = array(
                'contextid' => $context->id,
                'component' => 'test',
                'filearea' => 'intro',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'test.png'
            );
            $teststring = 'moodletest';
            $fs->create_file_from_string($filerecord, $teststring);
            $path = str_replace('//', '', implode('/', $filerecord));

            $text = <<<EOF
            <p>
                <span>text</span>
                写埋ルがンい未50要スぱ指6<img src="$CFG->wwwroot/pluginfile.php/$path"/>more more text
            </p>
            <img src="$CFG->wwwroot/pluginfile.php/$path">Here's that image again but void without closing tag.
EOF;

            // We shouldn't get anything when the contexts are blacklisted.
            $filteredtext = $this->filter->filter($text);
            $this->assertNotContains('<span class="ally-image-cover"', $filteredtext);
            $this->assertNotContains('<span class="ally-feedback"', $filteredtext);
            $substr = '<span class="filter-ally-wrapper ally-image-wrapper">' .
                '<img src="' . $CFG->wwwroot . '/pluginfile.php/' . $path . '"';
            $this->assertNotContains($substr, $filteredtext);
            $substr = '<span class="ally-image-cover"';
            $this->assertNotContains($substr, $filteredtext);
            $substr = '<span class="ally-feedback"';
            $this->assertNotContains($substr, $filteredtext);
        }
    }

    public function test_filter_anchor() {
        global $CFG;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();

        $course = $gen->create_course();
        $student = $gen->create_user();
        $teacher = $gen->create_user();
        $gen->enrol_user($student->id, $course->id, 'student');
        $gen->enrol_user($teacher->id, $course->id, 'teacher');

        $fs = get_file_storage();
        $filerecord = array(
            'contextid' => context_course::instance($course->id)->id,
            'component' => 'test',
            'filearea' => 'intro',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'test.txt'
        );
        $teststring = 'moodletest';
        $fs->create_file_from_string($filerecord, $teststring);
        $path = str_replace('//', '', implode('/', $filerecord));

        $this->setUser($student);

        $text = <<<EOF
        <p>
            <span>text</span>
            写埋ルがンい未50要スぱ指6<a href="$CFG->wwwroot/pluginfile.php/$path">HI THERE</a>more more text
        </p>
        <a href="$CFG->wwwroot/pluginfile.php/$path">Here's that anchor again.</a>Boo!
EOF;
        $filteredtext = $this->filter->filter($text);
        // Make sure student gets download palceholder.
        $this->assertContains('<span class="ally-download"', $filteredtext);
        // As we are not logged in as a teacher, we shouldn't get the feedback placeholder.
        $this->assertNotContains('<span class="ally-feedback"', $filteredtext);
        // Make sure both anchors were processed.
        $substr = '<span class="filter-ally-wrapper ally-anchor-wrapper">'.
            '<a href="'.$CFG->wwwroot.'/pluginfile.php/'.$path.'"';
        $count = substr_count($filteredtext, $substr);
        $this->assertEquals(2, $count);

        $this->setUser($teacher);
        // Make sure teachers get download and feedback place holder.
        $filteredtext = $this->filter->filter($text);
        $this->assertContains('<span class="ally-download"', $filteredtext);
        // As we are logged in as a teacher, we should get the feedback placeholder.
        $this->assertContains('<span class="ally-feedback"', $filteredtext);
        // Make sure both anchors were processed.
        $substr = '<span class="filter-ally-wrapper ally-anchor-wrapper">'.
            '<a href="'.$CFG->wwwroot.'/pluginfile.php/'.$path.'"';
        $count = substr_count($filteredtext, $substr);
        $this->assertEquals(2, $count);
    }

    public function test_filter_anchor_blacklistedcontexts() {
        global $PAGE, $CFG, $USER;
        $this->resetAfterTest();

        $this->setAdminUser();

        $PAGE->set_url($CFG->wwwroot.'/course/view.php');

        $gen = $this->getDataGenerator();

        $category = $gen->create_category();

        $blacklistedcontexts = [
            context_coursecat::instance($category->id),
            context_system::instance(),
            context_user::instance($USER->id)
        ];

        foreach ($blacklistedcontexts as $context) {
            $fs = get_file_storage();
            $filerecord = array(
                'contextid' => $context->id,
                'component' => 'test',
                'filearea' => 'intro',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'test.txt'
            );
            $teststring = 'moodletest';
            $fs->create_file_from_string($filerecord, $teststring);
            $path = str_replace('//', '', implode('/', $filerecord));

            $text = <<<EOF
            <p>
                <span>text</span>
                写埋ルがンい未50要スぱ指6<a href="$CFG->wwwroot/pluginfile.php/$path">HI THERE</a>more more text
            </p>
            <a href="$CFG->wwwroot/pluginfile.php/$path">Here's that anchor again.</a>Boo!
EOF;
            // We shouldn't get anything when contexts were blacklisted.
            $filteredtext = $this->filter->filter($text);
            $this->assertNotContains('<span class="ally-download"', $filteredtext);
            $this->assertNotContains('<span class="ally-feedback"', $filteredtext);
            // Make sure both anchors were processed.
            $substr = '<span class="filter-ally-wrapper ally-anchor-wrapper">'.
                '<a href="'.$CFG->wwwroot.'/pluginfile.php/'.$path.'"';
            $this->assertNotContains($substr, $filteredtext);
        }
    }
}
