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
 * @author    Guy Thomas
 * @copyright Copyright (c) 2017 Open LMS / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package filter_ally
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Element\NodeElement;
use Moodle\BehatExtension\Exception\SkippedException;
use tool_ally\local_content;
use tool_ally\models\component_content;

/**
 * Ally filter context
 *
 * @author    Guy Thomas
 * @copyright Copyright (c) 2017 Open LMS / 2023 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @category  test
 * @package   filter_ally
 */
class behat_filter_ally extends behat_base {
    /**
     * Check specific forum type is installed.
     *
     * @Given forum type :forumtype is available
     */
    public function forum_module_exists(string $forumtype): void {
        global $CFG;

        $path = $CFG->dirroot . '/mod/' . $forumtype;

        if (!is_dir($path)) {
            throw new SkippedException("Open forums module not found at $path");
        }
    }

    /**
     * Wrapper - necessary to allow for execution where mod_hsuforum is not installed.
     * Adds a new discussion to a forum of the given type.
     *
     * @When I add a new discussion to :forumname using forum type :forumtype with:
     *
     * @param string $forumname The forum name as seen on the course page.
     * @param string $forumtype The short name of the forum module (e.g. 'forum', 'hsuforum').
     * @param TableNode $data The discussion data (Subject, Message, Attachment, etc).
     */
    public function i_add_new_discussion_to_forumtype(
        string $forumname,
        string $forumtype,
        TableNode $data
    ): void {
        global $CFG;

        $path = $CFG->dirroot . '/mod/' . $forumtype;
        if (!is_dir($path)) {
            throw new \Moodle\BehatExtension\Exception\SkippedException("Forum type '$forumtype' not found at $path");
        }

        $step = "i_add_a_forum_discussion_to_forum_with";
        $context = $forumtype === 'forum' ? 'behat_mod_forum' : 'behat_mod_hsuforum';
        $this->execute("$context::$step", [$forumname, $data]);
    }

    /**
     * Wrapper - necessary to allow for execution where mod_hsuforum is not installed.
     * Replies to a post in a forum discussion of the given type.
     *
     * @When I reply :postname post from :discussion using forum type :forumtype with:
     *
     * @param string $postname
     * @param string $discussion The subject of the original post to reply to.
     * @param string $forumtype The short name of the forum module (e.g. 'forum', 'hsuforum').
     * @param TableNode $data The reply data (Message, Attachment, etc).
     */
    public function i_reply_post_from_discussion_with_forumtype(
        string $postname,
        string $discussion,
        string $forumtype,
        TableNode $data
    ): void {
        global $CFG;

        $path = $CFG->dirroot . '/mod/' . $forumtype;
        if (!is_dir($path)) {
            throw new \Moodle\BehatExtension\Exception\SkippedException("Forum type '$forumtype' not found at $path");
        }

        $context = $forumtype === 'forum' ? 'behat_mod_forum' : 'behat_mod_hsuforum';
        $step = "i_reply_post_from_forum_with";

        $this->execute("$context::$step", [$postname, $discussion, $data]);
    }

    /**
     * Check that the ally filter is enabled.
     *
     * @Given /^the ally filter is enabled$/
     */
    public function the_ally_filter_is_enabled() {
        filter_set_global_state('ally', TEXTFILTER_ON);
    }

    /**
     * Enable or disable the ally filter for the current course.
     * @param int $status
     * @throws ExpectationException
     * @throws coding_exception
     */
    private function ally_filter_status_course($status = TEXTFILTER_ON) {
        $course = $this->get_current_course();
        $context = context_course::instance($course->id);

        filter_set_local_state('ally', $context->id, $status);
    }

    /**
     * Ensure ally filter is enabled for the current course.
     *
     * @Given the ally filter is enabled for course
     * @throws coding_exception
     */
    public function the_ally_filter_is_enabled_for_course() {
        $this->ally_filter_status_course();
    }

    /**
     * Ensure ally filter is not enabled for the current course.
     *
     * @Given the ally filter is not enabled for course
     * @throws coding_exception
     */
    public function the_ally_filter_is_not_enabled_for_course() {
        $this->ally_filter_status_course(TEXTFILTER_OFF);
    }

    /**
     * Get current course;
     *
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
            throw new coding_exception('Failed to get course by id ' . $courseid . ' ' . $bodyclass);
        }
        return ($course);
    }

    /**
     * Create a label with fixture images.
     *
     * @When /^I create a label with fixture images "(?P<images_string>[^"]*)"$/
     * @param string $images (csv)
     */
    public function i_create_label_with_sample_images($images) {
        global $CFG, $DB;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot . '/filter/ally/tests/fixtures/';
        $images = explode(',', $images);

        $labeltext = '<h2>A test label</h2>';

        $voidtype = '/>';

        $course = $this->get_current_course();

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => 'pre file inserts',
            'introformat' => FORMAT_HTML,
        ];

        $label = $gen->create_module('label', $data);

