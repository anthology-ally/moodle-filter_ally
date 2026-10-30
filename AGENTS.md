# AGENTS.md: Ally for Moodle

Everything above `## This repo` is identical in moodle-tool_ally, moodle-filter_ally and moodle-report_allylti, on `master` and every supported `MOODLE_*_STABLE` branch. Change it in all of them together. Copilot code review reads only the first 4,000 characters, from the pull request's base branch, so the version rules come first.

## Version rules

Moodle offers any higher `$plugin->version` as an update and refuses to install a lower one over a higher one. A wrong number strands sites, and a later release can't undo it. In review, a break of any rule below is a blocking issue.

| Branch | Moodle | Frozen date |
| --- | --- | --- |
| `MOODLE_502_STABLE` | 5.2 | `20261102` |
| `MOODLE_503_STABLE` | 5.3 | `20261103` |

`MOODLE_501_STABLE` and older are no longer supported: no version bumps, no tags. `master` is not a release line.

1. **Version = frozen date + two digits.** Each release adds 1 to the last two digits: `2026110200`, `2026110201`, up to `99`. Never change the date and never use today's date: that let an older line outrank a newer one (filter_ally issue 109). The version line says so: `// 20261102 = frozen line date YYYYMMDD - do not modify!` This follows https://moodledev.io/docs/apis/commonfiles/version.php.
2. **A new line** gets a date later than every existing line. The first commit on its branch sets version.php to `<date>00` with that comment and `supported`, and sets `requires` to that Moodle version's branch date from core's `public/version.php` (Moodle 5.3 = `2026100500`).
3. **`$plugin->supported = [<line>, <line>]`**. The Marketplace lists the version under these Moodle versions only.
4. **`$plugin->release`** changes with every tagged version (`5.2.3` → `5.2.4`). A version number that was published is never reused.
5. **`$plugin->dependencies`** never exceed what the sibling plugin has released on the same line.
6. **`$plugin->requires`** is the oldest Moodle a build installs on, and the only Moodle check Moodle enforces: `supported` only shows a warning. It stays as it is in a version bump. Change it only in its own pull request that says why.

Before you push a version.php change, run `.github/scripts/check-version-order.sh <line>`. The `Version order` workflow runs the same check on every pull request, and a `FAIL` blocks the merge.

## Branches

- A fix that isn't specific to one Moodle version goes to `master` first. Then `git cherry-pick -x` it onto each supported `MOODLE_*_STABLE` branch: one pull request per branch, each with its own version bump. A fix for one Moodle version only goes straight to that branch.
- Never copy version.php between branches. The date, `supported`, `requires` and dependency minimums differ per line, so a cherry-pick that touches version.php needs fixing by hand.
- filter_ally's CI checks out the branch with the same name in tool_ally and report_allylti, or else that line's `MOODLE_<line>_STABLE`. For a change across repos, push the branches to tool_ally and report_allylti first.

## Releasing

A release publishes to the public Moodle Marketplace and can't be withdrawn. Releases come only from `MOODLE_<line>_STABLE`. An agent asked to release does every step below, one line at a time, and **stops for the person's explicit go-ahead before pushing each tag**.

1. **What ships.** `git fetch origin --tags`, then `git log --oneline <last AY-<line>-R tag>..origin/MOODLE_<line>_STABLE`. Nothing listed: nothing to release. Release a sibling plugin too only when a dependency minimum moves.
2. **Bump** in a pull request into `MOODLE_<line>_STABLE`: `$plugin->version` +1 (rule 1) and `$plugin->release` +1 in its last part (`5.2.3` → `5.2.4`). Nothing else changes in version.php.
3. **Before tagging**, on the merged tip `<sha>`:
   - `.github/scripts/check-version-order.sh <line>` prints `OK`.
   - CI passed on `<sha>`, or `<sha>` has the same tree (`git rev-parse <sha>^{tree}`) as a pull request head whose CI passed. Only filter_ally runs tests in CI.
   - The sibling versions that `dependencies` need are on the Marketplace.
   - The next unused tag number: `git ls-remote --tags origin 'AY-<line>-*'`.
