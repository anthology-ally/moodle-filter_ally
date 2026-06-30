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
 * Performance tests for the Ally filter.
 *
 * Measures DB read counts across the filter lifecycle to:
 * 1. Establish a baseline cost profile for setup() and filter().
 * 2. Confirm that disabling the filter at the course level eliminates DB reads.
 * 3. Validate that a proposed capability-based early-exit reduces reads.
 * 4. Demonstrate the scaling relationship between course content volume and DB reads.
 *
 * Uses $DB->perf_get_reads() for instrumentation — the same counter Moodle core
 * uses in its own DML tests (lib/dml/tests/dml_test.php::test_queries_counter).
 *
 * @package   filter_ally
 * @author    Anthology Inc.
 * @copyright Copyright (c) 2025 Anthology Inc. and its affiliates
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_ally;

use tool_ally\local_file;
use context_course;
use context_module;

/**
 * Performance tests for the Ally filter.
 *
 * @package   filter_ally
 * @group     filter_ally
 * @group     ally
 * @covers    \filter_ally\text_filter
 */
final class filter_performance_test extends \advanced_testcase {

    /**
     * Create a course populated with activities and files.
     *
     * Builds a realistic course with file resources (representing uploaded PDFs,
     * documents, etc.) and labels with embedded images (representing inline content
     * that passes through the filter). This mirrors what a real course page looks
     * like when the filter processes it.
     *
     * @param int $numresources Number of mod_resource activities to create.
     * @param int $numlabels Number of mod_label activities with embedded files.
     * @return object {course, teacher, student, resources[], files[]}
     */
    private function create_course_with_content(int $numresources = 10, int $numlabels = 5): object {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');

        $this->setUser($teacher);
        $fs = get_file_storage();

        $resources = [];
        for ($i = 0; $i < $numresources; $i++) {
            $resource = $gen->create_module('resource', ['course' => $course->id]);
            $filerecord = [
                'contextid' => context_module::instance($resource->cmid)->id,
                'component' => 'mod_resource',
                'filearea' => 'content',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => "testfile_{$i}.pdf",
            ];
            $fs->create_file_from_string($filerecord, "PDF content {$i}");
            $resources[] = $resource;
        }

        $files = [];
        for ($i = 0; $i < $numlabels; $i++) {
            $label = $gen->create_module('label', ['course' => $course->id]);
            $context = context_module::instance($label->cmid);
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'mod_label',
                'filearea' => 'intro',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => "image_{$i}.png",
            ];
            $file = $fs->create_file_from_string($filerecord, "PNG content {$i}");
            $files[] = $file;
        }

