<?php

/*
Plugin Name: WPCW Groups Grading 2
Plugin URI: 
Description: Makes grade book management easy by assiging people to groups.
Version: 2.1.0
Author: Ali Roshan
Author Email: aliroshan@live.com
Author URI: 
License: GPLv2
*/

/* 
Copyright (C) 2014 Ali Roshan

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/
global $wpdb;

define('GROUPS_GRADING_DB_VERSION', '2.1.0');

$plugin_stlye = plugins_url('wpcw-groups-grading-2/style.css');
wp_enqueue_style('wpcw_gg_style', $plugin_stlye);

require_once 'wpcw_functions.php';
require_once 'wpcw_groups_functions.php';
require_once 'wpcw_pmpro_functions.php';
require_once 'tests.php';
require_once 'wpcw_groups_grading_menu.php';

// Add hooks for Wordpress and WP-Courseware
add_action('init', 'init_wpcw_groups_grading');

function init_wpcw_groups_grading()
{
    ///////
    // WP Courseware hooks 
    // Override menu - We will show only My Group (users)
    add_filter('wpcw_back_filters_gradebook_filters', 'wpcw_ExtendGradeBookFilter', 10, 2);

    // Override default All users query - 
    add_filter('wpcw_back_query_filter_gradebook_users', 'wpcw_QueryGradeBookUsers', 10, 2);

    // These filters are modified to give users full access to gradebook
    add_filter('wpcw_back_menus_access_training_courses', 'wpcw_GradeBookCapability');
    add_filter('wpcw_back_menus_access_gradebook', 'wpcw_GradeBookCapability');
    add_filter('wpcw_back_menus_access_user_progress', 'wpcw_GradeBookCapability');
    add_filter('wpcw_back_menus_access_user_quiz_results', 'wpcw_GradeBookCapability');

    // query to select users that need grading
    add_filter('wpcw_back_query_filter_gradebook_users_final_grades_email', 'wpcw_QueryUsersNeedGrades', 10, 2);
    
    // TODO: change user query according to new group/course relation
    // notify instructor when user completes module/course, quiz needs grading
    /*add_action('wpcw_user_completed_module_notification', 'wpcw_NotifyGroupInstructor', 10, 4);
    add_action('wpcw_user_completed_course_notification', 'wpcw_NotifyGroupInstructor', 10, 4);
    add_action('wpcw_user_quiz_needs_marking_notification', 'wpcw_NotifyGroupInstructor', 10, 4);*/
    
    // Allow users to be set as group instructors
    add_action('edit_user_profile', 'groups_grading_AddGroupInstructor');
    add_action('show_user_profile', 'groups_grading_AddGroupInstructor');
    add_action('profile_update', 'groups_grading_UpdateGroupInstructor');
    
    ///////
    // Groups Grading Menu
    ///
    add_action("admin_menu", "wpcw_groups_grading_menu");
}

///////
// Create plugin tables when plugin is activated
///
register_activation_hook(__FILE__, 'wpcw_gg_install');
///
// Check if plugin was modified and perform necessary update actions on DB
///
add_action('plugins_loaded', 'wpcw_gg_update_check');

function wpcw_gg_install()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'gg_course_groups';
    $installed_ver = get_option( "groups_grading_db_version" );
    if($installed_ver!=GROUPS_GRADING_DB_VERSION)
    {
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            course_id mediumint(9) NOT NULL,
            group_id mediumint(9) NOT NULL,
            PRIMARY KEY  id (id)
        );";
    }
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    update_option( "groups_grading_db_version", GROUPS_GRADING_DB_VERSION);
}

function wpcw_gg_update_check()
{
    if(get_site_option('groups_grading_db_version')!=GROUPS_GRADING_DB_VERSION)
    {
        wpcw_gg_install();
    }
}