4. **Tag**, after the go-ahead: `git tag AY-<line>-R<n> <sha>`, then `git push origin AY-<line>-R<n>`. `<sha>` must still be the branch tip.
5. **Watch the run**: `gh run list --workflow moodle-release.yml --branch AY-<line>-R<n>`, then `gh run watch <id> --exit-status`. Success is `OK — pushed from MOODLE_<line>_STABLE` from `guard`, and `Response HTTP Status: 201` from the upload, with `version`, `releaseName` and `moodleVersions` matching version.php.
6. **Confirm** about 10 minutes later on `https://marketplace.moodle.com/plugins/<component>/versions`: the line shows `<release> (<version>)`. Only then is it released.

If it fails:
- `guard` fails: the tag is not on a stable branch tip. Delete it (`git push origin :refs/tags/<tag>`, `git tag -d <tag>`) and tag the right commit.
- The upload is not `201`: the log prints the response body. `401` or `403` means `MOODLE_MARKETPLACE_TOKEN` is missing or expired, and a person must replace it.
- Never re-run a job that got `201`, and never delete a published tag.

## This repo: filter_ally

A text filter that adds Ally's UI (score indicator, alternative formats, image cover) around teacher files and rich content. It installs at `public/filter/ally` and uses `tool_ally` classes directly.

### Commands

Run from the Moodle root:

```bash
vendor/bin/phpunit --group filter_ally
vendor/bin/phpunit --filter <test> public/filter/ally/tests/filter_test.php
php public/admin/tool/behat/cli/run.php --tags=@suite_ally   # all three plugins
cd public/filter/ally && npx grunt amd                       # amd/build is committed: rebuild after editing amd/src
vendor/bin/phpcs --standard=public/filter/ally/phpcs.xml public/filter/ally
```

`.github/workflows/moodle-ci.yml` is the only test CI for all three plugins. It also installs `mod_hsuforum` and `theme_snap`; put `skip-openlms` in the commit message or the pull request's title or body to skip them. `.travis.yml` is dead.

### How it works

Placeholders reach the page by two routes. Knowing which one owns a module is the key to debugging.

1. **PHP, for filtered text.** `classes/text_filter.php::filter()` finds `<a>` and `<img>` pointing at `pluginfile.php`, hashes the path to a `pathnamehash`, and wraps the element with `templates/wrapper.mustache`. A file missing from the per-file-area key cache is treated as not teacher-authored. Glossary attachments are left to JS, and lesson content goes through `verify_and_fix_if_applied()`.
2. **JS, for content the filter never sees** (resource, assign, folder, forum, glossary, lesson). `classes/local/entity_mapper.php` builds maps, and `setup()` writes them into the page footer as `ally_module_maps`, `ally_section_maps` and `ally_annotation_maps`, not as AMD arguments, which are size-limited. `amd/src/main.js` applies them. `filter_ally_get_module_maps` refreshes them over AJAX after `filterContentUpdated`, which is what makes lazy-loaded content (Snap theme) work.

- The CSS hides every placeholder until Ally's remote script adds `.ally-active`. A wrapper with no icon usually means that script didn't load, not that the filter failed.
- `amd/src/selectors.js` switches folder selectors at Moodle 5.2 (`2026032700`). Follow that pattern for differences between Moodle versions.
- `classes/local/jwthelper.php` signs an HS256 JWT with `tool_ally`'s secret. The secret never reaches JS.
- `entity_mapper` stops the filter re-entering itself while it builds annotation maps (MDL-67405).

### Debugging

- `?allytestmode=1` on a course page shows the placeholders with a red border, without Ally.
- Type `ally_module_maps` in the browser console on a course page: populated means the PHP side worked, `{}` means no eligible files, `undefined` means it never ran.
- `$CFG->filter_ally_disable_check_pagetype` runs the JS outside courses, `$CFG->filter_ally_enable_setup_debuger` logs setup calls, and `define('ALLY_OMITCACHE', true)` bypasses the file key cache.

### Conventions

- Tests use PHP 8 attributes, `#[Group('filter_ally')]` and `#[Group('ally')]`. Behat features carry `@suite_ally`; custom steps are in `tests/behat/behat_filter_ally.php`.
- `vendor/` (`firebase/php-jwt` 3.0.0) is committed.
