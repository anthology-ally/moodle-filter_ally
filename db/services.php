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
 * Web service local plugin external functions and service definitions.
 *
 * @package    filter_ally
 * @category   webservice
 * @author     Guy Thomas
 * @copyright  Copyright (c) 2017 Open LMS / 2025 Anthology Inc. and its affiliates
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// We defined the web service functions to install.
$functions = [
    'filter_ally_get_module_maps' => [
        'classname'   => filter_ally\external\get_module_maps::class,
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get module maps for Ally filter including file mappings, section mappings, and annotation mappings.',
        'type'        => 'read',
        'capabilities' => 'filter/ally:viewfeedback',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = [
    'Ally filter service' => [
        'functions' => [
            'filter_ally_get_module_maps',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];
