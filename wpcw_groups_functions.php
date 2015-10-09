<?php

function wpcw_groups_grading_GetGroups() {
    global $wpdb;
    $groups_table = _groups_get_tablename('group');
    $groups = $wpdb->get_results("SELECT * FROM $groups_table ORDER BY name");
    $groups_select  = '<select class="groups" name="wpcw-groups-grading-group-id">';
    $groups_select .= '<option value=""> -- Select --</option>';

    foreach ($groups as $group) {
        $groups_select .= sprintf('<option value="%d">%s</option>', Groups_Utility::id($group->group_id), wp_filter_nohtml_kses($group->name));
    }
    $groups_select .= '</select>';
    return $groups_select;
}

function wpcw_groups_grading_AddUserToGroup($user_id, $group_id) {
    if ( !Groups_User_Group::read( $user_id, $group_id ) ) {
        Groups_User_Group::create(
          array(
            'user_id' => $user_id,
            'group_id' => $group_id
          )
        );
    }
}

function wpcw_groups_grading_CreateGroup($name)
{
    global $wpdb;
    $creator_id  = get_current_user_id();
    $datetime    = date( 'Y-m-d H:i:s', time() );
    $parent_id   = null;
    $description = '';

    return Groups_Group::create( compact( "creator_id", "datetime", "parent_id", "description", "name" ) );
}

/**
 * Save group id as user meta "instructor_group_id".
 * make a user instructor for a particular group
 */
function wpcw_groups_grading_AddGroupInstructor($group_id, $user_id)
{
   global $wpdb;

   add_user_meta($user_id, 'instructor_group_id', $group_id);
}

function groups_grading_GetInstructorGroups($user_id)
{
    global $wpdb;
    $groups_table = _groups_get_tablename('group');
    $found = array();
    $groups_array = get_user_meta($user_id, 'instructor_group_id');
    $groups_ids = "('" . implode("', '", $groups_array) . "')";
    $groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE group_id IN $groups_ids ORDER BY name");
    foreach($groups as $group)
    {
        $found[$group->group_id] = $group->name;
    }
    return $found;
}

function groups_grading_GetCourseGroups($user_id, $course_id)
{
    global $wpdb;
    $groups_table = _groups_get_tablename('group');
    $course_group_ids = groups_grading_GetArrayCourseGroups($course_id);
    $groups_array = get_user_meta($user_id, 'instructor_group_id');
    
    $groups_ids = "('";
    foreach($groups_array as $user_group_id)
    {
        if( !in_array($user_group_id, $course_group_ids) )
        {
            $groups_array = array_diff($groups_array, array($user_group_id));
        }
    }
    $groups_ids = "('" . implode("', '", $groups_array) . "')";
    $groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE group_id IN $groups_ids ORDER BY name");
    $found = array();
    foreach($groups as $group)
    {
        $found[$group->group_id] = $group->name;
    }
    return $found;
}

function groups_grading_GetAllCourseGroups($course_id)
{
    global $wpdb;
    $groups_table = _groups_get_tablename('group');
    $groups_array = groups_grading_GetArrayCourseGroups($course_id);
    $groups_ids = "('" . implode("', '", $groups_array) . "')";
    $groups = $wpdb->get_results("SELECT * FROM $groups_table WHERE group_id IN $groups_ids ORDER BY name");
    $found = array();
    foreach($groups as $group)
    {
        $found[$group->group_id] = $group->name;
    }
    return $found;
}

function groups_grading_GetArrayCourseGroups($course_id)
{
    global $wpdb;
    $course_group_table = $wpdb->prefix . 'gg_course_groups';
    $course_group_ids = $wpdb->get_col("SELECT group_id FROM $course_group_table WHERE course_id='$course_id'");
    return $course_group_ids;
}