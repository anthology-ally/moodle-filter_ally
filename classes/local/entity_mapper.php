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
 * @author    Guy Thomas
 * @package   filter_ally
 * @copyright Copyright (c) 2017 Open LMS / 2025 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_ally\local;

require_once(__DIR__.'/../../../../mod/forum/lib.php');

use tool_ally\local_file;
use tool_ally\local_content;
use tool_ally\logging\logger;
use cm_info;
use stdClass;
use \core\exception\coding_exception;

/**
 * Class for generating module maps.
 * @author    Guy Thomas
 * @package   filter_ally
 * @copyright Copyright (c) 2017 Open LMS / 2025 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entity_mapper {

    /**
     * @var object The course object.
     */
    private $course;

    /**
     * @var array course ids for which we are currently annotating.
     */
    private static $isannotating = [];

    /**
     * Constructor.
     * @param stdClass|int $course course instance or courseid
     * @throws moodle_exception
     */
    public function __construct($course) {
        if ($course instanceof stdClass) {
            $this->course = $course;
            return;
        }
        if (!is_number($course)) {
            throw new coding_exception('Invalid course id');
        }
        $this->course = get_course($course);
    }

    /**
     * Are we on a course page ? (not course settings, etc. The actual course page).
     * @return bool
     * @throws coding_exception
     */
    protected function is_course_page() {
        $path = parse_url(qualified_me())['path'];
        return preg_match('~/course/view.php$~', $path) || preg_match('~/course/section.php~', $path);
    }

    /**
     * Get file map for specific course module, component and file area.
     * @param cm_info $cm
     * @param string $component
     * @param string $filearea
     * @param string $mimetype
     * @return array
     * @throws coding_exception
     */
    protected function get_cm_file_map(cm_info $cm, $component, $filearea, $mimetype = null) {
        $map = [];

        $files = local_file::iterator();
        /** @var stored_file[] $files */
        $files = $files->in_context($cm->context)->with_component($component)->with_filearea($filearea);

        if (!empty($mimetype)) {
            $files = $files->with_mimetype($mimetype);
        }

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            // Use the logic from moodle_url::make_pluginfile_url() to generate matching URL path.
            $path = [];
            $path[] = $cm->context->id;
            $path[] = $component;
            $path[] = $filearea;
            if ($file->get_itemid() !== null) {
                $path[] = $file->get_itemid();
            }
            $fullpath = implode('/', $path) . $file->get_filepath() . $file->get_filename();

            $map[$fullpath] = $file->get_pathnamehash();
        }
        return $map;
    }

    /**
     * Get course module for specific forum in current course.
     * @param int $forumid
     * @param object $course
     * @return cm_info
     */
    protected function get_forum_cm($forumid) {
        $modinfo = get_fast_modinfo($this->course);
        $instances = $modinfo->get_instances_of('forum');
        $cm = isset($instances[$forumid]) ? $instances[$forumid] : null;
        return $cm;
    }

    /**
     * Return file map for forum.
     * @param object $course The course object
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_forum_attachment_file_paths_to_pathhash() {
        global $PAGE, $DB;
        $map = [];
        $cm = false;
        if ($this->course->format === 'social') {
            if ($forum = forum_get_course_forum($this->course->id, 'social')) {
                $cm = $this->get_forum_cm($forum->id);
            }
        } else if (in_array($PAGE->pagetype, ['mod-forum-view', 'mod-forum-discuss'])) {
            $cmid = optional_param('id', false, PARAM_INT);
            if ($cmid) {
                [$coursetemp, $cm] = get_course_and_cm_from_cmid($cmid);
                unset($coursetemp);
            } else {
                $forumid = optional_param('forum', false, PARAM_INT);
                if (!$forumid) {
                    $forumid = optional_param('f', false, PARAM_INT);
                }
                if (!$forumid) {
                    $discussionid = optional_param('d', false, PARAM_INT);
                    if ($discussionid) {
                        $forumid = $DB->get_field('forum_discussions', 'forum', ['id' => $discussionid]);
                    }
                }
                if ($forumid) {
                    $cm = $this->get_forum_cm($forumid);
                }
            }
        }

        if (!empty($cm)) {
            $map = $this->get_cm_file_map($cm, 'mod_forum', 'attachment');
        }

        return $map;
    }

    /**
     * Map file paths to pathname hash.
     * @param object $course The course object
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_assignment_file_paths_to_pathhash() {
        global $PAGE;
        $map = [];

        if ($PAGE->pagetype === 'mod-assign-view') {
            $cmid = optional_param('id', false, PARAM_INT);
            if ($cmid === false) {
                return $map;
            }
            [$coursetemp, $cm] = get_course_and_cm_from_cmid($cmid);
            unset($coursetemp);
            $map = $this->get_cm_file_map($cm, 'mod_assign', 'introattachment');
        }

        return $map;
    }

    /**
     * Map folder file paths to pathname hash.
     * @param object $course The course object
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_folder_file_paths_to_pathhash() {
        global $PAGE, $DB;
        $map = [];

        if ($PAGE->pagetype === 'mod-folder-view') {
            $cmid = optional_param('id', false, PARAM_INT);
            if ($cmid === false) {
                return $map;
            }
            [$coursetemp, $cm] = get_course_and_cm_from_cmid($cmid);
            unset($coursetemp);
            /** @var cm_info $cm */
            $cm;
            $map = $this->get_cm_file_map($cm, 'mod_folder', 'content');
        } else if ((stripos($PAGE->pagetype, 'course-view') === 0) || $PAGE->pagetype === 'site-index') {
            $folders = $DB->get_records('folder', ['course' => $this->course->id]);
            $map = [];
            foreach ($folders as $folder) {
                if (empty($folder->name)) {
                    continue;
                }
                try {
                    [$coursetemp, $cm] = get_course_and_cm_from_instance($folder->id, 'folder');
                    $map = array_merge($map, $this->get_cm_file_map($cm, 'mod_folder', 'content'));
                } catch (\moodle_exception $ex) {
                    // Course module id not valid, component not identified correctly.
                    $context = ['_exception' => $ex];
                    logger::get()->error('logger:moduleidresolutionfailure', $context);
                }
            }
        }

        return $map;
    }

    /**
     * Map file paths to pathname hash.
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_glossary_file_paths_to_pathhash() {
        global $PAGE;
        $map = [];

        if ($PAGE->pagetype === 'mod-glossary-view') {
            $cmid = optional_param('id', false, PARAM_INT);
            if ($cmid === false) {
                return $map;
            }
            [$coursetemp, $cm] = get_course_and_cm_from_cmid($cmid);
            unset($coursetemp);
            $map = $this->get_cm_file_map($cm, 'mod_glossary', 'attachment');
        }

        return $map;
    }

    protected function map_course_module_file_paths_to_pathhash(string $modname) {
        global $DB;

        $modinfo = get_fast_modinfo($this->course);
        $modules = $modinfo->get_instances_of($modname);
        if (empty($modules)) {
            return [];
        }

        $contextsbymoduleid = [];
        $moduleidsbycontext = [];
        foreach ($modules as $modid => $module) {
            try {
                if ($module->uservisible) {
                    $contextsbymoduleid[$module->id] = $module->context->id;
                    $moduleidsbycontext[$module->context->id] = $module->id;
                }
            } catch (Throwable $ex) {
                $context = ['_exception' => $ex];
                logger::get()->error('logger:cmvisibilityresolutionfailure', $context);
            }
        }

        if (empty($contextsbymoduleid)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($contextsbymoduleid);

        $sql = "contextid $insql
            AND component = 'mod_{$modname}'
            AND mimetype IS NOT NULL
            AND filename != '.'";

        $files = $DB->get_records_select('files', $sql, $params, 'contextid ASC, sortorder DESC, id ASC');
        $pathhashbymoduleid = [];
        $contextid = null;
        foreach ($files as $id => $file) {
            if ($file->contextid === $contextid) {
                // We've already got the first file for this contextid.
                continue;
            }
            $contextid = $file->contextid;
            $moduleid = $moduleidsbycontext[$file->contextid];
            if (!isset($pathhashbymoduleid[$moduleid])) {
                $pathhashbymoduleid[$moduleid] = [];
            }
            $pathhashbymoduleid[$moduleid][$file->filearea] = $file->pathnamehash;
        }

        return $pathhashbymoduleid;
    }

    /**
     * Map file resource moduleid to pathname hash.
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function map_resource_file_paths_to_pathhash() {
        global $PAGE;

        if (!$this->is_course_page() && !AJAX_SCRIPT && $PAGE->pagetype !== 'site-index') {
            return [];
        }

        return $this->map_course_module_file_paths_to_pathhash('resource');
    }

    /**
     * Map lesson file paths to path hash.
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_lesson_file_paths_to_pathhash() {
        global $PAGE;
        $map = [];

        // Check if we're in a lesson context (either via PAGE or assume true for web service)
        $islessoncontext = !$PAGE ||
            (isset($PAGE->pagetype) && ($PAGE->pagetype === 'mod-lesson-view' || $PAGE->pagetype === 'mod-lesson-continue'));

        if ($islessoncontext) {
            // For web service context, get all lesson modules for this course
            if (!$PAGE) {
                $map = $this->map_course_module_file_paths_to_pathhash('lesson');
            } else {
                // For page context, get specific lesson module
                $cmid = optional_param('id', false, PARAM_INT);
                if ($cmid !== false) {
                    [$coursetemp, $cm] = get_course_and_cm_from_cmid($cmid);
                    unset($coursetemp);
                    $map['page_contents'] = $this->get_cm_file_map($cm, 'mod_lesson', 'page_contents');
                    $map['page_answers'] = $this->get_cm_file_map($cm, 'mod_lesson', 'page_answers');
                    $map['page_responses'] = $this->get_cm_file_map($cm, 'mod_lesson', 'page_responses');
                }
            }
        }

        return $map;
    }

    /**
     * Section ids hashed by section-numbers.
     * @return array
     */
    protected function map_sections_to_ids() {
        global $PAGE;

        // Ensure we're in a course context (either via PAGE or assume true for web service)
        $iscourseviewpage = !empty($PAGE) && strpos($PAGE->pagetype ?? '', 'course-view-') === 0;
        $iscoursecontext = AJAX_SCRIPT || !$PAGE || $iscourseviewpage;
        if (!$iscoursecontext) {
            return [];
        }

        $component = local_content::component_instance('course');
        $sections = $component->get_course_section_summary_rows($this->course->id);

        $sectionmap = [];
        foreach ($sections as $section) {
            $sectionmap['section-'.$section->section] = intval($section->id);
        }

        return $sectionmap;
    }

    /**
     * Execute a callback with proper global context set
     * @param callable $callback
     * @return mixed
     */
    private function with_global_context($callback) {
        global $COURSE;

        // Store original values
        $originalcourse = $COURSE;

        // Set course context - this is the main thing most methods need
        $COURSE = $this->course;

        try {
            return $callback();
        } finally {
            // Restore original values
            $COURSE = $originalcourse;
        }
    }

    /**
     * Are we currently annotating this course? If so, we should not try to do so again, we can be in a loop.
     *
     * Please remove this when MDL-67405 has been closed, as filters will be disabled from looping.
     *
     * @param $courseid
     * @return bool
     */
    public static function is_annotating($courseid): bool {
        return array_key_exists($courseid, self::$isannotating);
    }

    /**
     * @param $courseid
     */
    public static function start_annotating($courseid): void {
        if (self::is_annotating($courseid)) {
            throw new coding_exception('Can\'t start annotating this course.'
                    . ' Ally filter is already annotating course with id: ' . $courseid);
        }
        self::$isannotating[$courseid] = true;
    }

    /**
     * @param $courseid
     */
    public static function end_annotating($courseid): void {
        if (!self::is_annotating($courseid)) {
            throw new coding_exception('Can\'t end annotating this course.'
                . ' Ally filter was not annotating course with id: ' . $courseid);
        }
        unset(self::$isannotating[$courseid]);
    }

    /**
     * Get maps with proper global context
     * @param int|null $courseid Optional course ID for backward compatibility
     * @return object
     */
    public function get_maps($courseid = null) {
        // If courseid provided and different from constructor, create new instance
        if ($courseid !== null && $courseid != $this->course->id) {
            $mapper = new entity_mapper($courseid);
            return $mapper->get_maps();
        }

        return $this->with_global_context(function() {
            global $CFG;

            $course = $this->course;

            $sectionmaps = $this->map_sections_to_ids();

            // Possible course cache build recursion avoidance, by adding the course id to a static array.
            self::start_annotating($course->id);
            $annotationmaps = local_content::annotation_maps($course->id);
            self::end_annotating($course->id);

            require_once($CFG->libdir.'/filelib.php');

            // Note, we only have to build maps for modules that don't pass their file containing content
            // through the filter.
            $modulefilemapping = $this->map_resource_file_paths_to_pathhash();
            $assignmentmap = $this->map_assignment_file_paths_to_pathhash();
            $forummap = $this->map_forum_attachment_file_paths_to_pathhash();
            $foldermap = $this->map_folder_file_paths_to_pathhash();
            $glossarymap = $this->map_glossary_file_paths_to_pathhash();
            $lessonmap = $this->map_lesson_file_paths_to_pathhash();

            $modulemaps = [
                'file_resources' => $modulefilemapping,
                'assignment_files' => $assignmentmap,
                'forum_files' => $forummap,
                'folder_files' => $foldermap,
                'glossary_files' => $glossarymap,
                'lesson_files' => $lessonmap,
            ];

            return (object) ['modulemaps' => $modulemaps, 'sectionmaps' => $sectionmaps, 'annotationmaps' => $annotationmaps];
        });
    }
}
