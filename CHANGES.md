# Changelog

Release notes for this Moodle line, newest first. The release workflow publishes the
first `## ` section to the Moodle Marketplace, so every version bump PR adds one on top:
`## <release> (<version>)`, with both values copied from version.php.

Write for a Moodle admin deciding whether to upgrade: what changed, and whether anything
needs doing. Keep each bullet on one line, because the Marketplace turns every newline into a
line break, and leave out AB# ticket numbers. Example, indented so the workflow ignores it
(write yours without the indent):

    ## 5.2.3 (2026110200)

    ### Fixed
    - Ally feedback icons on images inside tables sit next to the image again.

    ### Changed
    - <one line on behaviour an admin or teacher will notice>

    ### Upgrade notes
    - No database changes and no new settings.

## 5.3.0 (2026110300)

### Added
- Support for Moodle 5.3. This version requires Moodle 5.3.

### Upgrade notes
- Requires Moodle 5.3. No database changes and no new settings.
