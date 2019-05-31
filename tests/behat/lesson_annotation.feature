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
# Tests for Ally filter lesson annotations.
#
# @package   filter_ally
# @author    Guy Thomas
# @copyright Copyright (c) 2019 Blackboard Inc. (http://www.blackboard.com)
# @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

@filter @filter_ally
Feature: In a lesson, rich content should have annotations.

  Background:
    Given the ally filter is enabled

  @javascript
  Scenario: Lesson main page rich content is annotated.
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | teacher1 | C1     | editingteacher |
    When I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Lesson" to section "1" and I fill the form with:
      | Name            | Test lesson |
      | Description     | <p>This is a description for a lesson</p> |
    And I add 2 content pages to lesson "Test lesson"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test lesson"
    And I follow "Preview"
    #Note - the last lesson page appears first - hence lesson content 2 for title below.
    And the lesson page content entitled "Test lesson content 2" is annotated and contains text "Test content 2"
    And I press "Continue"
    And the lesson page content entitled "Test lesson content 1" is annotated and contains text "Test content 1"
    And I add 2 true false pages to lesson "Test lesson"
    And I follow visible link "Edit" _ally_
    And I follow visible link "Expanded" _ally_
    And the lesson page content entitled "Test lesson content 1" is annotated and contains text "Test content 1"
    And the lesson page content entitled "Test lesson content 2" is annotated and contains text "Test content 2"
    And the lesson answer containing content "FALSE answer for 3" is annotated
    And the lesson answer containing content "TRUE answer for 3" is annotated
    And the lesson answer containing content "FALSE answer for 4" is annotated
    And the lesson answer containing content "TRUE answer for 4" is annotated
    And the lesson response containing content "FALSE response for 3" is annotated
    And the lesson response containing content "TRUE response for 3" is annotated
    And the lesson response containing content "FALSE response for 4" is annotated
    And the lesson response containing content "TRUE response for 4" is annotated