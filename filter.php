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

require_once(__DIR__.'/../../mod/forum/lib.php');

use filter_ally\renderables\wrapper;
use tool_ally\local_file;

/**
 * Filter for processing file links for Ally accessibility enhancements.
 * @author    Guy Thomas <gthomas@moodlerooms.com>
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
        $cm = $instances[$forumid];
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
     * Map file resource moduleid to pathname hash.
     * @param $course
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function map_resource_file_paths_to_pathhash($course) {
        global $DB, $PAGE;

        if (!$this->is_course_page() && $PAGE->pagetype !== 'site-index') {
            return [];
        }

        $modinfo = get_fast_modinfo($course);
        $modules = $modinfo->get_instances_of('resource');
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
            AND component = 'mod_resource'
            AND mimetype IS NOT NULL
            AND filename != '.'
            AND filearea = 'content'";

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
            $pathhashbymoduleid[$moduleid] = $file->pathnamehash;
        }

        return $pathhashbymoduleid;
    }

    /**
     * Set up the filter using settings provided in the admin settings page.
     * Also, get the file resource course module id -> file id mappings.
     *
     * @param moodle_page $page
     * @param context $context
     */
    public function setup($page, $context) {
        global $USER, $COURSE, $CFG;

        if ($page->pagelayout === 'embedded') {
            return;
        }

        // This only requires execution once per request.
        static $jsinitialised = false;
        if (!$jsinitialised) {
            require_once($CFG->libdir.'/filelib.php');

            $modulefilemapping = $this->map_resource_file_paths_to_pathhash($COURSE);
            $assignmentmap = $this->map_assignment_file_paths_to_pathhash();
            $forummap = $this->map_forum_attachment_file_paths_to_pathhash();
            $foldermap = $this->map_folder_file_paths_to_pathhash();
            $jwt = \filter_ally\local\jwthelper::get_token($USER, $COURSE->id);
            $coursecontext = context_course::instance($COURSE->id);
            $canviewfeedback = has_capability('filter/ally:viewfeedback', $coursecontext);
            $candownload = has_capability('filter/ally:viewdownload', $coursecontext);

            $modulemaps = [
                'file_resources' => $modulefilemapping,
                'assignment_files' => $assignmentmap,
                'forum_files' => $forummap,
                'folder_files' => $foldermap,
            ];
            $json = json_encode($modulemaps);

            $script = <<<EOF
            <script>
                var ally_module_maps = $json;
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
            $amdargs = [$jwt, $configvars, $canviewfeedback, $candownload];
            $page->requires->js_call_amd('filter_ally/main', 'init', $amdargs);
            $jsinitialised = true;
        }
    }

    /**
     * Process file url for file components.
     * @param string $url
     * @return void|array
     */
    private function process_url($url) {
        $regex = '/(?:.*)pluginfile\.php(?:\?file=|)(?:\/|%2F)(\d*?)(?:\/|%2F)(.*)$/';
        $matches = [];
        $matched = preg_match($regex, $url, $matches);
        if (!$matched) {
            return;
        }
        $contextid = $matches[1];
        if (strpos($matches[2], '%2F') !== false) {
            $del = '%2F';
        } else {
            $del = '/';
        }
        $arr = explode($del, $matches[2]);
        $component = urldecode(array_shift($arr));
        if (count($arr) === 2) {
            $filearea = array_shift($arr);
            $itemid = 0;
            $filename = array_shift($arr);
        } else if (count($arr) === 3) {
            $filearea = array_shift($arr);
            $itemid = array_shift($arr);
            $filename = array_shift($arr);
        } else if ($component === 'question' ) {
            $filearea = array_shift($arr);
            array_shift($arr); // Remove previewcontextid.
            array_shift($arr); // Remove previewcomponent.
            $itemid = array_shift($arr);
            $filename = array_shift($arr);
        } else {
            $filearea = array_shift($arr);
            $itemid = array_shift($arr);
            $filename = implode($arr, '/');
        }

        return [
            $contextid,
            $component,
            $filearea,
            $itemid,
            $filename
        ];
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
                if (is_object($element->attributes)
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
     * Filters the given HTML text, looking for links pointing to files so that the file id data attribute can
     * be injected.
     *
     * @param $text HTML to be processed.
     * @param $options
     * @return string String containing processed HTML.
     */
    public function filter($text, array $options = array()) {
        global $PAGE;

        if (strpos($text, 'pluginfile.php') === false) {
            // No plugin files to process, so don't do anything expensive.
            return $text;
        }

        if (!isloggedin()) {
            return $text;
        }

        $filesbyareakey = [];

        $supportedcomponents = local_file::list_html_file_supported_components();

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true); // Required for HTML5.
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $text);
        libxml_clear_errors(); // Required for HTML5.
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
                list($contextid, $component, $filearea, $itemid, $filename) = $this->process_url($url);

                if ($contextid === null) {
                    continue;
                }

                $context = context::instance_by_id($contextid);
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

                // Strip params from end of the url .e.g. file.pdf?forcedownload=1.
                $query = strpos($filename, '?');
                if ($query) {
                    $filename = substr($filename, 0, $query);
                }
                // Strip additional params from end of the url .e.g. ?file=...&forcedownload=1.
                $query = strpos($filename, '&');
                if ($query) {
                    $filename = substr($filename, 0, $query);
                }

                $filename = urldecode($filename);
                $filearea = urldecode($filearea);

                $itempath = "/$contextid/$component/$filearea/$itemid";
                $filepath = "$itempath/$filename";

                $areakey = sha1($itempath);
                $pathhash = sha1($filepath);

                if (!isset($filesbyareakey[$areakey])) {
                    $files = local_file::iterator();
                    /** @var stored_file[] $files */
                    $files = $files->in_context($context)->with_component($component)->with_filearea($filearea);
                    $files = $files->with_itemid($itemid);
                    $filekeys = [];
                    foreach ($files as $file) {
                        $key = $file->get_pathnamehash();
                        $filekeys[$key] = true;
                    }
                    unset($files);
                    $filesbyareakey[$areakey] = $filekeys;
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

                // Build a regex to cope with void tags closed by /> or >.
                if (substr($html, -2) === '/>') {
                    $stripclosingtag = substr($html, 0, strlen($html) - 2);
                } else {
                    $stripclosingtag = substr($html, 0, strlen($html) - 1);
                }

                $replaceregex = '~'.preg_quote($stripclosingtag, '~').'(?:\s*|)(?:>|/>)~m';

                if ($component == 'mod_folder') {
                    $ampencodedurl = str_replace('&', '&amp;', $url);
                    $replaceregex = '/<a href="' . preg_quote($ampencodedurl, '/') . '">.*?<\/a>/';
                }
                $text = preg_replace($replaceregex, $wrapped, $text);
            }
        }

        // Remove temporary processed flags.
        $text = str_replace('#P#', '', $text);

        return $text;
    }
}
