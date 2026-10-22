# AGENTS.md: Ally for Moodle

This file is identical in moodle-filter_ally, moodle-tool_ally and moodle-report_allylti, on every maintained MOODLE_*_STABLE branch. Copilot code review reads it from the pull request's base branch and only reads the first 4,000 characters, so the version rules come first.

## Version rules: check every change to version.php against these

Each maintained MOODLE_<line>_STABLE branch is its own release line: 500 = Moodle 5.0, then 501, 502. Moodle 4.5 (405) and older are no longer supported. Moodle offers any higher `$plugin->version` as an update and refuses to install a lower one over a higher one. So a wrong number strands sites and can't be undone by a later release.

1. **Order.** A line's version must be above every older line and below every newer line, both on the branch tips and on the last `AY-<line>-R<n>` tag of each line.
2. **Format** `YYYYMMDDSS`. `SS` is the line's suffix: 500 = 25, 501 = 50, 502 = 75. Suffixes only keep lines apart when the lines share the same date.
3. **Choosing a number.**
   - Newest line: today's date plus its suffix.
   - Older line: the date of the next newer line's last release plus this line's suffix. If that is not above this line's current version, add 1 to `SS`. It must stay below the next newer line's released version. If there is no room, bump the newer line in the same round.
   - Never put today's date on an older line alone. That is what caused issue 109.
4. **Release the newer line first**, or in the same round. A new number on an older line must not outrank the newer line's *released* version, even if the newer branch tip is already higher.
5. **`$plugin->supported = [<line>, <line>]`** on every stable branch. The Marketplace API takes the Moodle range from it and it can't be overridden. Without it, the range is guessed.
6. **`$plugin->release`** changes with every tagged version. A tag never reuses a version that was already released.
7. **`$plugin->dependencies`** on the sibling plugins may not exceed the version that sibling has *released* on the same line.
8. **`$plugin->requires`** stays as it is in a version bump. Change it only in its own PR that says why.
9. **master and unsupported lines (405 and older)** are not release lines. Don't bump or tag them, and don't use them in the order checks.

The `Version order` workflow runs on every pull request. When the pull request changes version.php on a stable branch, a `FAIL` that involves that line fails the check and blocks the merge. A `WARN` sets the release order. Before you push, run the same check locally, with the line the change targets:

```bash
.github/scripts/check-version-order.sh 501
```

In a review, report a break of any rule above as a blocking issue, even when the check passes. When a dependency changes, check the sibling repo on the same line.

## Past incidents

- **Issue 109 (filter_ally).** On 2026-03-25 the 4.5 and 5.0 backports took that day's date (`2026032500` and `2026032525`) while 5.1 was still at `2026012850`. Moodle 5.1 sites were offered 4.5.1, which declares `supported = [405, 405]`, and could not go back to the 5.1 line. PR 114 (5.2.1 = `2026102175`) and PR 115 (5.1.1 = `2026102150`) fix it. Release 5.2 first, because 5.1.1 alone would outrank the released 5.2.0 (`2026070600`).
- **tool_ally, 2026-08-07 to 2026-10-15.** 5.1.1 was released as `2026080750` while the released 5.2.0 was `2026070600`. A 5.1 site that upgraded to Moodle 5.2 could not install tool_ally 5.2.0 until 5.2.1 (`2026101575`) came out.
- **report_allylti AY-500-R2 and AY-500-R3** were both tagged at `2026012825`. A version that is already published can't be released again.

## The check script

`.github/scripts/check-version-order.sh [LINE [FILE]]` fetches the stable branches and tags. It prints the tip and the last release of each line from `MIN_LINE` (default 500, the oldest supported line), then tests rules 1, 4, 5 and 6.

- With no argument it checks the branch tips as pushed, and any problem fails.
- With `LINE`, it uses this checkout's version.php for that line in place of the branch tip. Only problems that involve `LINE` fail; the rest are warnings. Problems between releases that are already published are always warnings, because only a new release can fix them.
- With `FILE`, it reads that file for the line, for example `git show pr-head:version.php > /tmp/v.php`.
- `SKIP_FETCH=1` skips the fetch. `REPORT_ONLY=1` turns every problem into a warning.

`.github/workflows/version-order.yml` calls the script on every pull request, using the merged result of the pull request. If version.php is unchanged, or the base is not a stable branch, it only reports. The release workflow checks the order again when an `AY*` tag is pushed. That is the last line of defence.

A pull request on an older line fails while a newer line is out of order, even if the pull request's own number is correct. Fix the newer line first. For example, a 5.0 version bump on filter_ally fails until the 5.1 fix in PR 115 is merged.

## Releasing

1. Merge the version bump into `MOODLE_<line>_STABLE`.
2. Tag that commit `AY-<line>-R<n>` (the next `n` for that line) and push the tag from that branch.
3. `.github/workflows/moodle-release.yml` checks that the tag is on exactly one stable branch, checks the version order, and then publishes through the Moodle Marketplace API.

When several lines are released together, tag the newest line first. Release the three plugins together when their dependencies move.

## Working across branches

- A fix that goes to several lines needs one pull request per `MOODLE_*_STABLE` branch, each with its own version bump that follows rule 3. Never copy one branch's version.php to another branch.
- Read the target branch's version.php before you write a new number. The numbers differ on every branch, and so do the dates.
- `supported`, `requires` and the dependency minimums also differ per line, so a cherry-pick that touches version.php needs fixing by hand.
