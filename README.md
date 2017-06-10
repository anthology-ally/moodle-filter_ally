# Ally #

TODO Describe the plugin shortly here.

TODO Provide more detailed description here.

## Testing ##

It can be difficult to test without Ally installed. Following is how styling
can be tested for the 3 ways Ally integrations with Moodle in the UI filter.

First, it is assumed you have enabled the Ally filter in order to get the
placement of `ally-feedback`, `ally-image-cover` and `ally-download`
elements. These are the placeholder elements where Ally will "inject" a bit
of HTML when it has determined the link(s) should show.

Second, it will be helpful to place Ally's base CSS class in the `<head />`
area to get the base styles for the elements Ally places:

  `<link rel="stylesheet" href="https://prod.ally.ac/integration/css/ally.css" />`

Then, for each type of integration point (feedback, image cover and download),
you can some additional HTML:

### Instructor Feedback icon ###

The instructor feedback icon is a little meter indicator that, when clicked,
opens a modal for instructors to be instructed how to improve the accessibility
of the file. Find the appropriate `.ally-feedback` element and place the
following in the DOM:

```
<a href="#" class="ally-accessibility-score-indicator ally-accessibility-score-indicator-high ally-instructor-feedback">
  <img src="https://performance.ally.ac/integration/img/ally-icon-indicator-high.svg" alt="" aria-hidden="true">
  <span class="screenreader-only">Accessibility score: High. Click to improve</span>
</a>
```

When Ally "activates" the item, it places the above content and it appends the CSS class `ally-active` to the
`.ally-feedback` element. So doing both will show how Ally will look with the icon visible. When the `ally-active`
class is removed again, it should appear as though Ally has done nothing.

### Download accessible versions icon ###

The download icon is placed to allow students to download accessible versions.
When clicked, a modal appears where a student can select the alternative
version they wish to view. To enable this the way Ally does, find the appropriate
`.ally-download` element and place the following in the DOM:

```
<a href="#" title="Accessible versions" aria-haspopup="true">
    <img src="https://performance.ally.ac/integration/moodlerooms/img/ally-download.svg" alt="Accessible versions">
</a>
```

When Ally "activates" the item, it places the above content and it appends the CSS class `ally-active` to the
`.ally-download` element. So doing both will show how Ally will look with the icon visible. When the `ally-active`
class is removed again, it should appear as though Ally has done nothing.

### Seizure Guard image cover ###

The seizure guard is placed over images (GIFs) that Ally has determined carries
a risk of causing seizures. To enable this the way Ally does, find the
appropriate `.ally-image-cover` element and place the following in the DOM:

```
<div class="ally-image-seizure-guard">
    <button title="Potentially seizure inducing animation. Press to enable.">
        <img src="https://performance.ally.ac/integration/img/ally-icon-seizure-flag.svg">
    </button>
</div>
```

When Ally "activates" the item, it places the above content and it appends the CSS class `ally-active` to the
`.ally-image-cover` element. So doing both will show how Ally will look when the guard is active. When the `ally-active`
class is removed again, it should appear as though Ally has done nothing.

## License ##

Blackboard Inc 2017

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