        $i = 0;
        foreach ($images as $image) {
            $image = trim($image);
            $i++;
            // Alternate the way the image tag is closed.
            $voidtype = $voidtype === '/>' ? '>' : '/>';
            $fixturepath = $fixturedir . $image;
            if (!file_exists($fixturepath)) {
                throw new coding_exception('Fixture image does not exist ' . $fixturepath);
            }

            // Add actual file there.
            $filerecord = ['component' => 'mod_label', 'filearea' => 'intro',
                'contextid' => context_module::instance($label->cmid)->id, 'itemid' => 0,
                'filename' => $image, 'filepath' => '/', ];
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
     * Create a label with random text files.
     *
     * @When /I create a label with random text files "(?P<files_string>[^"]*)"/
     * @param str $files (csv)
     */
    public function i_create_a_label_with_random_text_files($files) {
        global $CFG, $DB;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot . '/filter/ally/tests/fixtures/';
        $files = explode(',', $files);

        $labeltext = '<h2>A test label</h2>';

        $course = $this->get_current_course();

        $data = (object) [
            'course' => $course->id,
            'name' => 'test label',
            'intro' => 'pre file inserts',
            'introformat' => FORMAT_HTML,
        ];

        $label = $gen->create_module('label', $data);

        $i = 0;
        foreach ($files as $file) {
            $file = trim($file);
            $i++;
            // Alternate the way the image tag is closed.
            $fixturepath = $fixturedir . $file;

            // Add actual file there.
            $filerecord = ['component' => 'mod_label', 'filearea' => 'intro',
                'contextid' => context_module::instance($label->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/', ];
            $fs = get_file_storage();
            $fs->create_file_from_string($filerecord, 'test file ' . $i);
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
     * Create a module of a specific type in a specific section with html content.
     *
     * @When I create a :module with html content :content in section :arg3
     * @param string $content
     */
    public function i_create_a_module_with_html_content($module, $content, $section) {
        global $DB;

        $gen = testing_util::get_data_generator();

        $course = $this->get_current_course();

        $data = (object) [
            'course' => $course->id,
            'name' => 'test ' . $module,
            'intro' => $content,
            'introformat' => FORMAT_HTML,
            'section' => $section,
            'showdescription' => $module === 'lesson' ? 1 : 0,
        ];

        $mod = $gen->create_module($module, $data);

        if ($module !== 'label') {
            $cm = $DB->get_record('course_modules', ['id' => $mod->cmid]);
            $cm->showdescription = 1;
            $DB->update_record('course_modules', $cm);
        }
    }

    /**
     * Stolen from /Users/guy/Development/www/moodle_test/blocks/tests/privacy_test.php
     * Get the block manager.
     *
     * @param array $regions The regions.
     * @param context $context The context.
     * @param string $pagetype The page type.
     * @param string $subpage The sub page.
     * @return block_manager
     */
    protected function get_block_manager($regions, $context, $pagetype = 'page-type', $subpage = '') {
        global $CFG;
        require_once($CFG->libdir . '/blocklib.php');
        $page = new moodle_page();
        $page->set_context($context);
        $page->set_pagetype($pagetype);
        $page->set_subpage($subpage);
        $page->set_url(new moodle_url('/'));

        $blockmanager = new block_manager($page);
        $blockmanager->add_regions($regions, false);
        $blockmanager->set_default_region($regions[0]);

        return $blockmanager;
    }

    /**
     * Add a html block with specific content.
     *
     * @When I add a html block with title :title and content :content
     * @param string $title
     * @param string $content
     */
    public function i_add_a_html_block_with_content($title, $content) {
        global $DB;
        // Note - we are not going to use behat_blocks::i_add_the_block because we don't want to test
        // fhe block UI, we just want to add a block!
        $course = $this->get_current_course();
        $context = context_course::instance($course->id);
        $bm = $this->get_block_manager(['side-pre'], $context);

        // Wow - the following doesn't return anything useful like say, erm, the block id!
        $bm->add_block('html', 'side-pre', 1, true, 'course-view-*');

        $blocks = $DB->get_records('block_instances', [], 'id DESC', 'id', 0, 1);
        if (empty($blocks)) {
            throw new coding_exception('Created a block but block instances empty!');
        }
        $block = reset($blocks);
        $blockconfig = (object) [
            'title' => $title,
            'format' => FORMAT_HTML,
            'text' => $content,
        ];
        $block->configdata = base64_encode(serialize($blockconfig));
        $DB->update_record('block_instances', $block);
    }

    /**
     * User opens specific module.
     *
     * @When I open the :module module
     * @param string $module
     */
    public function i_open_the_module($module) {
        $xpath = <<<XPATH
        //div[contains(@class,"activity-instance")]//span[contains(text(), 'test $module')]/../../a
XPATH;

        $this->execute('behat_general::i_click_on', [$xpath, 'xpath_element']);
    }

    /**
     * Add chapters to a book.
     *
     * @When I add :chapters chapters to ":bookname"
     * @param int $numchapters
     * @param string $bookname
     */
    public function i_add_chapters_to_book($numchapters, $bookname) {
        global $DB;

        if ($numchapters < 1) {
            throw new coding_exception('$numchapters cannot be less than 1');
        }

        $course = $this->get_current_course();
        $book = $DB->get_record('book', ['course' => $course->id, 'name' => $bookname]);
        $chaptercount = $DB->count_records('book_chapters', ['bookid' => $book->id]);

        $gen = testing_util::get_data_generator();
        $bookgenerator = $gen->get_plugin_generator('mod_book');

        for ($c = 0; $c < $numchapters; $c++) {
            $chptitlenum = $chaptercount + $c + 1;
            $data = [
                'bookid' => $book->id,
                'title' => $bookname . ' chapter ' . $chptitlenum,
                'content' => 'Test content ' . $chptitlenum,
                'contentformat' => FORMAT_HTML,
            ];

            $bookgenerator->create_chapter($data);
        }
    }

    /**
     * Get lesson instance by name for current course.
     */
    private function get_lesson_instance_by_name_for_current_course($lessonname) {
        global $DB;
        $course = $this->get_current_course();
        return $DB->get_record('lesson', ['course' => $course->id, 'name' => $lessonname]);
    }

    /**
     * Add pages to a lesson.
     *
     * @When I add :pages content pages to lesson ":lessonname"
     * @param $numpages
     * @param $lessonname
     */
    public function i_add_pages_to_lesson($numpages, $lessonname) {
        global $DB, $CFG; // This CFG needs to be here for the require to work.

        require_once(__DIR__ . '/../../../../mod/lesson/locallib.php');

        if ($numpages < 1) {
            throw new coding_exception('$numpages cannot be less than 1');
        }

        $lesson = $this->get_lesson_instance_by_name_for_current_course($lessonname);
         [$course, $cm] = get_course_and_cm_from_instance($lesson->id, 'lesson');
        $lesson->cmid = $cm->id;
        $pagecount = $DB->count_records('lesson_pages', ['lessonid' => $lesson->id]);

        $gen = testing_util::get_data_generator();
        $lessongenerator = $gen->get_plugin_generator('mod_lesson');

        for ($c = 0; $c < $numpages; $c++) {
            $titlenum = $pagecount + $c + 1;

            $lessonobj = new lesson($lesson);

            $page = $lessongenerator->create_content($lessonobj);
            $page->contents = 'Test content ' . $titlenum;
            $page->contentsformat = FORMAT_HTML;
            $page->title = $lessonname . ' content ' . $titlenum;

            $DB->update_record('lesson_pages', $page);
        }
    }

    /**
     * Add true false question pages to a lesson.
     *
     * @When I add :pages true false pages to lesson ":lessonname"
     * @param int $numpages
     * @param string $bookname
     */
    public function i_add_truefalse_pages_to_lesson($numpages, $lessonname) {
        global $DB, $CFG; // This CFG needs to be here for the require to work.

        require_once(__DIR__ . '/../../../../mod/lesson/locallib.php');

        if ($numpages < 1) {
            throw new coding_exception('$numpages cannot be less than 1');
        }

        $course = $this->get_current_course();
        $lesson = $this->get_lesson_instance_by_name_for_current_course($lessonname);
         [$course, $cm] = get_course_and_cm_from_instance($lesson->id, 'lesson');
        $lesson->cmid = $cm->id;
        $pagecount = $DB->count_records('lesson_pages', ['lessonid' => $lesson->id]);

        $gen = testing_util::get_data_generator();
        $lessongenerator = $gen->get_plugin_generator('mod_lesson');

        for ($c = 0; $c < $numpages; $c++) {
            $titlenum = $pagecount + $c + 1;

            $lessonobj = new lesson($lesson);

            $record = [];
            // The lesson generator doesn't add response text by default so we need to do that here.
            $record['response_editor'][0] = [
                'text' => 'TRUE response for ' . $titlenum,
                'format' => FORMAT_HTML,
            ];
            $record['response_editor'][1] = [
                'text' => 'FALSE response for ' . $titlenum,
                'format' => FORMAT_HTML,
            ];
            $page = $lessongenerator->create_question_truefalse($lessonobj, $record);
            $page->contents = 'Test true false question ' . $titlenum;
            $page->contentsformat = FORMAT_HTML;
            $page->title = $lessonname . ' question ' . $titlenum;

            $DB->update_record('lesson_pages', $page);
        }
    }

    /**
     * Check that the current book chapter is annotated.
     * @Then the current book chapter is annotated
     */
    public function book_current_chapter_is_annotated() {
        $xpath = <<<XPATH
            //div[@id="mod_book-chapter"]/div[@class="no-overflow"]|
            //section[@id="region-main"]//div[@role="main"]/div/div[@class="no-overflow"]
XPATH;
        $node = $this->find('xpath', $xpath);
        $params = ['node' => $node];
        $timeout = false;
        $exception = new ExpectationException('Annotation not found', $this->getSession()->getDriver());
        $microsleep = false;

        $annotationids = [
            'book:book:intro',
            'book:book_chapters:content',
        ];
        return $this->spin(
            function ($context, $args) use ($annotationids) {
                $node = $args['node'];
                $annotation = $node->getAttribute('data-ally-richcontent');
                return preg_match('(' . implode('|', $annotationids) . ')', $annotation) === 1;
            },
            $params,
            $timeout,
            $exception,
            $microsleep
        );
    }

    /**
     * Check that the current lesson page is annotated.
     *
     * @Then the current lesson page is annotated
     */
    public function lesson_current_page_is_annotated() {
        $xpath = <<<XPATH
            //body[@id="page-mod-lesson-view"]//form//fieldset//div[@class="contents"]/div[@data-ally-richcontent]
XPATH;
        $node = $this->find('xpath', $xpath);
        $annotation = $node->getAttribute('data-ally-richcontent');

        return strpos($annotation, 'lesson:lesson_page') !== false;
    }

    /**
     * Check that the lesson page content with the given title is annotated.
     *
     * @Then the lesson page content entitled ":title" is annotated and contains text ":text"
     * @param string $title
     */
    public function lesson_page_content_annotated($title, $exptext) {
        global $DB;
        $id = $DB->get_field('lesson_pages', 'id', ['title' => $title]);
        if (!$id) {
            throw new ExpectationException('No lesson page content with title ' . $title, $this->getSession());
        }
        $annotation = 'lesson:lesson_pages:contents:' . $id;
        $xpath = <<<XPATH
            //*[@data-ally-richcontent="$annotation"]
XPATH;
        $node = $this->find('xpath', $xpath);
        $text = $node->getText();
        if (stripos($text, $exptext) === false) {
            $msg = 'Annotation mismatch for element with title "' . $title . '" - element contained text "' . $text . '"';
            $msg .= ' Expected "' . $exptext . '"';
            throw new ExpectationException($msg, $this->getSession());
        }
    }

    /**
     * Check that the lesson answer or response content with the given content is annotated.
     *
     * @throws ExpectationException
     * @param string $content
     * @param string $type 'answer' or 'response'
     */
    private function lesson_or_answer_content_annotated($content, $type = 'answer') {
        global $DB;
        $select = $DB->sql_like($type, ':content');
        $params = ['content' => $content];
        // Note responses also live in the answers table.
        $id = $DB->get_field_select('lesson_answers', 'id', $select, $params);
        if (!$id) {
            throw new ExpectationException('No lesson ' . $type . ' found with content: ' . $content, $this->getSession());
        }
        $annotation = 'lesson:lesson_answers:' . $type . ':' . $id;
        $xpath = <<<XPATH
            //*[@data-ally-richcontent="$annotation"]
XPATH;
        $node = $this->find('xpath', $xpath);
        $text = $node->getText();
        if (stripos($text, $content) === false) {
            $msg = 'Annotation mismatch for ' . $type . ' containing content "' . $content
                . '" - element contained text "' . $text . '"'
                . ' Expected "' . $content . '"';
            throw new ExpectationException($msg, $this->getSession());
        }
    }

    /**
     * Check that the lesson answer content with the given content is annotated.
     *
     * @Then the lesson answer containing content ":content" is annotated
     */
    public function lesson_answer_content_annotated($content) {
        $this->lesson_or_answer_content_annotated($content);
    }

    /**
     * Check that the lesson response content with the given content is annotated.
     *
     * @Then the lesson response containing content ":content" is annotated
     */
    public function lesson_response_content_annotated($content) {
        $this->lesson_or_answer_content_annotated($content, 'response');
    }


    /**
     * Checks that the provided node is visible.
     *
     * @throws ExpectationException
     * @param NodeElement $node
     * @param int $timeout
     * @param null|ExpectationException $exception
     * @return bool
     */
    protected function is_node_visible(
        NodeElement $node,
        $timeout = null,
        ExpectationException $exception = null
    ) {

        $timeout = $timeout == null ? behat_base::get_extended_timeout() : $timeout;
        // If an exception isn't specified then don't throw an error if visibility can't be evaluated.
        $dontthrowerror = empty($exception);

        // Exception for timeout checking visibility.
        $msg = 'Something went wrong whilst checking visibility';
        $exception = new ExpectationException($msg, $this->getSession());

        $visible = false;

        try {
            $visible = $this->spin(
                function ($context, $args) {
                    if ($args->isVisible()) {
                        return true;
                    }
                    return false;
                },
                $node,
                $timeout,
                $exception,
                true
            );
        } catch (Exception $e) {
            if (!$dontthrowerror) {
                throw $exception;
            }
        }
        return $visible;
    }

    /**
     * Clicks link with specified id|title|alt|text.
     *
     * @When I follow visible link ":link" _ally_
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $link
     */
    public function click_visible_link($link) {
        $linknode = $this->find_link($link);
        if (!$linknode) {
            $msg = 'The "' . $link . '" link could not be found';
            throw new ExpectationException($msg, $this->getSession());
        }

        // See if the first node is visible and if so click it.
        if ($this->is_node_visible($linknode, behat_base::get_reduced_timeout())) {
            $linknode->click();
            return;
        }

        /** @var NodeElement[] $linknodes */
        $linknodes = $this->find_all('named_partial', ['link', behat_context_helper::escape($link)]);

        // Cycle through all nodes and if just one of them is visible break loop.
        foreach ($linknodes as $node) {
            if ($node === $linknode) {
                // We've already tested the first node, skip it.
                continue;
            }
            if ($node->isVisible()) {
                $node->click();
                return;
            }
        }

        // Oh dear, none of the links were visible.
        throw new ExpectationException('At least one node should be visible for the xpath "' . $xpath . '"', $this->getSession());
    }

    /**
     * Check that the true false questions for a lesson are annotated.
     *
     * @Then the true false questions for lesson ":lessonname" are annotated
     * @param string $lessonname
     */
    public function true_false_lesson_questions_annotated($lessonname) {
        global $DB;

        $lesson = $this->get_lesson_instance_by_name_for_current_course($lessonname);

        $sql = <<<SQL
        SELECT la.* FROM {lesson_pages} lp
                    JOIN {lesson_answers} la ON la.pageid = lp.id
                   WHERE lp.lessonid = ? AND lp.qtype = ?
SQL;

        $params = ['lessonid' => $lesson->id, 'qtype' => LESSON_PAGE_TRUEFALSE];

        $tfanswers = $DB->get_records_sql($sql, $params);
        foreach ($tfanswers as $answer) {
            $id = $answer->id;
            $xpath = <<<XPATH
                //span[@id="answer_wrapper_{$id}"][@data-ally-richcontent]
XPATH;
            $node = $this->find('xpath', $xpath);
            $annotation = $node->getAttribute('data-ally-richcontent');
            if (strpos($annotation, 'lesson:lesson_answer') === false) {
                throw new ExpectationException('Answer wrapper is not annotated', $this->getSession()->getDriver());
            }
        }
    }

    /**
     * Create file resources using fixture files.
     *
     * @When I create file resources using fixtures :fixtures_string
     * @When I create file resources using fixtures :fixtures_string in section :section
     * @param string $fixtures
     * @param string|null $section
     */
    public function i_create_file_resources_using_fixtures(string $fixtures, ?string $section = null) {
        global $CFG;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot . '/filter/ally/tests/fixtures/';
        $files = explode(',', $fixtures);

        $course = $this->get_current_course();

        foreach ($files as $file) {
            $file = trim($file);
            $fixturepath = $fixturedir . $file;
            if (!file_exists($fixturepath)) {
                throw new coding_exception('Fixture file does not exist ' . $fixturepath);
            }

            $data = (object) [
                'course' => $course->id,
                'name' => $file,
                'section' => $section ? intval($section) : 1, // Default is section 1 so that it will also work on front page.
            ];

            $resource = $gen->create_module('resource', $data);

            // Add actual file there.
            $filerecord = ['component' => 'mod_resource', 'filearea' => 'content',
                'contextid' => context_module::instance($resource->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/', ];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
        }
    }

    /**
     * Create an assignment with additional file submissions.
     *
     * @When /^I create assignment "(?P<name_string>[^"]*)" with additional file fixtures "(?P<fixtures_string>[^"]*)"/
     * @param $assignname
     * @param $fixtures
     */
    public function i_create_assign_with_additional_files($assignname, $fixtures) {
        global $CFG;

        $gen = testing_util::get_data_generator();

        $fixturedir = $CFG->dirroot . '/filter/ally/tests/fixtures/';
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
            'assignsubmission_onlinetext_enabled' => 1,
        ];

        $assign = $assigngen->create_instance($data);

        foreach ($files as $file) {
            $file = trim($file);
            $fixturepath = $fixturedir . $file;

            // Add actual file there.
            $filerecord = ['component' => 'mod_assign', 'filearea' => 'introattachment',
                'contextid' => context_module::instance($assign->cmid)->id, 'itemid' => 0,
                'filename' => $file, 'filepath' => '/', ];
            $fs = get_file_storage();
            $fs->create_file_from_pathname($filerecord, $fixturepath);
        }
    }

    /**
     * Check for feedback placeholder for specific image.
     *
     * @Then /^I should see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" image$/
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
     * Check user should not see feedback placeholder for specific image.
     *
     * @Then /^I should not see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" image$/
     * @param string $imagex
     */
    public function i_should_not_see_feedback_for_image_x($imagex) {
        $imagex = intval($imagex);
        $path = "//span[contains(concat(' ', @class, ' '), ' ally-image-wrapper ')][$imagex]";
        $path .= "//span[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * Check for ally image cover area for specific image.
     *
     * @Then /^the ally image cover area should exist for the "(\d*)(?:st|nd|rd|th)" image$/
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
        } else if ($type === 'file in subfolder') {
            $path = "//div[contains(@id, 'folder_tree0')]//div[contains(concat(' ', @class, ' '), ' ygtvchildren ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ygtvitem ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ygtvchildren ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ygtvitem ')][$anchorx]";
            $path .= "//*[contains(concat(' ', @class, ' '), ' ally-anchor-wrapper ')]";
        } else if ($type === 'glossary attachment') {
            $path = "//td[contains(concat(' ', @class, ' '), ' entry ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' attachments ')]";
            $path .= "//div[contains(concat(' ', @class, ' '), ' ally-glossary-attachment-row ')][$anchorx]";
        } else {
            throw new coding_exception('Unknown feedback container type: ' . $type);
        }
        $path .= "//*[contains(concat(' ', @class, ' '), ' $phclass ')]";
        return $path;
    }

    /**
     * Check user should not see any placeholders in the submissions area.
     *
     * @Then /^I should not see any placeholders in the submissions area$/
     */
    public function i_should_not_see_any_placeholders_in_the_submissions_area() {
        $xpathbase = "//div[contains(@class, 'summary_assignsubmission_file')]";
        $xpathdownload = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-download ')]";
        $xpathfeedback = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $xpath = $xpathdownload . '|' . $xpathfeedback;
        $this->execute('behat_general::should_not_exist', [$xpath, 'xpath_element']);
    }

    /**
     * Check user should not see any placeholders in the grading submissions column.
     *
     * @Then /^I should not see any placeholders in the grading submissions column$/
     */
    public function i_should_not_see_any_placeholders_in_the_grading_submissions_column() {
        $xpathbase = "//div[contains(@id, 'assign_files_tree')]";
        $xpathdownload = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-download ')]";
        $xpathfeedback = "$xpathbase//*[contains(concat(' ', @class, ' '), ' ally-feedback ')]";
        $xpath = $xpathdownload . '|' . $xpathfeedback;
        $this->execute('behat_general::should_not_exist', [$xpath, 'xpath_element']);
    }

    /**
     * Check for feedback placeholder for specific anchor / file resource / assignment
     * in glossary attachment.
     *
     * @Then /^I should see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|file in subfolder|glossary attachment)$/
     * @param string $anchorx
     * @param string $type
     */
    public function i_should_see_feedback_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-feedback', $type);
        $node = $this->get_selected_node('xpath_element', $path);
        $this->ensure_node_is_visible($node);
    }

    /**
     * Check user should not see feedback placeholder for specific anchor / file resource / assignment
     * in glossary attachment.
     *
     * @Then /^I should not see the feedback place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|file in subfolder|glossary attachment)$/
     * @param string $anchorx
     */
    public function i_should_not_see_feedback_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-feedback', $type);
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * Check user should see feedback placeholder for specific anchor / file resource / assignment
     * in glossary attachment.
     *
     * @Then /^I should see the download place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|file in subfolder|glossary attachment)$/
     * @param string $anchorx
     */
    public function i_should_see_download_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-download', $type);
        $node = $this->get_selected_node('xpath_element', $path);
        $this->ensure_node_is_visible($node);
    }

    /**
     * Check user should not see download placeholder for specific anchor / file resource / assignment
     * in glossary attachment.
     *
     * @Then /^I should not see the download place holder for the "(\d*)(?:st|nd|rd|th)" \
     * (anchor|file resource|assignment file|file in folder|file in subfolder|glossary attachment)$/
     * @param string $anchorx
     */
    public function i_should_not_see_download_for_anchor_x($anchorx, $type) {
        $path = $this->get_placeholder_xpath($anchorx, 'ally-download', $type);
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * Forum post xpath
     *
     * @param string $posttitle
     * @param string $postauthor
     * @param string $type - feedback or download
     * @return string
     */
    protected function forum_post_xpath($posttitle, $postauthor, $type) {
        $placeholderpath = "//*[contains(concat(' ', @class, ' '), ' ally-{$type} ')]";
        $pathforum = '//div[@aria-label="' . $posttitle . ' by ' . $postauthor . '"]' . $placeholderpath;
        $pathadvancedforum = '//h4[contains(text(), "' . $posttitle . '")]/' .
            'ancestor::article[@data-author="' . $postauthor . '"]' . $placeholderpath;
        $pathadvancedforum .= '|//div[@class="hsuforum-post-title"][contains(text(), "' . $posttitle . '")]/' .
            'ancestor::div[@data-author="' . $postauthor . '"]' . $placeholderpath;

        $path = $pathforum . '|' . $pathadvancedforum;
        return $path;
    }

    /**
     * Check for feedback placeholder for forum post.
     *
     * @Then /^I should see the feedback place holder for the post entitled "(?P<post_string>[^"]*)" \
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
     * Check feedback placeholder not shown for forum post.
     *
     * @Then /^I should not see the feedback place holder for the post entitled "(?P<post_string>[^"]*)" \
     * by "(?P<post_author>[^"]*)"$/
     * @param string $posttitle
     * @param string $postauthor
     */
    public function i_should_not_see_feedback_for_forum_post($posttitle, $postauthor) {
        $path = $this->forum_post_xpath($posttitle, $postauthor, 'feedback');
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * Check for download placeholder for forum post.
     *
     * @Then /^I should see the download place holder for the post entitled "(?P<post_string>[^"]*)" \
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
     * Check download placeholder not shown for forum post.
     *
     * @Then /^I should not see the download place holder for the post entitled "(?P<post_string>[^"]*)" \
     * by "(?P<post_author>[^"]*)"$/
     * @param string $posttitle
     * @param string $postauthor
     */
    public function i_should_not_see_download_for_forum_post($posttitle, $postauthor) {
        $path = $this->forum_post_xpath($posttitle, $postauthor, 'download');
        $this->execute('behat_general::should_not_exist', [$path, 'xpath_element']);
    }

    /**
     * Allow guest access for current course.
     *
     * @When /^I allow guest access for current course$/
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
     * View all submissions.
     *
     * @When /^I view all submissions$/
     */
    public function i_view_all_submissions() {
        $path = "//a[contains(text(), 'Submissions')][contains(@role, 'menuitem')]";
        $this->execute('behat_general::i_click_on', [$path, 'xpath_element']);
    }

    /**
     * Get course and section by shortname / section.
     *
     * @param string $shortname
     * @param int $section
     */
    private function get_course_and_section(string $shortname, int $section): array {
        global $DB;

        $course = $DB->get_field('course', 'id', ['shortname' => $shortname]);
        if (!$course) {
            throw new coding_exception('Invalid moodle course shortname', $shortname);
        }
        $coursesection = $DB->get_record('course_sections', ['course' => $course, 'section' => $section]);
        if (!$coursesection) {
            throw new coding_exception('Invalid moodle course section', $section);
        }

        return [$course, $coursesection];
    }

    /**
     * Add summary to course section.
     *
     * @param string $shortname
     * @param int $section
     * @param string $summary
     * @param int $format
     * @throws dml_exception
     */
    private function section_add_summary($shortname, $section, $summary, $format) {
        global $DB;
        [$course, $coursesection] = $this->get_course_and_section($shortname, $section);
        $coursesection->summaryformat = $format;
        $coursesection->summary = $summary;
        $DB->update_record('course_sections', $coursesection);

        // Trigger an event for course section update - just like is done by core when a course
        // section is updated. This ensures caches are updated appropriately during testing just
        // as they are in normal use.
        $event = \core\event\course_section_updated::create(
            [
                'objectid' => $coursesection->id,
                'courseid' => $course,
                'context' => context_course::instance($course),
                'other' => ['sectionnum' => $coursesection->section],
            ]
        );
        $event->trigger();
    }

    /**
     * User is on course section page.
     *
     * @Given I am on course :shortname section :section
     *
     * @param $shortname
     * @param string|null $section
     * @return void
     * @throws \coding_exception
     * @throws \core\exception\moodle_exception
     */
    public function on_course_section_page($shortname, ?string $section = null) {
        [, $coursesection] = $this->get_course_and_section($shortname, $section);

        $urlparams = ['id' => $coursesection->id];
        $url = new moodle_url('/course/section.php', $urlparams);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Add summary to course section in HTML format.
     *
     * @Given /^course "(?P<shortname_string>[^"]*)" section (?P<section_number>\d*) has html summary of \
     * "(?P<summary_string>[^"]*)"$/
     * @param string $shortname
     * @param int $section
     * @param string $summary
     */
    public function section_has_html_summary($shortname, $section, $summary) {
        $this->section_add_summary($shortname, $section, $summary, FORMAT_HTML);
    }

    /**
     * Add summary to course section in plain text format.
     *
     * @param string $shortname
     * @param int $section
     * @param string $summary
     * @Given /^course "(?P<shortname_string>[^"]*)" section (?P<section_number>\d*) has text summary of \
     * "(?P<summary_string>[^"]*)"$/
     */
    public function section_has_text_summary($shortname, $section, $summary) {
        $this->section_add_summary($shortname, $section, $summary, FORMAT_PLAIN);
    }

    /**
     * Get section summary selector.
     *
     * @param int $section
     * @param bool $targetally
     * @return string
     */
    private function section_summary_selector(int $section, $targetally = true) {
        $allyselector = $targetally ? '[data-ally-richcontent]' : '';
        $selector = <<<CSSSEL
            #section-$section > .content div[class*="summarytext"] .no-overflow$allyselector,
            #section-$section > .section-item div[class*="summarytext"] .no-overflow$allyselector
CSSSEL;
        return $selector;
    }

    /**
     * Check that course section summary is annotated.
     *
     * @Then /^section (?P<section_number>\d*) html is annotated$/
     */
    public function section_is_annotated(int $section) {
        $selector = $this->section_summary_selector($section);
        $node = $this->find('css', $selector);
        if (empty($node)) {
            throw new ExpectationException(
                'Failed to find annotation for section ' . $section . ' summary',
                $this->getSession()
            );
        }
        $annotation = $node->getAttribute('data-ally-richcontent');
        if (strpos($annotation, 'course:course_sections:summary') === false) {
            throw new ExpectationException(
                'Annotation is incorrect for ' . $section . ' summary - ' . $annotation,
                $this->getSession()
            );
        }
    }

    /**
     * Check that course section summary is not annotated.
     *
     * @Then /^section (?P<section_number>\d*) html is not annotated$/
     */
    public function section_is_not_annotated(int $section) {
        $selector = $this->section_summary_selector($section, false);
        $node = $this->find('css', $selector);

        if ($node->hasAttribute('data-ally-richcontent')) {
            throw new ExpectationException(
                'Annotation exists but should not exist for section ' . $section . ' summary',
                $this->getSession()
            );
        }
    }

    /**
     * Check that forum is annotated.
     *
     * @Then Forum should be annotated
     */
    public function forum_is_annotated() {
        $selector = '#page-mod-forum-discuss #region-main .forumpost .no-overflow[data-ally-richcontent]';
        $node = $this->find('css', $selector);
        if (empty($node)) {
            throw new ExpectationException(
                'Failed to find annotation',
                $this->getSession()
            );
        }
    }

    /**
     * Get module content node by html content.
     *
     * @param string $modname
     * @param string $html
     * @return \Behat\Mink\Element\NodeElement
     * @throws ExpectationException
     */
    private function get_module_content_node_by_html_content($modname, $html) {
        $html = $this->escape($html);
        $selector = <<<XPATH
//li[contains(concat( " ", @class, " " ), " activity ") and contains(concat( " ", @class, " " ), " $modname ")]
//div[contains(concat( " ", @class, " " ), " no-overflow ")][@data-ally-richcontent]//*[contains(text(), "$html")]
//ancestor::div[contains(concat( " ", @class, " " ), " no-overflow ")][position()=1]
XPATH;
        return $this->find('xpath', $selector);
    }

    /**
     * Check that module with specific html content is annotated.
     *
     * @Then :module with html ":html" is annotated
     *
     * @param string module
     * @param string $html
     */
    public function module_with_html_is_annotated($modname, $html) {
        $node = $this->get_module_content_node_by_html_content($modname, $html);
        if (empty($node)) {
            throw new ExpectationException(
                'Failed to find annotation for module ' . $modname . ' with html ' . $html,
                $this->getSession()
            );
        }
        $annotation = $node->getAttribute('data-ally-richcontent');
        if (strpos($annotation, $modname . ':' . $modname . ':intro') === false) {
            throw new ExpectationException(
                'Annotation is incorrect for for module ' . $modname . ' with html ' . $html . ' - ' . $annotation,
                $this->getSession()
            );
        }
        $wsparams = explode(':', $annotation);
        if (count($wsparams) < 4) {
            throw new ExpectationException('Incorrect number of params in ' . $modname . ' annotation ' . $annotation);
        }
    }

    /**
     * Check that html block with specific title is annotated.
     *
     * @Then html block with title ":title" is annotated
     * @param string $title
     */
    public function html_block_with_title_is_annotated($title) {
        $this->wait_for_pending_js();
        $selectors = [];
        // Boost theme selector < Moodle 4.3.
        $selectors[] = <<<XPATH
            //section[contains(@class, 'block_html')]
            //div//h3[contains(@class, 'card-title')][contains(text(), '$title')]
            /ancestor::section[contains(@class, 'block_html')]
            //div[contains(@class, 'card-text')]
            //div[@data-ally-richcontent]
XPATH;

        // Boost theme selector >= Moodle 4.3.
        $selectors[] = <<<XPATH
            //section[contains(@class, 'block_html')]
            //div//h5[contains(text(), '$title')]
            /ancestor::section[contains(@class, 'block_html')]
            //div[contains(@class, 'card-text')]
            //div[@data-ally-richcontent]
XPATH;

        // Clean theme selector.
        $selectors[] = <<<XPATH
            //div//h2[contains(@id, 'instance')][contains(text(), '$title')]
            /ancestor::div[contains(@class, 'block_html')]
            //div[contains(@class, 'content')]
            //div[@data-ally-richcontent]
XPATH;

        $selector = implode('|', $selectors);
        $node = $this->find('xpath', $selector);
        if (empty($node)) {
            throw new ExpectationException(
                'Failed to find annotation for html block with title "' . $title . '"',
                $this->getSession()
            );
        }
        $annotation = $node->getAttribute('data-ally-richcontent');
        if (strpos($annotation, 'block_html:block_instances:configdata') === false) {
            throw new ExpectationException(
                'Annotation is incorrect for for html block with title "' . $title . '" ' . $annotation,
                $this->getSession()
            );
        }
        $wsparams = explode(':', $annotation);
        if (count($wsparams) < 4) {
            throw new ExpectationException('Incorrect number of params in html block annotation ' . $annotation);
        }
    }

    /**
     * Get html content from annotation.
     *
     * @param string $annotation
     * @return component_content
     */
    private function get_html_content($annotation) {
        $wsparams = explode(':', $annotation);
        if (count($wsparams) < 4) {
            throw new ExpectationException('Incorrect number of params in label annotation ' . $annotation);
        }
        $component = $wsparams[0];
        $table = $wsparams[1];
        $field = $wsparams[2];
        $id = $wsparams[3];
        return local_content::get_html_content(
            $id,
            $component,
            $table,
            $field,
            null
        );
    }

    /**
     * Follow webservice content url for module with specific html content.
     * @When I follow the webservice content url for :module ":html"
     *
     * @param string $module
     * @param string $html
     */
    public function follow_module_ws_url($module, $html) {
        $node = $this->get_module_content_node_by_html_content($module, $html);
        $annotation = $node->getAttribute('data-ally-richcontent');
        $content = $this->get_html_content($annotation);
        $this->getSession()->visit($content->contenturl);
    }

    /**
     * Assert element is in viewport or not.
     *
     * @param \Behat\Mink\Element\NodeElement $node
     * @param boolean $assertin
     * @throws ExpectationException
     */
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
     * Ensure element is either visible or not taking into account viewport.
     *
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
                    'Element is visible and should not be ' . $node->getXpath(),
                    $this->getSession()
                );
            }
        }

        $this->assert_element_in_viewport_or_not($node, $visible);
    }

    /**
     * Check that element is visible and in viewport.
     *
     * @Then /^the "(?P<selector_string>[^"]*)" element "(?P<element_string>[^"]*)" is visible and in viewport$/
     * @param string $selectortype
     * @param string $element
     * @throws ExpectationException
     */
    public function ensure_element_is_visible_and_in_viewport($selectortype, $element) {
        $this->ensure_element_visible_or_not($element, $selectortype, true);
    }

    /**
     * Check that element is not visible or not in viewport.
     *
     * @Then /^the "(?P<selector_string>[^"]*)" element "(?P<element_string>[^"]*)" is not visible or not in viewport$/
     * @param string $selectortype
     * @param string $element
     * @throws ExpectationException
     */
    public function ensure_element_is_not_visible_or_not_in_viewport($selectortype, $element) {
        $this->ensure_element_visible_or_not($element, $selectortype, false);
    }

    /**
     * Check module with content is not visible or not in viewport.
     *
     * @Then the :module with html content ":content" is visible and in viewport
     * @param string $module
     * @param string $content
     */
    public function ensure_module_with_content_visible_and_in_viewport($module, $content) {
        $mod = $this->get_module_content_node_by_html_content($module, $content);
        if (!$mod->isVisible()) {
            throw new ExpectationException(
                $module . ' is not visible and should be: ' . $mod->getXpath(),
                $this->getSession()
            );
        }
        $this->assert_element_in_viewport_or_not($mod, true);
    }

    /**
     * Check module with content is not visible or not in viewport.
     *
     * @Then the :module with html content ":content" is not visible or not in viewport
     * @param string $module
     * @param string $content
     */
    public function ensure_module_with_content_not_visible_or_not_in_viewport($module, $content) {
        $mod = $this->get_module_content_node_by_html_content($module, $content);
        if (!$mod->isVisible()) {
            return; // Not visible so no need to check viewport.
        }
        $this->assert_element_in_viewport_or_not($mod, false);
    }

    /**
     * Get content node by it's container parent node id and node expected tag.
     *
     * @param string $elementid
     * @param string $elementtag
     * @return \Behat\Mink\Element\NodeElement
     * @throws ExpectationException
     */
    private function get_content_node_by_parent_id_and_tag($elementid, $elementtag) {
        $selector = <<<XPATH
//div[@id="$elementid"]
//div[contains(concat( " ", @class, " " ), " no-overflow ")]
//{$elementtag}[@data-ally-richcontent]
XPATH;

        return $this->find('xpath', $selector);
    }

    /**
     * Module element is annotated.
     *
     * @Then /^"(?P<_module_name>[^"]*)" "(?P<_element_id>[^"]*)" content is annotated on "(?P<_element_tag>[^"]*)" tag$/
     *
     * @param string $modname
     * @param string $elementid
     * @param string $elementtag
     */
    public function module_element_is_annotated($modname, $elementid, $elementtag) {
        $node = $this->get_content_node_by_parent_id_and_tag($elementid, $elementtag);
        if (empty($node)) {
            throw new ExpectationException(
                'Failed to find annotation for module ' . $modname . ' with element id ' . $elementid,
                $this->getSession()
            );
        }
        $annotation = $node->getAttribute('data-ally-richcontent');
        if (strpos($annotation, "{$modname}:{$modname}:{$elementid}") === false) {
            throw new ExpectationException(
                'Annotation is incorrect for for module ' . $modname . ' with element id  ' . $elementid . ' - ' . $annotation,
                $this->getSession()
            );
        }
        $wsparams = explode(':', $annotation);
        if (count($wsparams) < 4) {
            throw new ExpectationException('Incorrect number of params in assign annotation ' . $annotation);
        }
    }

    /**
     * Skip scenario with reason.
     *
     * @When /^I skip because "(?P<reason_string>[^"]*)" \(filter_ally\)$/
     */
    public function skip_with_reason($reason) {
        throw new SkippedException($reason);
    }
}
