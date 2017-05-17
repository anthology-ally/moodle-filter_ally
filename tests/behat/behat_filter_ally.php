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
     * Get current course;
     * @return stdClass | false
     * @throws \Behat\Mink\Exception\ExpectationException
     * @throws coding_exception
     */
    protected function get_current_course() {
        global $DB;

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
        return ($course);
    }

    /**
     * @Given /^I create a label with fixture images "(?P<images_string>[^"]*)"$/
     * @param string $images (csv)
     */
    public function i_create_label_with_sample_images($images) {
        global $CFG, $DB;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $images = explode(',', $images);

        $labeltext = '<h2>A test label</h2>';

        $voidtype = '/>';

        $course = $this->get_current_course();

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => 'pre file inserts',
            'introformat' => FORMAT_HTML
        ];

        $label = $gen->create_module('label', $data);

        $i = 0;
        foreach ($images as $image) {
            $image = trim($image);
            $i ++;
            // Alternate the way the image tag is closed.
            $voidtype = $voidtype === '/>' ? '>' : '/>';
            $fixturepath = $fixturedir.$image;
            if (!file_exists($fixturepath)) {
                throw new coding_exception('Fixture image does not exist '.$fixturepath);
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

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $files = explode(',', $files);

        $labeltext = '<h2>A test label</h2>';

        $course = $this->get_current_course();

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => 'pre file inserts',
            'introformat' => FORMAT_HTML
        ];

        $label = $gen->create_module('label', $data);

        $i = 0;
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
     * @Given /^I create file resources using fixtures "(?P<fixtures_string>[^"]*)"/
     * @param string $fixtures
     */
    public function i_create_file_resources_using_fixtures($fixtures) {
        global $CFG;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $files = explode(',', $fixtures);

        $course = $this->get_current_course();

        foreach ($files as $file) {
            $file = trim($file);
            $fixturepath = $fixturedir.$file;
            if (!file_exists($fixturepath)) {
                throw new coding_exception('Fixture file does not exist '.$fixturepath);
            }

            $data = (object) [
                'course' => $course->id,
                'name' => $file
            ];

            $resource = $gen->create_module('resource', $data);

            // Add actual file there.
            $filerecord = ['component' => 'mod_label', 'filearea' => 'intro',
                'contextid' => context_module::instance($resource->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/'];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
        }
    }

    /**
     * @Given /^I create assignment "(?P<name_string>[^"]*)" with additional file fixtures "(?P<fixtures_string>[^"]*)"/
     * @param $assignname
     * @param $fixtures
     */
    public function i_create_assign_with_additional_files($assignname, $fixtures) {
        global $CFG;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot.'/filter/ally/tests/fixtures/';
        $files = explode(',', $fixtures);

        $course = $this->get_current_course();

        $assigngen = $gen->get_plugin_generator('mod_assign');

        $data = [
            'course' => $course->id,
            'name' => $assignname,
            'submissiondrafts' => 0,
            'assignsubmission_file_enabled' => 1,
            'assignsubmission_file_maxfiles' => 12,
            'assignsubmission_file_maxsizebytes' => 10000,
            'assignsubmission_onlinetext_enabled' => 1
        ];

        $assign = $assigngen->create_instance($data);

        foreach ($files as $file) {
            $file = trim($file);
            $fixturepath = $fixturedir.$file;

            // Add actual file there.
            $filerecord = ['component' => 'mod_assign', 'filearea' => 'introattachment',
                'contextid' => context_module::instance($assign->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/'];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
        }
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
     * Get xpath for specific anchor and type.
     * @param string $anchorx
     * @param string $phclass place hodler class.
     * @param string $type
     * @return string
     * @throws coding_exception
     */
    protected function get_placeholder_xpath($anchorx, $phclass, $type) {
        $anchorx = intval($anchorx);
        if ($type === 'anchor') {
            $path = "//span[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')][$anchorx]";
        } else if ($type === 'file resource') {
            $path = "//li[contains(concat(' ', @class, ' '), ' modtype_resource ')][$anchorx]";
            $path .= "//span[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')]";
        } else if ($type === 'assignment file') {
            $path = "//div[contains(@id, 'assign_files_tree')]//div[contains(concat(' ', @class, ' '), ' ygtvchildren ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ygtvitem ')][$anchorx]";
            $path .= "//span[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')]";
        } else {
            throw new coding_exception('Unknown feedback container type: '.$type);
        }
        $path .= "//span[contains(concat(' ', @class, ' '), ' $phclass ')]";
        return $path;
    }

    /**
     * @Given /^I should not see any placeholders in the submissions area$/
     */
    public function i_should_not_see_any_placeholders_in_the_submissions_area() {
        $xpathbase = "//div[contains(@class, 'summary_assignsubmission_file')]";
        $xpathdownload = "$xpathbase//span[contains(concat(' ', @class, ' '), ' ally-download ')]";
        $xpathfeedback = "$xpathbase//span[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $xpath = $xpathdownload.'|'.$xpathfeedback;
        $this->execute('behat_general::should_not_exist', [$xpath, 'xpath_element']);
    }

    /**
     * @Given /^I should not see any placeholders in the grading submissions column$/
     */
    public function i_should_not_see_any_placeholders_in_the_grading_submissions_column() {
        $xpathbase = "//div[contains(@id, 'assign_files_tree')]";
        $xpathdownload = "$xpathbase//span[contains(concat(' ', @class, ' '), ' ally-download ')]";
        $xpathfeedback = "$xpathbase//span[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $xpath = $xpathdownload.'|'.$xpathfeedback;
        $this->execute('behat_general::should_not_exist', [$xpath, 'xpath_element']);
    }

    /**
     * @Given /^I should see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file)$/
     * @param string $anchorx
     * @param string $type
     */
    public function i_should_see_feedback_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-feedback', $type);
        $this->execute('behat_general::assert_element_contains_text', ['FEEDBACK', $path, 'xpath_element']);
    }

    /**
     * @Given /^I should not see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file)$/
     * @param string $anchorx
     */
    public function i_should_not_see_feedback_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-feedback', $type);
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * @Given /^I should see the download place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file)$/
     * @param string $anchorx
     */
    public function i_should_see_download_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-download', $type);
        $this->execute('behat_general::assert_element_contains_text', ['DOWNLOAD', $path, 'xpath_element']);
    }

    /**
     * @Given /^I should not see the download place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file)$/
     * @param string $anchorx
     */
    public function i_should_not_see_download_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-download', $type);
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * Forum post xpath
     * @param string $posttitle
     * @param string $postauthor
     * @param string $type - feedback or download
     * @return string
     */
    protected function forum_post_xpath($posttitle, $postauthor, $type) {
        $placeholderpath = '//span[@class="ally-'.$type.'"]';
        $pathforum = '//div[@aria-label="'.$posttitle.' by '.$postauthor.'"]'.$placeholderpath;
        $pathadvancedforum = '//h4[contains(text(), "'.$posttitle.'")]/'.
            'ancestor::article[@data-author="'.$postauthor.'"]'.$placeholderpath;
        $pathadvancedforum .= '|//div[@class="hsuforum-post-title"][contains(text(), "'.$posttitle.'")]/'.
            'ancestor::div[@data-author="'.$postauthor.'"]'.$placeholderpath;

        $path = $pathforum.'|'.$pathadvancedforum;
        return $path;
    }

    /**
     * @Given /^I should see the feedback place holder for the post entitled "(?P<post_string>[^"]*)" \
     * by "(?P<post_author>[^"]*)"$/
     * @param string $posttitle
     * @param string $postauthor
     */
    public function i_should_see_feedback_for_forum_post($posttitle, $postauthor) {
        $path = $this->forum_post_xpath($posttitle, $postauthor, 'feedback');
        $this->execute('behat_general::assert_element_contains_text', ['FEEDBACK', $path, 'xpath_element']);
    }

    /**
     * @Given /^I should not see the feedback place holder for the post entitled "(?P<post_string>[^"]*)" \
     * by "(?P<post_author>[^"]*)"$/
     * @param string $posttitle
     * @param string $postauthor
     */
    public function i_should_not_see_feedback_for_forum_post($posttitle, $postauthor) {
        $path = $this->forum_post_xpath($posttitle, $postauthor, 'feedback');
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * @Given /^I should see the download place holder for the post entitled "(?P<post_string>[^"]*)" \
     * by "(?P<post_author>[^"]*)"$/
     * @param string $posttitle
     * @param string $postauthor
     */
    public function i_should_see_download_for_forum_post($posttitle, $postauthor) {
        $path = $this->forum_post_xpath($posttitle, $postauthor, 'download');
        $this->execute('behat_general::assert_element_contains_text', ['DOWNLOAD', $path, 'xpath_element']);
    }

    /**
     * @Given /^I should not see the download place holder for the post entitled "(?P<post_string>[^"]*)" \
     * by "(?P<post_author>[^"]*)"$/
     * @param string $posttitle
     * @param string $postauthor
     */
    public function i_should_not_see_download_for_forum_post($posttitle, $postauthor) {
        $path = $this->forum_post_xpath($posttitle, $postauthor, 'download');
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * @Given /^I allow guest access for current course$/
     */
    public function i_allow_guest_access_for_current_course() {
        $course = $this->get_current_course();

        $instances = enrol_get_instances($course->id, false);
        $plugins   = enrol_get_plugins(false);
        foreach ($instances as $instance) {
            $plugin = $plugins[$instance->enrol];
            if ($plugin instanceof enrol_guest_plugin) {
                if ($instance->status != ENROL_INSTANCE_ENABLED) {
                    $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);
                }
            }
        }
    }

    /**
     * @Given /^I view all submissions$/
     */
    public function i_view_all_submissions() {
        $path = "//a[contains(text(), 'View all submissions')][contains(@class, 'btn')]";
        $this->execute('behat_general::i_click_on', [$path, 'xpath_element']);
    }
}
