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
 * Ally filter context
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Ally filter context
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_filter_ally extends behat_base {
    /**
     * @Given /^the ally filter is enabled$/
     */
    public function the_ally_filter_is_enabled() {
        filter_set_global_state('ally', TEXTFILTER_ON);
    }

    /**
     * @Given /^I create a label with fixture images "(?P<images_string>[^"]*)"$/
     * @param string $images (csv)
     */
    public function i_create_label_with_sample_images($images) {
        global $CFG, $DB;

        $gen = testing_util::get_data_generator();

        $bodynode = $this->find('xpath', 'body');
        $bodyclass = $bodynode->getAttribute('class');
        $matches = [];
        if (preg_match('/(?<=^course-|\scourse-)(?:\d*)/', $bodyclass, $matches) && !empty($matches)) {
            $courseid = intval($matches[0]);
        } else {
            $courseid = SITEID;
        }
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new coding_exception('Failed to get course by id '.$courseid. ' '.$bodyclass);
        }

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $images = explode(',', $images);

        $labeltext = '<h2>A test label</h2>';

        $i = 0;
        $voidtype = '/>';

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => 'pre file inserts',
            'introformat' => FORMAT_HTML
        ];

        $label = $gen->create_module('label', $data);

        foreach ($images as $image) {
            $image = trim($image);
            $i ++;
            // Alternate the way the image tag is closed.
            $voidtype = $voidtype === '/>' ? '>' : '/>';
            $fixturepath = $fixturedir.$image;
            if (!file_exists($fixturepath)) {
                throw coding_exception('Fixture image does not exist '.$fixturepath);
            }

            // Add actual file there.
            $filerecord = ['component' => 'mod_label', 'filearea' => 'intro',
                'contextid' => context_module::instance($label->cmid)->id, 'itemid' => 0,
                'filename' => $image, 'filepath' => '/'];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
            $path = '@@PLUGINFILE@@/' . $image;
            $labeltext .= 'Some text before the image';
            $labeltext .= '<img src="' . $path . '" alt="test file ' . $i . '" ' . $voidtype;
            $labeltext .= 'Some text after the image';
        }

        $label = $DB->get_record('label', ['id' => $label->id]);
        $label->intro = $labeltext;
        $DB->update_record('label', $label);
    }

    /**
     * @Given /I create a label with random text files "(?P<files_string>[^"]*)"/
     * @param str $files (csv)
     */
    public function i_create_a_label_with_random_text_files($files) {
        global $CFG, $DB;

        $gen = testing_util::get_data_generator();

        $bodynode = $this->find('xpath', 'body');
        $bodyclass = $bodynode->getAttribute('class');
        $matches = [];
        if (preg_match('/(?<=^course-|\scourse-)(?:\d*)/', $bodyclass, $matches) && !empty($matches)) {
            $courseid = intval($matches[0]);
        } else {
            $courseid = SITEID;
        }
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            throw new coding_exception('Failed to get course by id '.$courseid. ' '.$bodyclass);
        }

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $files = explode(',', $files);

        $labeltext = '<h2>A test label</h2>';

        $i = 0;

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => 'pre file inserts',
            'introformat' => FORMAT_HTML
        ];

        $label = $gen->create_module('label', $data);

        foreach ($files as $file) {
            $file = trim($file);
            $i ++;
            // Alternate the way the image tag is closed.
            $fixturepath = $fixturedir.$file;

            // Add actual file there.
            $filerecord = ['component' => 'mod_label', 'filearea' => 'intro',
                'contextid' => context_module::instance($label->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/'];
            $fs = get_file_storage();
            $fs->create_file_from_string($filerecord, 'test file '.$i);
            $path = '@@PLUGINFILE@@/' . $file;
            $labeltext .= 'Some text before the file';
            $labeltext .= '<a href="' . $path . '">test file ' . $i . '</a>';
            $labeltext .= 'Some text after the file';
        }

        $label = $DB->get_record('label', ['id' => $label->id]);
        $label->intro = $labeltext;
        $DB->update_record('label', $label);
    }

    /**
     * @Given /^I should see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" image$/
     * @param string $imagex
     */
    public function i_should_see_feedback_for_image_x($imagex) {
        $imagex = intval($imagex);
        $path = "//span[contains(concat(' ', @class, ' '), ' ally-image-wrapper ')][$imagex]";
        $path .= "//span[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $this->execute('behat_general::assert_element_contains_text', ['FEEDBACK', $path, 'xpath_element']);
    }

    /**
     * @Given /^I should not see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" image$/
     * @param string $imagex
     */
    public function i_should_not_see_feedback_for_image_x($imagex) {
        $imagex = intval($imagex);
        $path = "//span[contains(concat(' ', @class, ' '), ' ally-image-wrapper ')][$imagex]";
        $path .= "//span[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * @Given /^the ally image cover area should exist for the "(\d*)(?:st|nd|rd|th)" image$/
     * @param string $imagex
     */
    public function the_ally_image_cover_area_should_exist_for_image_x($imagex) {
        $imagex = intval($imagex);
        $path = "//span[contains(concat(' ', @class, ' '), ' ally-image-wrapper ')][$imagex]";
        $path .= "//span[contains(concat(' ', @class, ' '), ' ally-image-cover ')]";
        $this->execute('behat_general::should_exist', [$path, 'xpath_element']);
    }

    /**
     * @Given /^I should see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" anchor$/
     * @param string $anchorx
     */
    public function i_should_see_feedback_for_anchor_x($anchorx) {
        $anchorx = intval($anchorx);
        $path = "//span[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')][$anchorx]";
        $path .= "//span[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $this->execute('behat_general::assert_element_contains_text', ['FEEDBACK', $path, 'xpath_element']);
    }

    /**
     * @Given /^I should not see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" anchor$/
     * @param string $anchorx
     */
    public function i_should_not_see_feedback_for_anchor_x($anchorx) {
        $anchorx = intval($anchorx);
        $path = "//span[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')][$anchorx]";
        $path .= "//span[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * @Given /^I should see the download place holder for the "(\d*)(?:st|nd|rd|th)" anchor$/
     * @param string $anchorx
     */
    public function i_should_see_download_for_anchor_x($anchorx) {
        $anchorx = intval($anchorx);
        $path = "//span[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')][$anchorx]";
        $path .= "//span[contains(concat(' ', @class, ' '), ' ally-download ')]";
        $this->execute('behat_general::assert_element_contains_text', ['DOWNLOAD', $path, 'xpath_element']);
    }
}
