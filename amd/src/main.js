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
 * @package   filter_ally
 * @author    Guy Thomas <osdev@blackboard.com>
 * @copyright Copyright (c) 2016 Blackboard Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/templates', 'filter_ally/ally', 'filter_ally/imagecover', 'filter_ally/util'],
function($, Templates, Ally, ImageCover, Util) {
    return new function() {

        var self = this;

        self.canViewFeedback = false;
        self.canDownload = false;
        self.stateStale = false;

        /**
         * Render template and insert result in appropriate place.
         * @return {promise}
         */
        var renderTemplate = function(data, pathHash, targetEl) {
            var dfd = $.Deferred();

            if ($(targetEl).parents('.filter-ally-wrapper').length) {
                // This has already been processed.
                dfd.resolve();
                return dfd.promise();
            }

            // Too expensive to do at module level - this is a course level capability.
            data.canviewfeedback = self.canViewFeedback;
            data.candownload = self.canDownload;
            data.html = '<span id="content-target-' + pathHash + '"></span>';

            Templates.render('filter_ally/wrapper', data)
                .done(function(result) {
                    $(targetEl).after(result);
                    // We are inserting the module element next to the target as opposed to replacing the
                    // target as we want to ensure any listeners attributed to the module element persist.
                    $('#content-target-' + pathHash).after(targetEl);
                    $('#content-target-' + pathHash).remove();

                    dfd.resolve();
                });

            return dfd.promise();
        };

        /**
         * Place holder items that are matched by selector.
         * @param {string} selector
         * @param {string} map
         * @return {promise}
         */
        var placeHoldSelector = function(selector, map) {
            var dfd = $.Deferred();

            var c = 0;

            var length = $(selector).length;
            $(selector).each(function() {

                /**
                 * Check that all selectors have been processed.
                 */
                var checkComplete = function() {
                    if (c === length) {
                        dfd.resolve();
                    }
                };
                var url,
                    type;

                if ($(this).prop("tagName").toLowerCase() === 'a') {
                    url = $(this).attr('href');
                    type = 'a';
                } else {
                    url = $(this).attr('src');
                    type = 'img';
                }
                var regex;
                if (url.indexOf('?') > -1) {
                    regex = /pluginfile.php\/(\d*)\/(.*)(\?)/;
                } else {
                    regex = /pluginfile.php\/(\d*)\/(.*)/;
                }
                var match = url.match(regex);
                var pathHash;
                if (match) {
                    var path = match[1] + '/' + match[2];
                    path = decodeURIComponent(path);
                    pathHash = map[path];
                }

                if (pathHash === undefined) {
                    // Maybe 'slasharguments' setting is disabled for this host.
                    // Let's see if the file URI is found in the URL query.
                    var query = Util.getQuery(url);
                    if (query.file) {
                        var filePath = decodeURIComponent(query.file);
                        regex = /\/(\d*)\/(.*)/;

                        match = filePath.match(regex);
                        if (match) {
                            path = match[1] + '/' + match[2];
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

                var data = {
                    isimage: type === 'img',
                    fileid: pathHash,
                    url: url
                };

                renderTemplate(data, pathHash, $(this))
                    .done(function(){
                        c++;
                        checkComplete();
                    });
            });
            return dfd.promise();
        };

        /**
         * Add place holders for forum module image attachments (note, regular files are covered by php).
         * @param {array}
         * @return {promise}
         */
        var placeHoldForumModule = function(forumFileMapping) {
            var dfd = $.Deferred();
            placeHoldSelector('.forumpost .attachedimages img[src*="pluginfile.php"]', forumFileMapping)
                .done(function(){
                    dfd.resolve();
                });
            return dfd.promise();
        };

        /**
         * Add place holders for assign module additional files.
         * @param {array}
         * @return {promise}
         */
        var placeHoldAssignModule = function(assignFileMapping) {
            var dfd = $.Deferred();
            Util.whenTrue(function() {
                return $('div[id*="assign_files_tree"] .ygtvitem').length > 0;
            }, 10)
                .done(function() {
                    placeHoldSelector('div[id*="assign_files_tree"] a[href*="pluginfile.php"]', assignFileMapping);
                    dfd.resolve();
                });
            return dfd.promise();
        };

        /**
         * Add place holders for folder module files.
         * @param {array}
         * @return {promise}
         */
        var placeHoldFolderModule = function(folderFileMapping) {
            var dfd = $.Deferred();
            Util.whenTrue(function() {
                return $('.foldertree > .filemanager .ygtvitem').length > 0;
            }, 10)
                .done(function() {
                    var unwrappedlinks = '.foldertree > .filemanager span:not(.filter-ally-wrapper) > a[href*="pluginfile.php"]';
                    placeHoldSelector(unwrappedlinks, folderFileMapping)
                       .done(function() {
                          dfd.resolve();
                       });
                });
            return dfd.promise();
        };

        /**
         * Add place holders for folder module files.
         * @param {array}
         * @return {promise}
         */
        var placeHoldGlossaryModule = function(glossaryFileMapping) {
            var dfd = $.Deferred();

            // Glossary attachment markup is terrible!
            // The first thing we need to do is rewrite the glossary attachments so that they are encapsulated.
            $('.entry .attachments > br').each(function() {
                var mainAnchor = $(this).prev('a[href*="pluginfile.php"]');
                mainAnchor.addClass('ally-glossary-attachment');
                var iconAnchor = $(mainAnchor).prev('a[href*="pluginfile.php"]');
                $(this).after('<div class="ally-glossary-attachment-row"></div>');
                var container = $(this).next('.ally-glossary-attachment-row');
                container.append(iconAnchor);
                container.append(mainAnchor);
                $(this).remove();
            });

            var unwrappedlinks = '.entry .attachments .ally-glossary-attachment';
            placeHoldSelector(unwrappedlinks, glossaryFileMapping)
                .done(function() {
                    dfd.resolve();
                });
            return dfd.promise();
        };

        /**
         * Add place holders for resource module.
         * @param moduleFileMapping
         * @return {promise}
         */
        var placeHoldResourceModule = function(moduleFileMapping) {
            var dfd = $.Deferred();
            var c = 0;

            /**
             * Once all modules processed, resolve promise for this function.
             */
            var checkAllProcessed = function() {
                c++;
                // All resource modules have been dealt with.
                if (c >= Object.keys(moduleFileMapping).length) {
                    dfd.resolve();
                }
            };

            for (var moduleId in moduleFileMapping) {
                var pathHash = moduleFileMapping[moduleId];
                if ($('body').hasClass('theme-snap')) {
                    var moduleEl = $('#module-' + moduleId + ':not(.snap-native) .activityinstance ' +
                            '.snap-asset-link a:first-of-type');
                } else {
                    var moduleEl = $('#module-' + moduleId + ' .activityinstance a:first-of-type');
                }
                var processed = moduleEl.find('.filter-ally-wrapper');
                if (processed.length > 0) {
                    checkAllProcessed(); // Already processed.
                    continue;
                }
                var data = {
                    isimage: false,
                    fileid: pathHash,
                    url: $(moduleEl).attr('href')
                };
                renderTemplate(data, pathHash, moduleEl)
                    .done(checkAllProcessed);
            }
            return dfd.promise();
        };

        var buildContentIdent = function(component, table, field, id) {
            return [component, table, field, id].join(':');
        };

        /**
         * Add annotations to sections content.
         */
        var annotateSections = function(sectionMapping) {
            var dfd = $.Deferred();

            for (var s in sectionMapping) {
                var sectionId = sectionMapping[s];
                var ident = buildContentIdent('course', 'course_sections', 'summary', sectionId);

                var selectors = [
                    '#' + s + ' > .content > div[class*="summary"] > .no-overflow',
                    'body.theme-snap #' + s + ' > .content > .summary > div > .no-overflow' // Snap.
                ];

                $(selectors.join(',')).attr('data-ally-richcontent', ident);
            }

            dfd.resolve();
            return dfd.promise();
        };

        /**
         * Annotate module introductions.
         * @param introMapping
         */
        var annotateModuleIntros = function(introMapping, module) {
            for (var c in introMapping) {
                var annotation = introMapping[c];
                var selectors = [
                    'body.path-mod-' + module + '.cmid-' + c + ' #intro > .no-overflow',
                    // We need to be specific here for non course pages to skip this.
                    'li.activity.modtype_' + module + '#module-' + c + ' .contentafterlink > .no-overflow > .no-overflow',
                    'li.snap-activity.modtype_' + module + '#module-' + c + ' .contentafterlink > .no-overflow'
                ];
                if (module === 'hsuforum') {
                    selectors.push('#hsuforum-header .hsuforum_introduction > .no-overflow');
                }
                $(selectors.join(',')).attr('data-ally-richcontent', annotation);
            }
        };

        /**
         * Add annotations to forums.
         * @param array forumMapping.
         */
        var annotateForums = function(forumMapping) {

            // Annotate introductions.
            var intros = forumMapping['intros'];
            annotateModuleIntros(intros, 'forum');

            // Annotate discussions.
            var discussions = forumMapping['posts'];
            for (var d in discussions) {
                var post = 'p' + d;
                var annotation = discussions[d];
                var postSelector = "#page-mod-forum-discuss a#" + post +
                        ' + div.firstpost div.posting.fullpost';
                var contentSelector = postSelector + ' > *:not(.attachedimages):not([data-ally-richcontent])';
                $(postSelector).prepend("<div data-ally-richcontent='" + annotation + "'></div>");
                $(contentSelector).detach().appendTo(postSelector + ' > div[data-ally-richcontent]');
            }
        };

        /**
         * Add annotations to Open Forums.
         * @param array forumMapping
         */
        var annotateMRForums = function(forumMapping) {

            // Annotate introductions.
            var intros = forumMapping['intros'];
            annotateModuleIntros(intros, 'hsuforum');

            var discussions = forumMapping['posts'];
            for (var d in discussions) {
                var annotation = discussions[d];
                var postSelector = 'article[id="p' + d + '"] div.posting';
                $(postSelector).attr('data-ally-richcontent', annotation);
            }
        };

        /**
         * Add annotations to glossary.
         * @param array mapping
         */
        var annotateGlossary = function(mapping) {
            // Annotate introductions.
            var intros = mapping['intros'];
            annotateModuleIntros(intros, 'glossary');

            // Annotate entries.
            var entries = mapping['entries'];
            for (var e in entries) {
                var annotation = entries[e];
                var entryFooter = $('.entrylowersection .commands a[href*="id=' + e + '"]');
                var entry = $(entryFooter).parents('.glossarypost').find('.entry .no-overflow');
                $(entry).attr('data-ally-richcontent', annotation);
            }
        };

        /**
         * Annotate supported modules
         * @param moduleMapping
         */
        var annotateModules = function(moduleMapping) {
            var dfd = $.Deferred();
            if (moduleMapping['mod_forum'] !== undefined) {
                annotateForums(moduleMapping['mod_forum']);
            }
            if (moduleMapping['mod_hsuforum'] !== undefined) {
                annotateMRForums(moduleMapping['mod_hsuforum']);
            }
            if (moduleMapping['mod_glossary'] !== undefined) {
                annotateGlossary(moduleMapping['mod_glossary']);
            }
            dfd.resolve();
            return dfd.promise();
        };

        /**
         * Annotates course summary if found on footer.
         * @param mapping
         */
        var annotateSnapCourseSummary = function(mapping) {
            var dfd = $.Deferred();
            var snapFooterCourseSummary = $('#snap-course-footer-summary > div.no-overflow');
            if (snapFooterCourseSummary.length) {
                var ident = buildContentIdent('course', 'course', 'summary', mapping.courseId);
                snapFooterCourseSummary.attr('data-ally-richcontent', ident);
            }
            dfd.resolve();
            return dfd.promise();
        };

        /**
         * Apply place holders and add annotations to content.
         * @return {promise}
         */
        var applyPlaceHolders = function() {
            var dfd = $.Deferred();

            if (ally_module_maps === undefined || ally_section_maps === undefined) {
                dfd.resolve();
                return dfd.promise();
            }

            var tasks = [{
                mapVar: ally_module_maps.file_resources,
                method: placeHoldResourceModule
            },
            {
                mapVar: ally_module_maps.assignment_files,
                method: placeHoldAssignModule
            },
            {
                mapVar: ally_module_maps.folder_files,
                method: placeHoldFolderModule
            },
            {
                mapVar: ally_module_maps.forum_files,
                method: placeHoldForumModule
            },
            {
                mapVar: ally_module_maps.glossary_files,
                method: placeHoldGlossaryModule
            },
            {
                mapVar: ally_section_maps,
                method: annotateSections
            },
            {
                mapVar: ally_annotation_maps,
                method: annotateModules
            },
            {
                mapVar: {courseId: self.courseId},
                method: annotateSnapCourseSummary
            },
            ];

            $(document).ready(function() {
                var completed = 0;
                /**
                 * Run this once a task has resolved.
                 */
                var onTaskComplete = function() {
                    completed++;
                    if (completed === tasks.length) {
                        // All tasks completed.
                        dfd.resolve();
                    }
                };

                for (var t in tasks) {
                    var task = tasks[t];
                    if (Object.keys(task.mapVar).length > 0) {
                        task.method(task.mapVar)
                            .done(onTaskComplete);
                    } else {
                        // Skipped this task because mappings are empty.
                        onTaskComplete();
                    }
                }
            });
            return dfd.promise();
        };

        /**
         * Init function.
         * @param jwt
         * @param {object} config
         * @param {boolean} canViewFeedback
         * @param {boolean} canDownload
         * @param {int} courseId
         */
        this.init = function(jwt, config, canViewFeedback, canDownload, courseId) {
            self.canViewFeedback = canViewFeedback;
            self.canDownload = canDownload;
            self.courseId = courseId;
            if (canViewFeedback || canDownload) {
                applyPlaceHolders()
                    .done(function() {
                        ImageCover.init();
                        Ally.init(jwt, config);
                        setInterval(function() {
                            placeHoldFolderModule(ally_module_maps.folder_files);
                        }, 5000);
                    });

                // Re-apply placeholders when hide / show clicked in action menu.
                var reApplySelectors = [
                    '.moodle-actionmenu[data-enhance=moodle-core-actionmenu] .editing_hide',
                    '.moodle-actionmenu[data-enhance=moodle-core-actionmenu] .editing_show',
                    '.moodle-actionmenu[data-enhance=moodle-core-actionmenu] .editing_moveleft',
                    '.moodle-actionmenu[data-enhance=moodle-core-actionmenu] .editing_moveright',
                    '.moodle-actionmenu[data-enhance=moodle-core-actionmenu] .editing_duplicate'
                ].join(', ');
                $('.course-content').on('click', reApplySelectors, function() {
                    self.stateStale = true;
                });
                $(document).ajaxComplete(function() {
                    if (self.stateStale) {
                        applyPlaceHolders();
                        self.stateStale = false;
                    }
                });
            }
        };
    };
});
