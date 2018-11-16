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
 * @author    Guy Thomas <osdev@blackboard.com>
 * @package   filter_ally
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../mod/forum/lib.php');

use filter_ally\renderables\wrapper;
use tool_ally\cache;
use tool_ally\local_file;
use tool_ally\local_content;
use tool_ally\models\pluginfileurlprops;

/**
 * Filter for processing file links for Ally accessibility enhancements.
 * @author    Guy Thomas <osdev@blackboard.com>
 * @package   filter_ally
 * @copyright Copyright (c) 2017 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_ally extends moodle_text_filter {

    /**
     * @var array File ids (path hashes) of all processed files by url.
     */
    private static $fileidsbyurl = [];

    /**
     * Constants for identifying html element types and 'ally-'.$type.'-wrapper' usage.
     */
    const ANCHOR = 'anchor';
    const IMAGE  = 'image';

    /**
     * Are we on a course page ? (not course settings, etc. The actual course page).
     * @return bool
     * @throws coding_exception
     */
    protected function is_course_page() {
        $path = parse_url(qualified_me())['path'];
        return (bool) preg_match('~/course/view.php$~', $path);
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
            $fullpath = $cm->context->id.'/'.$component.'/'.$filearea.'/'.
                $file->get_itemid().'/'.
                $file->get_filepath().'/'.
                $file->get_filename();
            $fullpath = str_replace('///', '/', $fullpath);
            $map[$fullpath] = $file->get_pathnamehash();
        }
        return $map;
    }

    /**
     * Get course module for specific forum in current course.
     * @param int $forumid
     * @return cm_info
     */
    protected function get_forum_cm($forumid) {
        global $COURSE;
        $modinfo = get_fast_modinfo($COURSE);
        $instances = $modinfo->get_instances_of('forum');
        $cm = isset($instances[$forumid]) ? $instances[$forumid] : null;
        return $cm;
    }

    /**
     * Return file map for forum.
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_forum_attachment_file_paths_to_pathhash() {
        global $PAGE, $COURSE, $DB;
        $map = [];
        $cm = false;

        if ($COURSE->format === 'social') {
            if ($forum = forum_get_course_forum($COURSE->id, 'social')) {
                $cm = $this->get_forum_cm($forum->id);
            }
        } else if (in_array($PAGE->pagetype, ['mod-forum-view', 'mod-forum-discuss'])) {
            $cmid = optional_param('id', false, PARAM_INT);
            if ($cmid) {
                list($course, $cm) = get_course_and_cm_from_cmid($cmid);
                unset($course);
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
            $map = $this->get_cm_file_map($cm, 'mod_forum', 'attachment', 'image%');
        }

        return $map;
    }

    /**
     * Map file paths to pathname hash.
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
            list($course, $cm) = get_course_and_cm_from_cmid($cmid);
            unset($course);
            $map = $this->get_cm_file_map($cm, 'mod_assign', 'introattachment');
        }

        return $map;
    }

    /**
     * Map folder file paths to pathname hash.
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function map_folder_file_paths_to_pathhash() {
        global $PAGE;
        $map = [];

        if ($PAGE->pagetype === 'mod-folder-view') {
            $cmid = optional_param('id', false, PARAM_INT);
            if ($cmid === false) {
                return $map;
            }
            list($course, $cm) = get_course_and_cm_from_cmid($cmid);
            unset($course);
            /** @var cm_info $cm */
            $cm;
            $fs = get_file_storage();
            $files = $fs->get_area_files($cm->context->id, 'mod_folder', 'content');
            foreach ($files as $file) {
                if ($file->is_directory()) {
                    continue;
                }
                $fullpath = $cm->context->id.'/mod_folder/content/'.
                    $file->get_itemid().'/'.
                    $file->get_filepath().
                    $file->get_filename();
                $fullpath = str_replace('//', '/', $fullpath);
                $map[$fullpath] = $file->get_pathnamehash();
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
            list($course, $cm) = get_course_and_cm_from_cmid($cmid);
            unset($course);
            $map = $this->get_cm_file_map($cm, 'mod_glossary', 'attachment');
        }

        return $map;
    }

    protected function map_course_module_file_paths_to_pathhash($course, $modname) {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $modules = $modinfo->get_instances_of($modname);
        if (empty($modules)) {
            return [];
        }

        $contextsbymoduleid = [];
        $moduleidsbycontext = [];
        foreach ($modules as $modid => $module) {
            if ($module->uservisible) {
                $contextsbymoduleid[$module->id] = $module->context->id;
                $moduleidsbycontext[$module->context->id] = $module->id;
            }
        }

        if (empty($contextsbymoduleid)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($contextsbymoduleid);

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
     * @param $course
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function map_resource_file_paths_to_pathhash($course) {
        global $PAGE;

        if (!$this->is_course_page() && $PAGE->pagetype !== 'site-index') {
            return [];
        }

        return $this->map_course_module_file_paths_to_pathhash($course, 'resource');
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

        if ($PAGE->pagetype === 'mod-lesson-view' || $PAGE->pagetype === 'mod-lesson-continue') {
            $cmid = optional_param('id', false, PARAM_INT);
            if ($cmid === false) {
                return $map;
            }
            list($course, $cm) = get_course_and_cm_from_cmid($cmid);
            unset($course);
            $map['page_contents'] = $this->get_cm_file_map($cm, 'mod_lesson', 'page_contents');
            $map['page_answers'] = $this->get_cm_file_map($cm, 'mod_lesson', 'page_answers');
            $map['page_responses'] = $this->get_cm_file_map($cm, 'mod_lesson', 'page_responses');
        }

        return $map;
    }

    /**
     * Section ids hashed by section-numbers.
     * @return array
     */
    protected function map_sections_to_ids() {
        global $PAGE, $COURSE;

        $sectionmap = [];
        if (strpos($PAGE->pagetype, 'course-view-') === 0) {

            $component = local_content::component_instance('course');
            $sections = $component->get_course_section_summary_rows($COURSE->id);

            foreach ($sections as $section) {
                $sectionmap['section-'.$section->section] = intval($section->id);
            }
        }

        return $sectionmap;
    }

    /**
     * Set up the filter using settings provided in the admin settings page.
     * Also, get the file resource course module id -> file id mappings.
     *
     * @param moodle_page $page
     * @param context $context
     */
    public function setup($page, $context) {
        global $USER, $COURSE, $CFG, $PAGE, $DB;

        if ($page->pagelayout === 'embedded') {
            return;
        }

        if ($PAGE->pagetype === 'admin-setting-additionalhtml' || $PAGE->pagetype === 'admin-search') {
            return;
        }

        // This only requires execution once per request.
        static $jsinitialised = false;
        if (!$jsinitialised) {

            $sectionmap = $this->map_sections_to_ids();
            $sectionjson = json_encode($sectionmap);
            $annotationmaps = json_encode(local_content::annotation_maps($COURSE->id));

            require_once($CFG->libdir.'/filelib.php');

            // Note, we only have to build maps for modules that don't pass their file containing content
            // through the filter.
            $modulefilemapping = $this->map_resource_file_paths_to_pathhash($COURSE);
            $assignmentmap = $this->map_assignment_file_paths_to_pathhash();
            $forummap = $this->map_forum_attachment_file_paths_to_pathhash();
            $foldermap = $this->map_folder_file_paths_to_pathhash();
            $glossarymap = $this->map_glossary_file_paths_to_pathhash();
            $lessonmap = $this->map_lesson_file_paths_to_pathhash();
            $jwt = \filter_ally\local\jwthelper::get_token($USER, $COURSE->id);
            $coursecontext = context_course::instance($COURSE->id);
            $canviewfeedback = has_capability('filter/ally:viewfeedback', $coursecontext);
            $candownload = has_capability('filter/ally:viewdownload', $coursecontext);

            $modulemaps = [
                'file_resources' => $modulefilemapping,
                'assignment_files' => $assignmentmap,
                'forum_files' => $forummap,
                'folder_files' => $foldermap,
                'glossary_files' => $glossarymap,
                'lesson_files' => $lessonmap
            ];
            $filejson = json_encode($modulemaps);

            $script = <<<EOF
            <script>
                var ally_module_maps = $filejson;
                var ally_section_maps = $sectionjson;
                var ally_annotation_maps = $annotationmaps;
            </script>
EOF;

            if (!isset($CFG->additionalhtmlfooter)) {
                $CFG->additionalhtmlfooter = '';
            }
            // Note, we have to put the module maps into the footer instead of passing them into the amd module as an
            // argument. If you pass large amounts of data into the amd arguments then it throws a debug error.
            $CFG->additionalhtmlfooter .= $script;

            $config = get_config('tool_ally');
            // We only want to send these config vars - we don't want to be sending security sensitive stuff like the shared secret!
            $configvars = (object) [
                'adminurl' => !empty($config->adminurl) ? $config->adminurl : null,
                'pushurl' => !empty($config->pushurl) ? $config->pushurl : null,
                'clientid' => !empty($config->clientid) ? $config->clientid : null
            ];

            $params = new stdClass();
            if (strpos($PAGE->pagetype, 'mod-lesson') !== false) {
                $pageid = optional_param('pageid', null, PARAM_INT);
                $answerid = optional_param('answerid', null, PARAM_INT);
                $params->answerid = $answerid;
                $params->pageid = $pageid;
            }
            if (strpos($PAGE->pagetype, 'mod-book') !== false) {
                $chapterid = optional_param('chapterid', null, PARAM_INT);
                if ($chapterid === null) {
                    $cmid = optional_param('id', null, PARAM_INT);
                    if ($cmid) {
                        list ($course, $cm) = get_course_and_cm_from_cmid($cmid);
                        $bookid = $cm->instance;
                        // Get first chapter id for book
                        $sql = 'SELECT min(id) FROM {book_chapters} WHERE bookid = ?';
                        $chapterid = $DB->get_field_sql($sql, [$bookid]);
                    }
                }
                $params->chapterid = $chapterid;
            }
            
            $amdargs = [$jwt, $configvars, $canviewfeedback, $candownload, $COURSE->id, $params];
            $page->requires->js_call_amd('filter_ally/main', 'init', $amdargs);
            $jsinitialised = true;
        }
    }

    /**
     * Verifies and fixes the text if the filter was found to have been already applied.
     * This has been found to be a fix for answers in the lesson module that get stripped off data attrs.
     * @param string $type
     * @param DOMElement $element
     * @param string $text
     * @return bool|string false if filter not applied, string with fixed text otherwise.
     */
    private function verify_and_fix_if_applied($type, DOMElement $element, $text) {
        $feedbackfound = false;
        if ($element->parentNode->tagName === 'span'
            && $element->parentNode->getAttribute('class') === 'filter-ally-wrapper ally-'.$type.'-wrapper') {

            $feedbacknodes = $element->parentNode->getElementsByTagName('span');
            foreach ($feedbacknodes as $feedbacknode) {
                if (is_object($feedbacknode->attributes)
                    && is_object($feedbacknode->attributes->getNamedItem('data-file-id'))
                    && is_object($feedbacknode->attributes->getNamedItem('data-file-url'))) {
                    $feedbackfound = true;
                    break;
                }
            }

            // Feedback placeholders and feedback data has been found, continue.
            if ($feedbackfound) {
                return $text;
            }
        } else {
            return false;
        }

        if (!is_object($element->attributes) || !is_object($element->attributes->getNamedItem('href'))) {
            return false;
        }
        $href = $element->attributes->getNamedItem($type === self::ANCHOR ? 'href' : 'src')->nodeValue;
        if (strpos($href, 'pluginfile.php') !== false) {
            if (!empty(self::$fileidsbyurl[$href])) {
                // Feedback placeholders have no data attributes. Let's try to fill them again.
                $spanclasses = ['ally-feedback', 'ally-download'];
                $linkpos = strpos($text, $href); // Link position.
                foreach ($spanclasses as $spanclass) {
                    $classpos = strpos($text, $spanclass, $linkpos); // Class position.
                    if ($classpos === false) {
                        continue;
                    }
                    $classend = $classpos + strlen($spanclass) + 1; // Class definition end.
                    $strtomatch = substr($text, $linkpos, $classend - $linkpos);
                    $strforreplacement = substr($text, $linkpos, $classpos - $linkpos);

                    $strforreplacement .= $spanclass.'" ';
                    $strforreplacement .= 'data-file-id="'.self::$fileidsbyurl[$href].'" ';
                    $strforreplacement .= 'data-file-url="'.$href.'"';
                    $text = str_replace($strtomatch, $strforreplacement, $text);
                }
                return $text;
            } else {
                return false; // Pathhash has not been processed.
            }
        } else {
            return false; // No applicable links found.
        }
    }

    /**
     * Where supported, apply content annotation.
     *
     * @param string $text
     */
    private function apply_content_annotation($text) {

        $annotation = local_content::get_annotation($this->context);
        if (empty($annotation)) {
            return $text;
        }

        $pattern = '/\>/mU';

        // Some modules will not send a single div node or have several nodes for filtering.
        // We need to add a parent div when such setup is found.
        $doc = local_content::build_dom_doc($text);
        if (!$doc) {
            return $text;
        }
        $bodynode = $doc->getElementsByTagName('body')->item(0);
        $shouldwrap = $bodynode->childNodes->length > 1;
        if (!$shouldwrap && $bodynode->childNodes->length === 1) {
            $node = $bodynode->childNodes->item(0);
            $shouldwrap = $node->tagName !== 'div' ||
                ($node->tagName === 'div' && strpos($node->getAttribute('class'), 'no-overflow') === false);
        }

        if ($shouldwrap) {
            $text = "<div class=\"no-overflow\">{$text}</div>";
        }

        $text = preg_replace ( $pattern , ' data-ally-richcontent = "'.$annotation.'" >' , $text , 1);

        return $text;
    }

    /**
     * @param $pluginfileurl
     * @return array|null
     */
    private function process_url($pluginfileurl) {
        $urlprops = local_file::get_fileurlproperties($pluginfileurl);
        if (empty($urlprops)) {
            return null;
        }
        return $urlprops->to_list();
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
        global $PAGE;

        $text = $this->apply_content_annotation($text);

        if (strpos($text, 'pluginfile.php') === false) {
            // No plugin files to process, so don't do anything expensive.
            return $text;
        }

        if (!isloggedin()) {
            return $text;
        }

        $filesbyareakey = [];

        $supportedcomponents = local_file::list_html_file_supported_components();

        $doc = local_content::build_dom_doc($text);
        if (!$doc) {
            return $text;
        }

        $elements = [];
        $results = $doc->getElementsByTagName('a');
        foreach ($results as $result) {
            if (!is_object($result->attributes) || !is_object($result->attributes->getNamedItem('href'))) {
                continue;
            }
            $href = $result->attributes->getNamedItem('href')->nodeValue;
            if (strpos($href, 'pluginfile.php') !== false) {
                $elements[] = (object) [
                    'type' => 'a',
                    'url' => $href,
                    'result' => $result
                ];
            }
        }
        $results = $doc->getElementsByTagName('img');
        foreach ($results as $result) {
            if (!is_object($result->attributes) || !is_object($result->attributes->getNamedItem('src'))) {
                continue;
            }
            $src = $result->attributes->getNamedItem('src')->nodeValue;
            if (strpos($src, 'pluginfile.php') !== false) {
                $elements[] = (object) [
                    'type' => 'img',
                    'url' => $src,
                    'result' => $result
                ];
            }
        }
        foreach ($elements as $key => $element) {
            $url = $element->url;

            if (strpos($url, 'pluginfile.php') !== false) {

                $urlcomps = $this->process_url($url);
                if (empty($urlcomps)) {
                    continue;
                }
                list($contextid, $component, $filearea, $itemid, $filename) = $urlcomps;

                if ($component === 'mod_glossary' && $filearea === 'attachment') {
                    // We have to do this with JS as the DOM needs rewriting.
                    continue;
                }

                if ($contextid === null) {
                    continue;
                }

                $context = context::instance_by_id($contextid, IGNORE_MISSING);
                if (!$context) {
                    // The context couldn't be found (perhaps this is a copy/pasted url pointing at old deleted content). Move on.
                    continue;
                }

                $blacklistedcontexts = [
                    CONTEXT_USER,
                    CONTEXT_COURSECAT,
                    CONTEXT_SYSTEM
                ];
                if (in_array($context->contextlevel, $blacklistedcontexts)) {
                    continue;
                }
                $canviewfeedback = has_capability('filter/ally:viewfeedback', $context);
                $candownload = has_capability('filter/ally:viewdownload', $context);
                if (!$canviewfeedback && !$candownload) {
                    continue;
                }

                if (!in_array($component, $supportedcomponents)) {
                    $canviewfeedback = false;
                }

                if ($component === 'mod_page' && $filearea === 'content') {
                    $itemid = 0;
                }

                if ($component === 'mod_lesson') {
                    $verifiedresult = $this->verify_and_fix_if_applied(
                        $element->type === 'a' ? self::ANCHOR : self::IMAGE, $element->result, $text);
                    if ($verifiedresult !== false) {
                        $text = $verifiedresult;
                        continue;
                    }
                }

                $itempath = "/$contextid/$component/$filearea/$itemid";
                $filepath = "$itempath/$filename";

                $areakey = sha1($itempath);
                $pathhash = sha1($filepath);

                if (!isset($filesbyareakey[$areakey])) {
                    if (!defined('ALLY_OMITCACHE') && $keys = cache::instance()->get($areakey)) {
                        $filesbyareakey[$areakey] = $keys;
                    } else {
                        $files = local_file::iterator();
                        /** @var stored_file[] $files */
                        $files = $files->in_context($context)->with_component($component)->with_filearea($filearea);
                        $files = $files->with_itemid($itemid);
                        $filekeys = [];
                        foreach ($files as $file) {
                            $key = $file->get_pathnamehash();
                            $filekeys[$key] = true;
                        }
                        $filesbyareakey[$areakey] = $filekeys;
                        cache::instance()->set($areakey, $filekeys);
                        unset($files);
                    }
                }

                $filesbypathhash = $filesbyareakey[$areakey];
                if (!isset($filesbypathhash[$pathhash])) {
                    // Assume that this file should not be processed - i.e. not authored by a teacher / manager, etc.
                    continue;
                }

                // Store the path hash in case it's needed again.
                self::$fileidsbyurl[$url] = $pathhash;

                $html = $doc->saveHTML($element->result);
                $type = $element->type;

                /** @var filter_ally_renderer $renderer */
                $renderer = $PAGE->get_renderer('filter_ally');
                $wrapper = new wrapper();
                $wrapper->fileid = $pathhash;
                // Flag html as processed with #P# so that it doesn't get hit again with multiples of the same link or image.
                $wrapper->html = str_replace('<'.$type, '<'.$type.'#P#', $html);
                $wrapper->url = $url;
                $wrapper->candownload = $candownload;
                $wrapper->canviewfeedback = $canviewfeedback;
                $wrapper->isimage = $type === 'img';
                $wrapped = $renderer->render_wrapper($wrapper);

                if ($component === 'mod_folder' && $filearea !== 'intro') {
                    $ampencodedurl = str_replace('&', '&amp;', $url);
                    $replaceregex = '/<a href="' . preg_quote($ampencodedurl, '/') . '">.*?<\/a>/';
                } else {
                    // To cope with void tags closed by /> or >.
                    if (substr($html, -2) === '/>') {
                        $htmltosrch = substr($html, 0, strlen($html) - 2);
                    } else {
                        $htmltosrch = substr($html, 0, strlen($html) - 1);
                    }
                    // Replace quotes and single quotes with alternatives.
                    // This is to cope with the fact that in some cases $doc->saveHTML swaps single quotes to double
                    // quotes.
                    $htmltosrch = preg_quote($htmltosrch, '~');
                    $pattern = '/(\'|"|&quot;)/';
                    $htmltosrch = preg_replace($pattern, '(\'|"|&quot;)', $htmltosrch);
                    $replaceregex = '~'.$htmltosrch.'(?:\s*|)(?:>|/>)~m';

                }
                $text = preg_replace($replaceregex, $wrapped, $text);
            }
        }

        // Remove temporary processed flags.
        $text = str_replace('#P#', '', $text);

        return $text;
    }
}