        return (object) [
            'course' => $course,
            'teacher' => $teacher,
            'student' => $student,
            'resources' => $resources,
            'files' => $files,
        ];
    }

    /**
     * Build HTML containing pluginfile.php URLs, simulating the text Moodle
     * passes through the filter for labels with embedded files.
     *
     * @param \stored_file[] $files Files to generate URLs for.
     * @return string HTML with pluginfile.php references.
     */
    private function generate_pluginfile_html(array $files): string {
        $html = '';
        foreach ($files as $file) {
            $url = local_file::url($file);
            $html .= '<p>Some content <a href="' . $url . '">Download</a></p>';
            $html .= '<p><img src="' . $url . '" alt="test"/></p>';
        }
        return $html;
    }

    /**
     * Create a fresh text_filter instance and run setup().
     *
     * Each test needs its own filter instance because the filter caches its
     * active/inactive state internally. A fresh instance forces a clean check.
     *
     * @param object $page The $PAGE global.
     * @param \context $context The context to create the filter in.
     * @return text_filter
     */
    private function create_and_setup_filter($page, $context): text_filter {
        $filter = new text_filter($context, []);
        $filter->setup($page, $context);
        return $filter;
    }

    /**
     * Test 1: Establish baseline — DB reads for setup() on a populated course.
     *
     * This measures the total read cost of entity_mapper::get_maps(), which includes:
     * - map_sections_to_ids()
     * - annotation_maps() for every supported component
     * - map_resource_file_paths_to_pathhash()
     * - map_*_file_paths_to_pathhash() for assignments, forums, folders, etc.
     *
     * The read count here is what every single user pays on the first page load
     * in a course (before any caching kicks in).
     */
    public function test_setup_db_reads_baseline(): void {
        global $DB, $PAGE, $COURSE, $CFG;
        $this->resetAfterTest();
        filter_set_global_state('ally', TEXTFILTER_ON);

        $data = $this->create_course_with_content(20, 10);
        $COURSE = $data->course;
        $PAGE->set_url($CFG->wwwroot . '/course/view.php', ['id' => $data->course->id]);
        $PAGE->set_pagetype('course-view-topics');

        // Measure as the teacher (has both capabilities).
        $this->setUser($data->teacher);
        $context = context_course::instance($data->course->id);

        $readsbefore = $DB->perf_get_reads();
        $this->create_and_setup_filter($PAGE, $context);
        $setupreads = $DB->perf_get_reads() - $readsbefore;

        // setup() must perform reads to build maps — this is the cost we want to document.
        $this->assertGreaterThan(0, $setupreads,
            'setup() should perform DB reads to build entity maps');

        // Output for human review when running with --verbose.
        // This is the number we want to see decrease after optimizations.
        fwrite(STDOUT, "\n[PERF BASELINE] setup() reads (teacher, 20 resources + 10 labels): {$setupreads}\n");
    }

    /**
     * Test 2: Establish baseline — DB reads for filter() processing HTML with pluginfile URLs.
     *
     * After setup() has run, each call to filter() processes a text fragment.
     * For fragments containing pluginfile.php URLs, the filter performs:
     * - Context lookups per URL
     * - Capability checks per URL (has_capability calls)
     * - File iterator queries when the area key cache misses
     */
    public function test_filter_db_reads_baseline(): void {
        global $DB, $PAGE, $COURSE, $CFG;
        $this->resetAfterTest();
        filter_set_global_state('ally', TEXTFILTER_ON);

        $data = $this->create_course_with_content(5, 5);
        $COURSE = $data->course;
        $PAGE->set_url($CFG->wwwroot . '/course/view.php', ['id' => $data->course->id]);
        $PAGE->set_pagetype('course-view-topics');

        $this->setUser($data->student);
        $context = context_course::instance($data->course->id);
        $filter = $this->create_and_setup_filter($PAGE, $context);

        $html = $this->generate_pluginfile_html($data->files);

        $readsbefore = $DB->perf_get_reads();
        $filter->filter($html);
        $filterreads = $DB->perf_get_reads() - $readsbefore;

        $this->assertGreaterThan(0, $filterreads,
            'filter() should perform DB reads when processing pluginfile URLs');

        fwrite(STDOUT, "\n[PERF BASELINE] filter() reads (student, 5 files / 10 URLs): {$filterreads}\n");
    }

    /**
     * Test 3: Confirm that filter() does ZERO reads when text has no pluginfile URLs.
     *
     * This validates the existing fast-path optimization (the strpos check).
     * Serves as a sanity check that the filter isn't doing unnecessary work
     * on plain text content.
     */
    public function test_filter_zero_reads_for_plain_text(): void {
        global $DB, $PAGE, $COURSE, $CFG;
        $this->resetAfterTest();
        filter_set_global_state('ally', TEXTFILTER_ON);

        $data = $this->create_course_with_content(5, 0);
        $COURSE = $data->course;
        $PAGE->set_url($CFG->wwwroot . '/course/view.php', ['id' => $data->course->id]);
        $PAGE->set_pagetype('course-view-topics');

        $this->setUser($data->student);
        $context = context_course::instance($data->course->id);
        $filter = $this->create_and_setup_filter($PAGE, $context);

        // Plain text — no pluginfile.php references.
        $plainhtml = '<p>Just some plain text with <strong>formatting</strong> but no files.</p>';

        $readsbefore = $DB->perf_get_reads();
        $result = $filter->filter($plainhtml);
        $filterreads = $DB->perf_get_reads() - $readsbefore;

        // The filter should bail out at the strpos check with zero DB reads.
        $this->assertEquals(0, $filterreads,
            'filter() should perform zero DB reads for text without pluginfile URLs');
    }

    /**
     * Test 4: Confirm filter does zero work when disabled at the course level.
     *
     * When the ally filter is set to TEXTFILTER_OFF for a specific course context,
     * both setup() and filter() should short-circuit. The filter() call should
     * perform exactly zero reads and return text unchanged.
     *
     * This validates the customer's expectation: "if I disable Ally for a course,
     * it should not cause any DB load for that course."
     */
    public function test_zero_filter_reads_when_disabled_for_course(): void {
        global $DB, $PAGE, $COURSE, $CFG;
        $this->resetAfterTest();
        filter_set_global_state('ally', TEXTFILTER_ON);

        $data = $this->create_course_with_content(10, 5);
        $COURSE = $data->course;
        $context = context_course::instance($data->course->id);

        // Explicitly disable the ally filter for this course.
        filter_set_local_state('ally', $context->id, TEXTFILTER_OFF);

        $PAGE->set_url($CFG->wwwroot . '/course/view.php', ['id' => $data->course->id]);
        $PAGE->set_pagetype('course-view-topics');

        $this->setUser($data->student);
        $filter = new text_filter($context, []);

        // Run setup — this should detect the filter is OFF and set filteractive = false.
        // It will still perform a small number of reads for filter_get_active_in_context().
        $readsbefore = $DB->perf_get_reads();
        $filter->setup($PAGE, $context);
        $setupreads = $DB->perf_get_reads() - $readsbefore;

        // Now filter() — this should be completely free.
        $html = $this->generate_pluginfile_html($data->files);
        $readsbefore = $DB->perf_get_reads();
        $filtered = $filter->filter($html);
        $filterreads = $DB->perf_get_reads() - $readsbefore;

        $this->assertEquals(0, $filterreads,
            'filter() must do zero DB reads when ally is disabled for the course');
        $this->assertEquals($html, $filtered,
            'filter() must return text unchanged when ally is disabled for the course');

        fwrite(STDOUT, "\n[PERF DISABLED] setup() reads when filter OFF: {$setupreads}\n");
        fwrite(STDOUT, "[PERF DISABLED] filter() reads when filter OFF: {$filterreads}\n");
    }

    /**
     * Test 5: Contrast reads for teacher vs student on the same course.
     *
     * Teachers have both filter/ally:viewfeedback and filter/ally:viewdownload,
     * so they pay the full setup() cost. Students have only viewdownload, so
     * they also get the full setup — but their filter() may differ due to
     * per-element capability checks skipping feedback placeholders.
     *
     * Users with NEITHER capability (tested in test 7) skip the expensive parts of setup.
     */
    public function test_read_comparison_teacher_vs_student(): void {
        global $DB, $PAGE, $COURSE, $CFG;
        $this->resetAfterTest();
        filter_set_global_state('ally', TEXTFILTER_ON);

        $data = $this->create_course_with_content(10, 5);
        $COURSE = $data->course;
        $PAGE->set_url($CFG->wwwroot . '/course/view.php', ['id' => $data->course->id]);
        $PAGE->set_pagetype('course-view-topics');
        $context = context_course::instance($data->course->id);
        $html = $this->generate_pluginfile_html($data->files);

        // Measure total reads (setup + filter) for the teacher.
        $this->setUser($data->teacher);
        $readsbefore = $DB->perf_get_reads();
        $teacherfilter = $this->create_and_setup_filter($PAGE, $context);
        $teachersetupreads = $DB->perf_get_reads() - $readsbefore;

        $readsbefore = $DB->perf_get_reads();
        $teacherfilter->filter($html);
        $teacherfilterreads = $DB->perf_get_reads() - $readsbefore;

        // Measure total reads (setup + filter) for the student.
        // Need a fresh filter instance since the static $jsinitialised flag persists.
        $this->setUser($data->student);
        $readsbefore = $DB->perf_get_reads();
        $studentfilter = new text_filter($context, []);
        $studentfilter->setup($PAGE, $context);
        $studentsetupreads = $DB->perf_get_reads() - $readsbefore;

        $readsbefore = $DB->perf_get_reads();
        $studentfilter->filter($html);
        $studentfilterreads = $DB->perf_get_reads() - $readsbefore;

        // Both should perform some reads — this documents the current state.
        $this->assertGreaterThan(0, $teachersetupreads);
        $this->assertGreaterThan(0, $studentsetupreads);

        fwrite(STDOUT, "\n[PERF ROLE COMPARISON] Teacher: setup={$teachersetupreads}, filter={$teacherfilterreads}\n");
        fwrite(STDOUT, "[PERF ROLE COMPARISON] Student: setup={$studentsetupreads}, filter={$studentfilterreads}\n");

        $teachertotal = $teachersetupreads + $teacherfilterreads;
        $studenttotal = $studentsetupreads + $studentfilterreads;
        fwrite(STDOUT, "[PERF ROLE COMPARISON] Total: teacher={$teachertotal}, student={$studenttotal}\n");
    }

    /**
     * Test 6: Measure how reads scale with course content volume.
     *
     * Runs the filter against courses of increasing size to show the relationship
     * between content volume and DB reads. This is the most direct evidence for
     * the customer's complaint: courses with many activities cause proportionally
     * more reads.
     */
    public function test_read_scaling_with_content_volume(): void {
        global $DB, $PAGE, $COURSE, $CFG;
        $this->resetAfterTest();
        filter_set_global_state('ally', TEXTFILTER_ON);

        $sizes = [
            'small' => ['resources' => 5, 'labels' => 2],
            'medium' => ['resources' => 20, 'labels' => 10],
            'large' => ['resources' => 50, 'labels' => 25],
        ];

        fwrite(STDOUT, "\n[PERF SCALING] Content volume vs DB reads:\n");

        foreach ($sizes as $label => $counts) {
            $data = $this->create_course_with_content($counts['resources'], $counts['labels']);
            $COURSE = $data->course;
            $PAGE->set_url($CFG->wwwroot . '/course/view.php', ['id' => $data->course->id]);
            $PAGE->set_pagetype('course-view-topics');
            $context = context_course::instance($data->course->id);

            $this->setUser($data->teacher);

            $readsbefore = $DB->perf_get_reads();
            $filter = $this->create_and_setup_filter($PAGE, $context);
            $setupreads = $DB->perf_get_reads() - $readsbefore;

            $html = $this->generate_pluginfile_html($data->files);
            $readsbefore = $DB->perf_get_reads();
            $filter->filter($html);
            $filterreads = $DB->perf_get_reads() - $readsbefore;

            $totalactivities = $counts['resources'] + $counts['labels'];
            $totalreads = $setupreads + $filterreads;
            $readsperactivity = $totalactivities > 0 ? round($totalreads / $totalactivities, 1) : 0;

            fwrite(STDOUT, "  {$label} ({$totalactivities} activities): " .
                "setup={$setupreads}, filter={$filterreads}, total={$totalreads}, " .
                "reads/activity={$readsperactivity}\n");

            $this->assertGreaterThan(0, $totalreads);
        }
    }

    /**
     * Test 7: Verify that a user with no Ally capabilities skips expensive setup.
     *
     * With the capability-based early exit in setup(), a user who has neither
     * filter/ally:viewfeedback nor filter/ally:viewdownload pays only the cost
     * of the filter-active check and capability check in setup() — not the full
     * get_maps() + JWT + AMD initialization pipeline.
     *
     * The filter() method still runs (filteractive remains true) but per-element
     * capability checks skip all wrapping, so output is unchanged.
     */
    public function test_setup_reads_for_user_without_capabilities(): void {
        global $DB, $PAGE, $COURSE, $CFG;
        $this->resetAfterTest();
        filter_set_global_state('ally', TEXTFILTER_ON);

        $data = $this->create_course_with_content(10, 5);
        $COURSE = $data->course;
        $PAGE->set_url($CFG->wwwroot . '/course/view.php', ['id' => $data->course->id]);
        $PAGE->set_pagetype('course-view-topics');
        $context = context_course::instance($data->course->id);

        // Create a user enrolled with a custom role that has NO ally capabilities.
        $nocaprole = $this->getDataGenerator()->create_role(['shortname' => 'nocaprole']);
        $nocapuser = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($nocapuser->id, $data->course->id, $nocaprole);

        // Confirm this user has neither capability.
        $this->setUser($nocapuser);
        $this->assertFalse(has_capability('filter/ally:viewfeedback', $context));
        $this->assertFalse(has_capability('filter/ally:viewdownload', $context));

        // Measure setup reads — should be minimal (filter-active check + capability checks only,
        // no get_maps() or JWT generation).
        $readsbefore = $DB->perf_get_reads();
        $filter = $this->create_and_setup_filter($PAGE, $context);
        $setupreads = $DB->perf_get_reads() - $readsbefore;

        // Measure filter reads — still runs but per-element checks skip all wrapping.
        $html = $this->generate_pluginfile_html($data->files);
        $readsbefore = $DB->perf_get_reads();
        $filtered = $filter->filter($html);
        $filterreads = $DB->perf_get_reads() - $readsbefore;

        fwrite(STDOUT, "\n[PERF NO-CAP USER] setup={$setupreads}, filter={$filterreads}\n");

        // setup() should only perform reads for the filter-active check and capability checks,
        // not the full get_maps() pipeline. A reasonable upper bound is 10 reads.
        $this->assertLessThan(10, $setupreads,
            'setup() should perform minimal reads for users without Ally capabilities');

        // filter() still runs but output should be unchanged since all elements are
        // skipped by per-element capability checks.
        $this->assertEquals($html, $filtered,
            'filter() should return text unchanged for users without Ally capabilities');
    }
}
