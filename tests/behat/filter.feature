# This file is part of Moodle - http://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
#
# Tests for Ally filter.
#
# @package    filter_ally
# @author     Guy Thomas
# @copyright  Copyright (c) 2017 Blackboard Inc.
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later


@filter @filter_ally
Feature: When the ally filter is enabled ally place holders are inserted when appropriate into user generated content.

  Background:
    Given the ally filter is enabled

  @javascript
  Scenario: Img tags for local files are processed.
    Given the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | 0        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | teacher        |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I create a label with fixture images "bpd_bikes_640px.jpg, testgif_small.gif, testpng_small.png"
    When I reload the page
    Then I should see the feedback place holder for the "1st" image
    And I should see the feedback place holder for the "2nd" image
    And I should see the feedback place holder for the "3rd" image
    And the ally image cover area should exist for the "1st" image
    And the ally image cover area should exist for the "2nd" image
    And the ally image cover area should exist for the "3rd" image
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    Then I should not see the feedback place holder for the "1st" image
    And I should not see the feedback place holder for the "2nd" image
    And I should not see the feedback place holder for the "3rd" image
    And the ally image cover area should exist for the "1st" image
    And the ally image cover area should exist for the "2nd" image
    And the ally image cover area should exist for the "3rd" image

  @javascript
  Scenario: Anchors linking to local files are processed.
    Given the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | 0        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher1  | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | teacher        |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I allow guest access for current course
    And I create a label with random text files "test1.txt, test2.txt, test3.txt"
    When I reload the page
    Then I should see the feedback place holder for the "1st" anchor
    And I should see the feedback place holder for the "2nd" anchor
    And I should see the feedback place holder for the "3rd" anchor
    And I should see the download place holder for the "1st" anchor
    And I should see the download place holder for the "2nd" anchor
    And I should see the download place holder for the "3rd" anchor
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    Then I should not see the feedback place holder for the "1st" anchor
    And I should not see the feedback place holder for the "2nd" anchor
    And I should not see the feedback place holder for the "3rd" anchor
    And I should see the download place holder for the "1st" anchor
    And I should see the download place holder for the "2nd" anchor
    And I should see the download place holder for the "3rd" anchor
    And I log out
    And I log in as "guest"
    And I follow "Course 1"
    Then I should not see the feedback place holder for the "1st" anchor
    And I should not see the feedback place holder for the "2nd" anchor
    And I should not see the feedback place holder for the "3rd" anchor
    And I should not see the download place holder for the "1st" anchor
    And I should not see the download place holder for the "2nd" anchor
    And I should not see the download place holder for the "3rd" anchor
