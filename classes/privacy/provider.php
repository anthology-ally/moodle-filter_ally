<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for filter_ally.
 *
 * @package filter_ally
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_ally\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for filter_ally implementing null_provider.
 *
 * @copyright Copyright (c) 2018 Blackboard Inc. (http://www.blackboard.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {
    use \core_privacy\local\legacy_polyfill;

    public static function _get_metadata(collection $collection) {
        $collection->link_external_location('jwt', [
            'userid'   => 'privacy:metadata:jwt:userid',
            'courseid' => 'privacy:metadata:jwt:courseid',
            'locale'   => 'privacy:metadata:jwt:locale',
            'roles'    => 'privacy:metadata:jwt:roles',
        ], 'privacy:metadata:jwt:externalpurpose');

        return $collection;
    }

    public static function _get_contexts_for_userid($userid) {
        return new contextlist();
    }

    public static function _export_user_data(approved_contextlist $contextlist) {
    }

    public static function _delete_data_for_all_users_in_context(\context $context) {
    }

    public static function _delete_data_for_user(approved_contextlist $contextlist) {
    }
}