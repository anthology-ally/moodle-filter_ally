/**
 * This file is part of Moodle - http://moodle.org/
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Main library.
 *
 * @package
 * @author    Guy Thomas
 * @copyright Copyright (c) 2016 Open LMS / 2023 Anthology Group
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable camelcase */

import $ from 'jquery';
import Templates from 'core/templates';
import {get_strings as getStrings} from 'core/str';
import Ally from 'filter_ally/ally';
import ImageCover from 'filter_ally/imagecover';
import Util from 'filter_ally/util';

class Main {
    #canViewFeedback = false;
    #canDownload = false;
    #courseId;
    #initialised = false;
    #params = {};

    /**
     * Get nodes by xpath.
     * @param {string} xpath
     * @returns {Array}
     */
    #getNodesByXpath(xpath) {
        const expression = window.document.createExpression(xpath);
        const result = expression.evaluate(window.document, XPathResult.ANY_TYPE);
        const nodes = [];
        let node;
        do {
            node = result.iterateNext();
            nodes.push(node);
        } while (node);
        return nodes;
    }

    /**
     * Get single node by xpath.
     * @param {string} xpath
     * @returns {Node}
     */
    #getNodeByXpath(xpath) {
        const expression = window.document.createExpression(xpath);
        const result = expression.evaluate(window.document, XPathResult.FIRST_ORDERED_NODE_TYPE);
        return result.singleNodeValue;
    }

    /**
     * Render template and insert result in appropriate place.
     * @param {object} data
     * @param {string} pathHash
     * @param {node} targetEl
     * @returns {promise}
     */
    #renderTemplate(data, pathHash, targetEl) {
        const dfd = $.Deferred();

        if ($(targetEl).parents('.filter-ally-wrapper').length) {
            // This has already been processed.
            dfd.resolve();
            return dfd.promise();
        }

        // Too expensive to do at module level - this is a course level capability.
        data.canviewfeedback = this.#canViewFeedback;
        data.candownload = this.#canDownload;
        data.html = '<span id="content-target-' + pathHash + '"></span>';

        Templates.render('filter_ally/wrapper', data)
            .done((result) => {
                const presentWrappers = $(targetEl).next().find('span[data-file-id="' + pathHash + '"]');
                if (presentWrappers.length == 0) {
                    $(targetEl).after(result);

                    // We are inserting the module element next to the target as opposed to replacing the
                    // target as we want to ensure any listeners attributed to the module element persist.
                    $('#content-target-' + pathHash).after(targetEl);
                    $('#content-target-' + pathHash).remove();
                }
                dfd.resolve();
            });

        return dfd.promise();
    }

    /**
     * Place holder items that are matched by selector.
     * @param {string} selector
     * @param {string} map
     * @returns {promise}
     */
    #placeHoldSelector(selector, map) {
        const dfd = $.Deferred();
        const length = $(selector).length;
        let c = 0;

        if (!length) {
            dfd.resolve();
        }
        $(selector).each((idx, el) => {

            /**
             * Check that all selectors have been processed.
             */
            const checkComplete = () => {
                if (c === length) {
                    dfd.resolve();
                }
            };

            let url,
                type;

            if ($(el).prop("tagName").toLowerCase() === 'a') {
                url = $(el).attr('href');
                type = 'a';
            } else {
                url = $(el).attr('src');
                type = 'img';
            }
            let regex;
            if (url.indexOf('?') > -1) {
                regex = /pluginfile.php\/(\d*)\/(.*)(\?)/;
            } else {
                regex = /pluginfile.php\/(\d*)\/(.*)/;
            }
            const match = url.match(regex);
            let pathHash;
            if (match) {
                let path = match[1] + '/' + match[2];
                path = decodeURIComponent(path);
                pathHash = map[path];
            }

            if (pathHash === undefined) {
                // Maybe 'slasharguments' setting is disabled for this host.
                // Let's see if the file URI is found in the URL query.
                const query = Util.getQuery(url);
                if (query.file) {
                    const filePath = decodeURIComponent(query.file);
                    regex = /\/(\d*)\/(.*)/;

                    const match = filePath.match(regex);
                    if (match) {
                        let path = match[1] + '/' + match[2];
                        path = decodeURIComponent(path);
                        pathHash = map[path];
                    }
                }
            }

            // Pathhash was definitely not found :( .
            if (pathHash === undefined) {
                c++;
                checkComplete();
                return;
            }

            const data = {
                isimage: type === 'img',
                fileid: pathHash,
                url: url
            };

            this.#renderTemplate(data, pathHash, $(el))
                .done(() => {
                    c++;
                    checkComplete();
                });
        });
        return dfd.promise();
    }

    /**
     * Add place holders for forum module image attachments (note, regular files are covered by php).
     * @param {array} forumFileMapping
     * @returns {promise}
     */
    #placeHoldForumModule(forumFileMapping) {
        const dfd = $.Deferred();
        this.#placeHoldSelector('.forumpost .attachedimages img[src*="pluginfile.php"], ' +
            '.forumpost .body-content-container a[href*="pluginfile.php"]', forumFileMapping)
            .done(() => {
                dfd.resolve();
            });
        return dfd.promise();
    }

    /**
     * Add place holders for assign module additional files.
     * @param {array} assignFileMapping
     * @returns {promise}
     */
    #placeHoldAssignModule(assignFileMapping) {
        const dfd = $.Deferred();
        Util.whenTrue(() => {
            return $('div[id*="assign_files_tree"] .ygtvitem').length > 0;
        }, 10)
            .done(() => {
                this.#placeHoldSelector('div[id*="assign_files_tree"] a[href*="pluginfile.php"]', assignFileMapping);
                dfd.resolve();
            });
        return dfd.promise();
    }

    /**
     * Add place holders for folder module files.
     * @param {array} folderFileMapping
     * @returns {promise}
     */
    #placeHoldFolderModule(folderFileMapping) {
        const dfd = $.Deferred();
        Util.whenTrue(() => {
            return $('.foldertree > .filemanager .ygtvitem').length > 0;
        }, 10)
            .done(() => {
                const unwrappedlinks = '.foldertree > .filemanager span:not(.filter-ally-wrapper) > a[href*="pluginfile.php"]';
                this.#placeHoldSelector(unwrappedlinks, folderFileMapping)
                    .done(() => {
                        dfd.resolve();
                    });
            });
        return dfd.promise();
    }

    /**
     * Add place holders for glossary module files.
     * @param {array} glossaryFileMapping
     * @returns {promise}
     */
    #placeHoldGlossaryModule(glossaryFileMapping) {
        const dfd = $.Deferred();

        // Glossary attachment markup is terrible!
        // The first thing we need to do is rewrite the glossary attachments so that they are encapsulated.
        $('.entry .attachments > br').each((idx, el) => {
            const mainAnchor = $(el).prev('a[href*="pluginfile.php"]');
            mainAnchor.addClass('ally-glossary-attachment');
            const iconAnchor = $(mainAnchor).prev('a[href*="pluginfile.php"]');
            $(el).after('<div class="ally-glossary-attachment-row"></div>');
            const container = $(el).next('.ally-glossary-attachment-row');
            container.append(iconAnchor);
            container.append(mainAnchor);
            $(el).remove();
        });

        const unwrappedlinks = '.entry .attachments .ally-glossary-attachment';
        this.#placeHoldSelector(unwrappedlinks, glossaryFileMapping)
            .done(() => {
                dfd.resolve();
            });
        return dfd.promise();
    }

    /**
     * Encode a file path so that it can be used to find things by uri.
     * @param {string} filePath
     * @returns {string}
     */
    urlEncodeFilePath(filePath) {
        const parts = filePath.split('/');
        for (let p in parts) {
            parts[p] = encodeURIComponent(parts[p]);
        }
        const encoded = parts.join('/');
        return encoded;
    }

    /**
     * General function for finding lesson component file elements and then add mapping.
     * @param {array} map
     * @param {string} selectorPrefix
     * @returns {Promise}
     */
    #placeHoldLessonGeneral(map, selectorPrefix) {
        const dfd = $.Deferred();
        if (map.length === 0) {
            dfd.resolve();
        } else {
            for (let c in map) {
                const path = this.urlEncodeFilePath(c);
                const sel = selectorPrefix + 'img[src*="' + path + '"], ' + selectorPrefix + 'a[href*="' + path + '"]';
                this.#placeHoldSelector(sel, map).done(() => {
                    dfd.resolve();
                });
            }
        }
        return dfd.promise();
    }

    /**
     * Placehold lesson page contents.
     * @param {array} pageContentsMap
     * @returns {Promise}
     */
    #placeHoldLessonPageContents(pageContentsMap) {
        return this.#placeHoldLessonGeneral(pageContentsMap, '');
    }

    /**
     * Placehold lesson answers.
     * @param {array} pageAnswersMap
     * @returns {Promise}
     */
    #placeHoldLessonAnswersContent(pageAnswersMap) {
        return this.#placeHoldLessonGeneral(pageAnswersMap,
            '.studentanswer table tr:nth-child(1) '); // Space at end of selector intended.
    }

    /**
     * Placehold lesson responses.
     * @param {array} pageResponsesMap
     * @returns {Promise}
     */
    #placeHoldLessonResponsesContent(pageResponsesMap) {
        return this.#placeHoldLessonGeneral(pageResponsesMap,
            '.studentanswer table tr.lastrow '); // Space at end of selector intended.
    }

    /**
     * Add place holders for lesson module files.
     * @param {array} lessonFileMapping
     * @returns {Promise}
     */
    async #placeHoldLessonModule(lessonFileMapping) {
        const pageContentsMap = lessonFileMapping.page_contents;
        const pageAnswersMap = lessonFileMapping.page_answers;
        const pageResponsesMap = lessonFileMapping.page_responses;

        await this.#placeHoldLessonPageContents(pageContentsMap);
        await this.#placeHoldLessonAnswersContent(pageAnswersMap);
        await this.#placeHoldLessonResponsesContent(pageResponsesMap);
    }

    /**
     * Add place holders for resource module.
     * @param {object} moduleFileMapping
     * @returns {promise}
     */
    #placeHoldResourceModule(moduleFileMapping) {
        const dfd = $.Deferred();
        let c = 0;

        /**
         * Once all modules processed, resolve promise for this function.
         */
        const checkAllProcessed = () => {
            c++;
            // All resource modules have been dealt with.
            if (c >= Object.keys(moduleFileMapping).length) {
                dfd.resolve();
            }
        };
        for (let moduleId in moduleFileMapping) {
            const pathHash = moduleFileMapping[moduleId].content;
            let moduleEl;
            if ($('body').hasClass('theme-snap') && !$('body').hasClass('format-tiles')) {
                moduleEl = $('#module-' + moduleId + ':not(.snap-native) .activityinstance ' +
                    '.snap-asset-link a:first-of-type:not(.clickable-region)');
            } else if ($('body').hasClass('format-tiles')) {
                moduleEl = $('#module-' + moduleId + ' .activityinstance ' +
                    'a:first-of-type:not(.clickable-region,.editing_move)');
            } else {
                moduleEl = $('#module-' + moduleId + ' .activity-instance ' +
                    'a:first-of-type:not(.clickable-region,.editing_move)');
            }
            const processed = moduleEl.find('.filter-ally-wrapper');
            if (processed.length > 0) {
                checkAllProcessed(); // Already processed.
                continue;
            }
            const data = {
                isimage: false,
                fileid: pathHash,
                url: $(moduleEl).attr('href')
            };
            this.#renderTemplate(data, pathHash, moduleEl)
                .done(checkAllProcessed);
        }
        return dfd.promise();
    }

    buildContentIdent(component, table, field, id) {
        return [component, table, field, id].join(':');
    }

    /**
     * Add annotations to sections content.
     * @param {array} sectionMapping
     * @returns {Promise}
     */
    #annotateSections(sectionMapping) {
        const dfd = $.Deferred();

        for (let s in sectionMapping) {
            const sectionId = sectionMapping[s];
            const ident = this.buildContentIdent('course', 'course_sections', 'summary', sectionId);

            const selectors = [
                '#' + s + ' > .content div[class*="summarytext"] .no-overflow',
                'body.theme-snap #' + s + ' > .content > .summary > div > .no-overflow' // Snap.
            ];
            $(selectors.join(',')).attr('data-ally-richcontent', ident);
        }

        dfd.resolve();
        return dfd.promise();
    }

    /**
     * Annotate module introductions.
     * @param {array} introMapping
     * @param {string} module
     * @param {array} additionalSelectors
     */
    annotateModuleIntros(introMapping, module, additionalSelectors) {
        for (let i in introMapping) {
            const annotation = introMapping[i];
            const selectors = [
                'body.path-mod-' + module + '.cmid-' + i + ' #intro > .no-overflow',
                // We need to be specific here for non course pages to skip this.
                'li.activity.modtype_' + module + '#module-' + i + ' .description .no-overflow > .no-overflow',
                'li.snap-activity.modtype_' + module + '#module-' + i + ' .contentafterlink > .no-overflow'
            ];
            if (additionalSelectors) {
                for (let a in additionalSelectors) {
                    selectors.push(additionalSelectors[a].replace('{{i}}', i));
                }
            }
            $(selectors.join(',')).attr('data-ally-richcontent', annotation);
        }
    }

    /**
     * Add annotations to forums.
     * @param {array} forumMapping
     */
    annotateForums(forumMapping) {
        // Annotate introductions.
        const intros = forumMapping.intros;
        this.annotateModuleIntros(intros, 'forum');

        // Annotate discussions.
        const discussions = forumMapping.posts;
        for (let d in discussions) {
            const post = 'p' + d;
            const annotation = discussions[d];
            const selectors = [
                "#page-mod-forum-discuss #" + post +
                ' div.forumpost div.no-overflow'
            ];
            $(selectors.join(',')).attr('data-ally-richcontent', annotation);
        }
    }

    /**
     * Add annotations to Open Forums.
     * @param {array} forumMapping
     */
    annotateMRForums(forumMapping) {

        // Annotate introductions.
        const intros = forumMapping.intros;
        this.annotateModuleIntros(intros, 'hsuforum', ['#hsuforum-header .hsuforum_introduction > .no-overflow']);

        const discussions = forumMapping.posts;
        for (let d in discussions) {
            const annotation = discussions[d];
            const postSelector = 'article[id="p' + d + '"] div.posting';
            $(postSelector).attr('data-ally-richcontent', annotation);
        }
    }

    /**
     * Add annotations to glossary.
     * @param {array} mapping
     */
    annotateGlossary(mapping) {
        // Annotate introductions.
        const intros = mapping.intros;
        this.annotateModuleIntros(intros, 'glossary');

        // Annotate entries.
        const entries = mapping.entries;
        for (let e in entries) {
            const annotation = entries[e];
            const entryFooter = $('.entrylowersection .commands a[href*="id=' + e + '"]');
            const entry = $(entryFooter).parents('.glossarypost').find('.entry .no-overflow');
            $(entry).attr('data-ally-richcontent', annotation);
        }
    }

    /**
     * Add annotations to page.
     * @param {array} mapping
     */
    annotatePage(mapping) {
        const intros = mapping.intros;
        this.annotateModuleIntros(intros, 'page', ['li.snap-native.modtype_page#module-{{i}} .contentafterlink > .summary-text']);

        // Annotate content.
        const content = mapping.content;
        for (let c in content) {
            const annotation = content[c];
            const selectors = [
                '#page-mod-page-view #region-main .box.generalbox > .no-overflow',
                'li.snap-native.modtype_page#module-' + c + ' .pagemod-content'
            ];
            $(selectors.join(',')).attr('data-ally-richcontent', annotation);
        }
    }

    /**
     * Add annotations to book.
     * @param {array} mapping
     */
    annotateBook(mapping) {
        const intros = mapping.intros;

        // For book, the only place the intro shows is on the course page when you select "display description on course page"
        // in the module settings.
        this.annotateModuleIntros(intros, 'book',
            ['li.snap-native.modtype_book#module-{{i}} .contentafterlink > .summary-text .no-overflow']);

        // Annotate content.
        const content = mapping.chapters;
        let chapterId;

        if (this.#params.chapterid) {
            chapterId = this.#params.chapterid;
        } else {
            const urlParams = new URLSearchParams(window.location.search);
            chapterId = urlParams.get('chapterid');
        }

        $.each(content, (ch, annotation) => {
            if (chapterId != ch) {
                return;
            }
            const selectors = [
                '#page-mod-book-view #region-main .box.generalbox.book_content > .no-overflow',
                'li.snap-native.modtype_page#module-' + ch + ' .pagemod-content'
            ];
            $(selectors.join(',')).attr('data-ally-richcontent', annotation);
        });
    }

    /**
     * Add annotations to lesson.
     * @param {array} mapping
     */
    async annotateLesson(mapping) {
        const intros = mapping.intros;

        // For lesson, the only place the intro shows is on the course page when you select "display description on course page"
        // in the module settings.
        this.annotateModuleIntros(intros, 'lesson',
            ['li.snap-native.modtype_lesson#module-{{i}} .contentafterlink > .summary-text .no-overflow']);

        // Annotate content.
        const content = mapping.lesson_pages;
        let pageId;
        for (let p in content) {
            if (document.body.id === "page-mod-lesson-edit") {
                const xpath = '//a[@id="lesson-' + p + '"]//ancestor::table//tbody/tr/td/div[contains(@class, "no-overflow")]';
                const annotation = content[p];
                const node = this.#getNodeByXpath(xpath);
                $(node).attr('data-ally-richcontent', annotation);
            } else {
                // Try get page from form.
                const node = this.#getNodeByXpath('//form[contains(@action, "continue.php")]//input[@name="pageid"]');
                if (node) {
                    pageId = $(node).val();
                } else {
                    const urlParams = new URLSearchParams(window.location.search);
                    pageId = urlParams.get('pageid');
                }

                if (pageId != p) {
                    continue;
                }
                const annotation = content[p];
                const selectors = [
                    // Regular page.
                    '#page-mod-lesson-view #region-main .box.contents > .no-overflow',
                    // Question page.
                    '#page-mod-lesson-view #region-main form > fieldset > .fcontainer > .contents .no-overflow',
                    // Lesson page.
                    'li.snap-native.modtype_page#module-' + p + ' .pagemod-content'
                ];

                $(selectors.join(',')).attr('data-ally-richcontent', annotation);
            }
        }

        // Annotate answer answers.
        const strings = await getStrings([
            {key: 'answer', component: 'mod_lesson'},
            {key: 'response', component: 'mod_lesson'}
        ]);

        const answerLabel = strings[0];
        const responseLabel = strings[1];
        const answers = mapping.lesson_answers;

        const processAnswerResponse = (pageId, i, label, annotation) => {
            const xpath = '//a[@id="lesson-' + pageId + '"]//ancestor::table' +
                '//td/label[contains(text(),"' + label + ' ' + i + '")]/ancestor::tr/td[2]';
            const nodes = this.#getNodesByXpath(xpath);
            for (let n in nodes) {
                const node = nodes[n];
                $(node).attr('data-ally-richcontent', annotation);
            }
        };

        for (let a in answers) {
            // Increment anum so that we can get the answer number.
            // Note, we can trust that this is correct because you can't order answers and the code in the lesson component
            // orders answers by id.
            const annotation = answers[a];

            const tmpArr = a.split('_');
            const pageId = tmpArr[0];
            const ansId = tmpArr[1];
            const anum = tmpArr[2];

            // Process answers when on lesson edit page.
            if (document.body.id === "page-mod-lesson-edit") {
                processAnswerResponse(pageId, anum, answerLabel, annotation);
            } else {
                // Wrap answers in labels.
                $('#page-mod-lesson-view label[for="id_answerid_' + ansId + '"]').attr('data-ally-richcontent', annotation);

                if (this.#params.answerid && this.#params.answerid == ansId) {
                    $('.studentanswer tr:nth-of-type(1) > td div').attr('data-ally-richcontent', annotation);
                } else {
                    const answerWrapperId = 'answer_wrapper_' + ansId;
                    const answerEl = $('#id_answerid_' + ansId);
                    if (answerEl.data('annotated') != 1) {
                        // We only want to wrap this once.
                        const contentEls = answerEl.nextAll();
                        answerEl.parent('label').append('<span id="answer_wrapper_' + ansId + '"></span>');
                        $('#' + answerWrapperId).append(contentEls);
                    }
                    answerEl.data('annotated', 1);
                }
                $('#answer_wrapper_' + a).attr('data-ally-richcontent', annotation);
            }
        }

        // Annotate answer responses.
        const responses = mapping.lesson_answers_response;
        for (let r in responses) {
            const annotation = responses[r];

            const tmpArr = r.split('_');
            const pageId = tmpArr[0];
            const respId = tmpArr[1];
            const rnum = tmpArr[2];

            if (document.body.id === "page-mod-lesson-edit") {
                processAnswerResponse(pageId, rnum, responseLabel, annotation);
            } else if (this.#params.answerid && this.#params.answerid == respId) {
                // Just incase you are wondering, yes answer ids ^ are the same as response ids ;-).
                const responseWrapperId = 'response_wrapper_' + respId;
                if (!$('.studentanswer tr.lastrow > td #' + responseWrapperId).length) {
                    // We only want to wrap this once, hence above ! length check.
                    const contentEls = $('.studentanswer tr.lastrow > td > br').nextAll();
                    $('.studentanswer tr.lastrow > td > br').after('<span id="' + responseWrapperId + '"></span>');
                    $('#' + responseWrapperId).append(contentEls);
                }

                $('#' + responseWrapperId).attr('data-ally-richcontent', annotation);
            }
        }
    }

    /**
     * Annotate supported modules
     * @param {array} moduleMapping
     * @returns {Promise}
     */
    #annotateModules(moduleMapping) {
        const dfd = $.Deferred();
        if (moduleMapping.mod_forum !== undefined) {
            this.annotateForums(moduleMapping.mod_forum);
        }
        if (moduleMapping.mod_hsuforum !== undefined) {
            this.annotateMRForums(moduleMapping.mod_hsuforum);
        }
        if (moduleMapping.mod_glossary !== undefined) {
            this.annotateGlossary(moduleMapping.mod_glossary);
        }
        if (moduleMapping.mod_page !== undefined) {
            this.annotatePage(moduleMapping.mod_page);
        }
        if (moduleMapping.mod_book !== undefined) {
            this.annotateBook(moduleMapping.mod_book);
        }
        if (moduleMapping.mod_lesson !== undefined) {
            this.annotateLesson(moduleMapping.mod_lesson);
        }
        dfd.resolve();
        return dfd.promise();
    }

    /**
     * Annotates course summary if found on footer.
     * @param {object} mapping
     * @returns {Promise}
     */
    #annotateSnapCourseSummary(mapping) {
        const dfd = $.Deferred();
        const snapFooterCourseSummary = $('#snap-course-footer-summary > div.no-overflow');
        if (snapFooterCourseSummary.length) {
            const ident = this.buildContentIdent('course', 'course', 'summary', mapping.courseId);
            snapFooterCourseSummary.attr('data-ally-richcontent', ident);
        }
        dfd.resolve();
        return dfd.promise();
    }

    /**
     * Annotate html block.
     * @param {object} mapping
     * @returns {Promise}
     */
    #annotateHtmlBlock(mapping) {
        const dfd = $.Deferred();

        const items = mapping.block_html;
        for (let i in items) {
            const ident = items[i];
            const selectors = [
                '#inst' + i + '.block_html > .card-body > .card-text > .no-overflow',
                '#inst' + i + '.block_html > .content > .no-overflow'
            ];
            const selector = selectors.join(',');
            $(selector).attr('data-ally-richcontent', ident);
        }
        dfd.resolve();
        return dfd.promise();
    }

    /**
     * Apply place holders and add annotations to content.
     * @returns {promise}
     */
    applyPlaceHolders() {
        M.util.js_pending('filter_ally_applyPlaceHolders');
        const dfd = $.Deferred();

        if (ally_module_maps === undefined || ally_section_maps === undefined) {
            dfd.resolve();
            return dfd.promise();
        }

        const self = this;

        const tasks = [
            {
                mapVar: ally_module_maps.file_resources,
                method: this.#placeHoldResourceModule
            },
            {
                mapVar: ally_module_maps.assignment_files,
                method: this.#placeHoldAssignModule
            },
            {
                mapVar: ally_module_maps.folder_files,
                method: this.#placeHoldFolderModule
            },
            {
                mapVar: ally_module_maps.forum_files,
                method: this.#placeHoldForumModule
            },
            {
                mapVar: ally_module_maps.glossary_files,
                method: this.#placeHoldGlossaryModule
            },
            {
                mapVar: ally_module_maps.lesson_files,
                method: this.#placeHoldLessonModule
            },
            {
                mapVar: ally_section_maps,
                method: this.#annotateSections
            },
            {
                mapVar: ally_annotation_maps,
                method: this.#annotateModules
            },
            {
                mapVar: {courseId: this.#courseId},
                method: this.#annotateSnapCourseSummary
            },
            {
                mapVar: ally_annotation_maps,
                method: this.#annotateHtmlBlock
            }];

        $(document).ready(() => {
            let completed = 0;

            /**
             * Run this once a task has resolved.
             */
            const onTaskComplete = () => {
                completed++;
                if (completed === tasks.length) {
                    // All tasks completed.
                    M.util.js_complete('filter_ally_applyPlaceHolders');
                    dfd.resolve();
                }
            };

            for (let t in tasks) {
                const task = tasks[t];
                if (Object.keys(task.mapVar).length > 0) {
                    task.method.call(self, task.mapVar)
                        .done(onTaskComplete);
                } else {
                    // Skipped this task because mappings are empty.
                    onTaskComplete();
                }
            }
        });
        return dfd.promise();
    }

    /**
     * Initialise JS stage two.
     * @param {string} jwt
     * @param {object} config
     */
    initStageTwo(jwt, config) {
        if (this.#canViewFeedback || this.#canDownload) {
            const debounceApplyPlaceHolders = Util.debounce(() => {
                return this.applyPlaceHolders();
            }, 1000);
            debounceApplyPlaceHolders()
                .done(() => {
                    ImageCover.init();
                    Ally.init(jwt, config);
                    try {
                        const selector = $('.foldertree > .filemanager');
                        const targetNode = selector[0];
                        if (targetNode) {
                            const observerConfig = {attributes: true, childList: true, subtree: true};
                            const callback = (mutationsList) => {
                                mutationsList.filter((mutation) => {
                                    return mutation.type === 'childList';
                                }).forEach(() => {
                                    this.#placeHoldFolderModule(ally_module_maps.folder_files);
                                });
                            };
                            const observer = new MutationObserver(callback);
                            observer.observe(targetNode, observerConfig);
                        }
                    } catch (error) {
                        setInterval(() => {
                            this.#placeHoldFolderModule(ally_module_maps.folder_files);
                        }, 5000);
                    }
                    this.#initialised = true;
                });

            $(document).ajaxComplete(() => {
                if (!this.#initialised) {
                    return;
                }
                debounceApplyPlaceHolders();
            });
            // For Snap theme.
            if ($('body.theme-snap').length) {
                $(document).ajaxComplete((event, xhr, settings) => {
                    // Search ally server response.
                    if (settings.url.includes('ally.js')) {
                        setTimeout(() => {
                            // Show score icons that are hidden, see INT-18688.
                            $('.ally-feedback.ally-active.ally-score-indicator-embedded span').each((idx, el) => {
                                if (el.style.display == 'none') {
                                    el.style.display = 'block';
                                    if (el.getAttribute('class') == 'ally-scoreindicator-container') {
                                        el.style.display = 'inline-block';
                                        el.children[0].style.display = 'inline-block';
                                    }
                                }
                            });
                        }, 5000);
                        $(document).off('ajaxComplete');
                    }
                });
            }
        }
    }

    /**
     * Init function.
     * @param {string} jwt
     * @param {object} config
     * @param {boolean} canViewFeedback
     * @param {boolean} canDownload
     * @param {int} courseId
     * @param {object} params
     */
    init(jwt, config, canViewFeedback, canDownload, courseId, params) {

        this.#canViewFeedback = canViewFeedback;
        this.#canDownload = canDownload;
        this.#courseId = courseId;
        this.#params = params;

        const self = this;

        const pluginJSURL = (path) => {
            return M.cfg.wwwroot + "/pluginfile.php/" + M.cfg.contextid + "/filter_ally/" + path;
        };

        const polyfills = {};
        if (!document.evaluate) {
            polyfills['filter_ally/wgxpath'] = pluginJSURL("vendorjs/wgxpath.install");
        }
        if (typeof URLSearchParams === 'undefined') {
            polyfills['filter_ally/urlsearchparams'] = [
                'https://cdnjs.cloudflare.com/ajax/libs/url-search-params/1.1.0/url-search-params.amd.js',
                pluginJSURL('vendorjs/url-search-params.amd') // CDN fallback.
            ];
        }
        if (polyfills !== {}) {
            // Polyfill document.evaluate.
            require.config(
                {
                    enforceDefine: false,
                    paths: polyfills
                }
            );
            const requires = Object.keys(polyfills);

            require(requires, () => {
                if (typeof URLSearchParams === 'undefined') {
                    window.URLSearchParams = arguments[1]; // Second arg in require (which is URLSearchParams).
                }
                self.initStageTwo(jwt, config);
            });

            return;
        }
        self.initStageTwo(jwt, config);
    }
}

export default new Main();
