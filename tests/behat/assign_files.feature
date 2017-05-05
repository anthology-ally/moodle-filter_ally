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
# Tests for Ally filter assignment additional files javascript.
#
# @package    filter_ally
# @author     Guy Thomas
# @copyright  Copyright (c) 2017 Blackboard Inc.
# @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later


@filter @filter_ally
Feature: When the ally filter is enabled, ally place holders are inserted when appropriate into assignment additional files.

  Background:
    Given the ally filter is enabled

  @javascript
  Scenario: Assignment additional files are processed.
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
    And I allow guest access for current course
    And I create assignment "test assignment" with additional file fixtures "bpd_bikes_640px.jpg, testgif_small.gif, testpng_small.png"
    And I reload the page
    And I follow "test assignment"
    Then I should see the feedback place holder for the "1st" assignment file
    And I should see the feedback place holder for the "2nd" assignment file
    And I should see the feedback place holder for the "3rd" assignment file
    And I should see the download place holder for the "1st" assignment file
    And I should see the download place holder for the "2nd" assignment file
    And I should see the download place holder for the "3rd" assignment file
    And I log out
    And I log in as "student1"
    When I follow "Course 1"
    And I follow "test assignment"
    Then I should not see the feedback place holder for the "1st" assignment file
    And I should not see the feedback place holder for the "2nd" assignment file
    And I should not see the feedback place holder for the "3rd" assignment file
    And I should see the download place holder for the "1st" assignment file
    And I should see the download place holder for the "2nd" assignment file
    And I should see the download place holder for the "3rd" assignment file
    And I log out
    And I log in as "guest"
    When I follow "Course 1"
    And I follow "test assignment"
    Then I should not see the feedback place holder for the "1st" assignment file
    And I should not see the feedback place holder for the "2nd" assignment file
    And I should not see the feedback place holder for the "3rd" assignment file
    And I should not see the download place holder for the "1st" assignment file
    And I should not see the download place holder for the "2nd" assignment file
    And I should not see the download place holder for the "3rd" assignment file

