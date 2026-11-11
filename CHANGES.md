# Changelog

Release notes for this Moodle line, newest first. The release workflow publishes the
first `## ` section to the Moodle Marketplace, so every version bump PR adds one on top:
`## <release> (<version>)`, with both values copied from version.php.

Write for a Moodle admin deciding whether to upgrade: what changed, and whether anything
needs doing. Example, indented so the workflow ignores it (write yours without the indent):

    ## X.Y.Z (YYYYMMDDXX)

    ### Fixed
    - Ally feedback icons on images inside tables sit next to the image again.

    ### Changed
    - <one line on behaviour an admin or teacher will notice>

    ### Upgrade notes
    - No database changes and no new settings.

## 5.2.2 (2026110200)

### Fixed
- Fixed an issue where the accessibility score indicator for images inside a table appeared
  away from its image, near the left edge of the page, and could overlap other indicators.
  Each indicator now sits next to its image.

### Upgrade notes
- No database changes and no new settings.
