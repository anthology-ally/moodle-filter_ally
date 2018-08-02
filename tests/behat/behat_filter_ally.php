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

use \Behat\Mink\Exception\ExpectationException;
use \tool_ally\local_content;
use \tool_ally\models\component_content;

/**
 * Ally filter context
 *
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @category  test
 * @package   filter_ally
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
     * @Given /I create a label with html content "(?P<content_string>[^"]*)" in section (?P<section_number>\d*)$/
     * @param string $content
     */
    public function i_create_a_label_with_html_content($content, $section) {

        $gen = testing_util::get_data_generator();

        $course = $this->get_current_course();

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => $content,
            'introformat' => FORMAT_HTML,
            'section' => $section
        ];

        $gen->create_module('label', $data);
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
                'name' => $file,
                'section' => 1 // Section 1 so that it will also work on front page.
            ];

            $resource = $gen->create_module('resource', $data);

            // Add actual file there.
            $filerecord = ['component' => 'mod_resource', 'filearea' => 'content',
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
        $node = $this->get_selected_node('xpath_element', $path);
        $this->ensure_node_is_visible($node);
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
            $path = "//*[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')][$anchorx]";
        } else if ($type === 'file resource') {
            $path = "//li[contains(concat(' ', @class, ' '), ' modtype_resource ')][$anchorx]";
            $path .= "//*[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')]";
        } else if ($type === 'assignment file') {
            $path = "//div[contains(@id, 'assign_files_tree')]//div[contains(concat(' ', @class, ' '), ' ygtvchildren ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ygtvitem ')][$anchorx]";
            $path .= "//*[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')]";
        } else if ($type === 'file in folder') {
            $path = "//div[contains(@id, 'folder_tree0')]//div[contains(concat(' ', @class, ' '), ' ygtvchildren ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ygtvitem ')][$anchorx]";
            $path .= "//*[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')]";
        } else if ($type === 'glossary attachment') {
            $path = "//td[contains(concat(' ', @class, ' '), ' entry ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' attachments ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ally-glossary-attachment-row ')][$anchorx]";
        } else {
            throw new coding_exception('Unknown feedback container type: '.$type);
        }
        $path .= "//*[contains(concat(' ', @class, ' '), ' $phclass ')]";
        return $path;
    }

    /**
     * @Given /^I should not see any placeholders in the submissions area$/
     */
    public function i_should_not_see_any_placeholders_in_the_submissions_area() {
        $xpathbase = "//div[contains(@class, 'summary_assignsubmission_file')]";
        $xpathdownload = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-download ')]";
        $xpathfeedback = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $xpath = $xpathdownload.'|'.$xpathfeedback;
        $this->execute('behat_general::should_not_exist', [$xpath, 'xpath_element']);
    }

    /**
     * @Given /^I should not see any placeholders in the grading submissions column$/
     */
    public function i_should_not_see_any_placeholders_in_the_grading_submissions_column() {
        $xpathbase = "//div[contains(@id, 'assign_files_tree')]";
        $xpathdownload = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-download ')]";
        $xpathfeedback = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $xpath = $xpathdownload.'|'.$xpathfeedback;
        $this->execute('behat_general::should_not_exist', [$xpath, 'xpath_element']);
    }

    /**
     * @Given /^I should see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|glossary attachment)$/
     * @param string $anchorx
     * @param string $type
     */
    public function i_should_see_feedback_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-feedback', $type);
        $node = $this->get_selected_node('xpath_element', $path);
        $this->ensure_node_is_visible($node);
    }

    /**
     * @Given /^I should not see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|glossary attachment)$/
     * @param string $anchorx
     */
    public function i_should_not_see_feedback_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-feedback', $type);
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * @Given /^I should see the download place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|glossary attachment)$/
     * @param string $anchorx
     */
    public function i_should_see_download_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-download', $type);
        $node = $this->get_selected_node('xpath_element', $path);
        $this->ensure_node_is_visible($node);
    }

    /**
     * @Given /^I should not see the download place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|glossary attachment)$/
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
        $placeholderpath = "//*[contains(concat(' ', @class, ' '), ' ally-{$type} ')]";
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
        $node = $this->get_selected_node('xpath_element', $path);
        $this->ensure_node_is_visible($node);
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
        $node = $this->get_selected_node('xpath_element', $path);
        $this->ensure_node_is_visible($node);
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

    /**
     * @param string $shortname
     * @param int $section
     * @param string $summary
     * @param int $format
     * @throws dml_exception
     */
    private function section_has_summary($shortname, $section, $summary, $format) {
        global $DB;
        $course = $DB->get_field('course', 'id', ['shortname' => $shortname]);
        $coursesection = $DB->get_record('course_sections', ['course' => $course, 'section' => $section]);
        $coursesection->summaryformat = $format;
        $coursesection->summary = $summary;
        $DB->update_record('course_sections', $coursesection);
    }

    /**
     * @param string $shortname
     * @param int $section
     * @param string $summary
     * @Given /^course "(?P<shortname_string>[^"]*)" section (?P<section_number>\d*) has html summary of \
     * "(?P<summary_string>[^"]*)"$/
     */
    public function section_has_html_summary($shortname, $section, $summary) {
        $this->section_has_summary($shortname, $section, $summary, FORMAT_HTML);
    }

    /**
     * @param string $shortname
     * @param int $section
     * @param string $summary
     * @Given /^course "(?P<shortname_string>[^"]*)" section (?P<section_number>\d*) has text summary of \
     * "(?P<summary_string>[^"]*)"$/
     */
    public function section_has_text_summary($shortname, $section, $summary) {
        $this->section_has_summary($shortname, $section, $summary, FORMAT_PLAIN);
    }

    /**
     * @Given /^section (?P<section_number>\d*) html is annotated$/
     */
    public function section_is_annotated($section) {
        $selector = '#section-'.$section.' > .content > div[class*="summary"] > .no-overflow[data-ally-richcontent]';
        $node = $this->find('css', $selector);
        if (empty($node)) {
            throw new ExpectationException(
                    'Failed to find annotation for section '.$section.' summary', $this->getSession());
        }
        $annotation = $node->getAttribute('data-ally-richcontent');
        if (strpos($annotation, 'course:course_sections:summary') === false) {
            throw new ExpectationException(
                    'Annotation is incorrect for '.$section.' summary - '.$annotation, $this->getSession());
        }
    }

    /**
     * @Given /^section (?P<section_number>\d*) html is not annotated$/
     */
    public function section_is_not_annotated($section) {
        $selector = '#section-'.$section.' > .content > div[class*="summary"] > .no-overflow';
        $node = $this->find('css', $selector);

        if ($node->hasAttribute('data-ally-richcontent')) {
            throw new ExpectationException(
                    'Annotation exists but should not exist for section '.$section.' summary', $this->getSession());
        }
    }

    /**
     * Get label content node by it's html content
     * @param string $html
     * @return \Behat\Mink\Element\NodeElement
     * @throws ExpectationException
     */
    private function get_label_content_node_by_html_content($html) {
        $modname = 'label';
        $html = $this->escape($html);
        $selector = <<<XPATH
//li[contains(concat( " ", @class, " " ), " activity ") and contains(concat( " ", @class, " " ), " $modname ")]
//div[contains(concat( " ", @class, " " ), " no-overflow ")][@data-ally-richcontent]//*[contains(text(), "$html")]
//parent::div[contains(concat( " ", @class, " " ), " no-overflow ")]
XPATH;

        return $this->find('xpath', $selector);
    }

    /**
     * @param string $html
     * @Given /^label with html "(?P<html_string>[^"]*)" is annotated$/
     */
    public function label_is_annotated($html) {
        $modname = 'label';
        $node = $this->get_label_content_node_by_html_content($html);
        if (empty($node)) {
            throw new ExpectationException(
                    'Failed to find annotation for module '.$modname.' with html '.$html, $this->getSession());
        }
        $annotation = $node->getAttribute('data-ally-richcontent');
        if (strpos($annotation, 'label:label:intro') === false) {
            throw new ExpectationException(
                    'Annotation is incorrect for for module '.$modname.' with html '.$html.' - '.$annotation,
                    $this->getSession());
        }
        $wsparams = explode(':', $annotation);
        if (count($wsparams) < 4) {
            throw new ExpectationException('Incorrect number of params in label annotation '.$annotation);
        }
    }

    /**
     * Get html content from annotation
     * @param string $annotation
     * @return component_content
     */
    private function get_html_content($annotation) {
        $wsparams = explode(':', $annotation);
        if (count($wsparams) < 4) {
            throw new ExpectationException('Incorrect number of params in label annotation '.$annotation);
        }
        $component = $wsparams[0];
        $table = $wsparams[1];
        $field = $wsparams[2];
        $id = $wsparams[3];
        return local_content::get_html_content(
            $id, $component, $table, $field, null);
    }

    /**
     * @param string $html
     * @Given /^I follow the webservice content url for label "(?P<html_string>[^"]*)"$/
     */
    public function follow_label_ws_url($html) {
        $node = $this->get_label_content_node_by_html_content($html);
        $annotation = $node->getAttribute('data-ally-richcontent');
        $content = $this->get_html_content($annotation);
        $this->getSession()->visit($content->contenturl);
    }

    protected function assert_element_in_viewport_or_not($node, $assertin = true) {

        $xpath = $node->getXpath();
        $xpath = str_replace(chr(13), '', $xpath);
        $xpath = str_replace(chr(10), '', $xpath);
        if (strpos($xpath, '(//html') !== false) {
            $xpath = substr($xpath, strlen('(//html'));
            $xpath = substr($xpath, 0, strrpos($xpath, ')[1]'));
        }

        $script = <<<JS
        (function(){
            var isElementInViewport = function(el) {
                var rect = el.getBoundingClientRect();
                return (
                    rect.top >= 0 &&
                    rect.left >= 0 &&
                    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                );
            }
            var el = document.evaluate('$xpath', document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
            return isElementInViewport(el);
        })();
JS;

        $inviewport = $this->getSession()->evaluateScript($script);

        if ($assertin) {
            if (!$inviewport) {
                throw new ExpectationException('Element is not in view port ' . $xpath, $this->getSession());
            }
        } else {
            if ($inviewport) {
                throw new ExpectationException('Element is in view port and should not be ' . $xpath, $this->getSession());
            }
        }
    }

    /**
     * Ensure element is either visible or not taking into account viewport
     * @param string $element
     * @param string $selectortype
     * @param boolean $visible
     * @throws ExpectationException
     */
    protected function ensure_element_visible_or_not($element, $selectortype, $visible) {
        if ($visible) {
            $node = $this->ensure_element_is_visible($element, $selectortype);
        } else {
            try {
                $node = $this->get_selected_node($selectortype, $element);
            } catch (Exception $e) {
                $node = null;
            }
            if (empty($node)) {
                // Failed to get node - so it can't be visible.
                return;
            }
            if ($node->isVisible()) {
                throw new ExpectationException(
                        'Element is visible and should not be '.$node->getXpath(), $this->getSession());
            }
        }

        $this->assert_element_in_viewport_or_not($node, $visible);
    }

    /**
     * @Given /^the "(?P<selector_string>[^"]*)" element "(?P<element_string>[^"]*)" is visible and in viewport$/
     * @param string $selectortype
     * @param string $element
     * @throws ExpectationException
     */
    public function ensure_element_is_visible_and_in_viewport($selectortype, $element) {
        $this->ensure_element_visible_or_not($element, $selectortype, true);
    }

    /**
     * @Given /^the "(?P<selector_string>[^"]*)" element "(?P<element_string>[^"]*)" is not visible or not in viewport$/
     * @param string $selectortype
     * @param string $element
     * @throws ExpectationException
     */
    public function ensure_element_is_not_visible_or_not_in_viewport($selectortype, $element) {
        $this->ensure_element_visible_or_not($element, $selectortype, false);
    }

    /**
     * @param string $content
     * @Given /^the label with html content "(?P<content_string>[^"]*)" is visible and in viewport$/
     */
    public function ensure_label_with_content_visible_and_in_viewport($content) {
        $label = $this->get_label_content_node_by_html_content($content);
        if (!$label->isVisible()) {
            throw new ExpectationException(
                    'Label is not visible and should be: '.$label->getXpath(),
                    $this->getSession());
        }
        $this->assert_element_in_viewport_or_not($label, true);
    }

    /**
     * @param string $content
     * @Given /^the label with html content "(?P<content_string>[^"]*)" is not visible or not in viewport$/
     */
    public function ensure_label_with_content_not_visible_or_not_in_viewport($content) {
        $label = $this->get_label_content_node_by_html_content($content);
        if (!$label->isVisible()) {
            return; // Not visible so no need to check viewport.
        }
        $this->assert_element_in_viewport_or_not($label, false);
    }
}
