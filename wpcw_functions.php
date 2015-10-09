<?php

function wpcw_ExtendGradeBookFilter($filters_list, $course_id)
{
    global $wpdb, $wpcwdb;
    $user_ID = get_current_user_id();
    // If user is not admin, only his groups are visible to him
    if ( !current_user_can('manage_options') )
    {
        $filters_list = array();
        $my_groups = groups_grading_GetCourseGroups($user_ID, $course_id);
    }
    else // Show all course groups to admin
    {
        $my_groups = groups_grading_GetAllCourseGroups($course_id);
    }
    foreach($my_groups as $group_id => $group_name)
    {
        $filters_list[$group_name] = $group_name;
    }
    return $filters_list;
}

function wpcw_QueryGradeBookUsers($query_all_users, $course_id)
{
    global $wpdb, $wpcwdb;
    $user_ID = get_current_user_id();
    $filter_str = reqVar('filter');
    if ( current_user_can('manage_options') )
    {
        $my_groups = groups_grading_GetAllCourseGroups($course_id);
    }
    else
    {
        $my_groups = groups_grading_GetCourseGroups($user_ID, $course_id);
        if( !$filter_str )
        {
            $filter_str = reset($my_groups);
        }
    }
    
    if (in_array($filter_str, $my_groups))
    {
        $group_id = array_search($filter_str, $my_groups);
        $tbl_user_group = _groups_get_tablename('user_group');

        $filtered_users = "
        SELECT u.*, uc.* 
        FROM $wpdb->users u
        JOIN $tbl_user_group ug ON u.`ID` = ug.user_id
        JOIN $wpcwdb->user_courses uc ON u.`ID` = uc.`user_id`
        WHERE ug.`group_id` = '$group_id'
        AND uc.course_id = '$course_id'";
        return $filtered_users;
    }
    return $query_all_users;
}

function wpcw_GradeBookCapability($default_required_capability)
{
    return "publish_posts";
}

function wpcw_QueryUsersNeedGrades($default_query, $courseDetails)
{
    global $wpdb, $wpcwdb;
    $user_ID = get_current_user_id();
    if ( current_user_can('manage_options') )
    {
        $my_groups = groups_grading_GetAllCourseGroups($course_id);
    }
    else
    {
        $my_groups = groups_grading_GetCourseGroups($user_ID, $course_id);
    }
    $filter_str = reqVar('filter');
    $tbl_user_group = _groups_get_tablename('user_group');
    if (in_array($filter_str, $my_groups))
    {
        $queryUsersNeedGrades = $wpdb->prepare("
        SELECT * 
        FROM $wpcwdb->user_courses uc                 
        LEFT JOIN $wpdb->users u ON u.ID = uc.user_id
        LEFT JOIN $tbl_user_group ug ON u.ID = ug.user_id
        WHERE uc.course_id = %d
        AND u.ID IS NOT NULL
        AND uc.course_progress = 100
        AND uc.course_final_grade_sent != 'sent'
        AND ug.group_id = '$group_id'", $courseDetails->course_id);
        return $queryUsersNeedGrades;
    }
    return $default_query;
}

function wpcw_NotifyGroupInstructor($unitParentData, $userDetails, $subjectTemplate, $bodyTemplate)
{
    $instructor_id = getUserGroupInstructor($userDetails->ID);
    $instructor = get_userdata($instructor_id);

    if ($instructor !== false) {
        WPCW_email_sendEmail($unitParentData, $userDetails, // User who's done the completion
                $instructor->data->user_email, $subjectTemplate, $bodyTemplate);
    }
}

function getUserGroupInstructor($user_id) {
    global $wpdb;
    $groups_table = $wpdb->prefix . "groups_group";
    $groups_user_table = $wpdb->prefix . "groups_user_group";
    $query = "
    SELECT gg.name AS group_name, gg.group_id, um.user_id AS instructor
    FROM $groups_table gg
    INNER JOIN $groups_user_table gug
    ON gg.group_id = gug.group_id
    INNER JOIN $wpdb->usermeta um
    ON gg.group_id = um.meta_value
    WHERE gug.user_id = '$user_id'
    AND gg.name != 'Registered'
    AND meta_key = 'instructor_group_id'
    LIMIT 0, 1";
    $user_group = $wpdb->get_row($query);
    return $user_group->instructor;
}

function wpcw_groups_grading_GetCourses() {
    global $wpcwdb, $wpdb;
    $course_select  = '<select class="courses" name="wpcw-groups-grading-course-id">';
    $course_select .= '<option value=""> -- Select --</option>';
    $courses = $wpdb->get_results("SELECT * FROM $wpcwdb->courses ORDER BY course_title ASC");

    foreach ($courses as $course) {
        $course_select .= sprintf('<option value="%d">%s</option>', $course->course_id, $course->course_title);
    }
    $course_select .= "</select>";
    return $course_select;
}

function wpcw_groups_grading_AddUserToCourse($user_id, $course_id) {
    WPCW_courses_syncUserAccess($user_id, $course_id); 
}

/**
 * Show list of groups on user edit profile page
 * 
 * @param WP User object $user
 */
function groups_grading_AddGroupInstructor($user) {
   global $wpdb;

   $group_id = get_user_meta($user->ID, 'instructor_group_id');

   echo "<h3>User is Instructor of Group</h3>";
   $group_table = _groups_get_tablename('group');
   $query = "SELECT * FROM $group_table";
   $results = $wpdb->get_results($query);

   $group_select = "<select name='instructor_group_id[]' multiple>";
   $group_select .= "<option value=\"\"> -- None --</option>";

   foreach ($results as $result) {
       $checked = ( in_array($result->group_id, $group_id)) ? "selected" : "";
       $group_select .= "<option value=\"$result->group_id\" $checked>$result->name</option>";
   }

   $group_select .= "</select>";

   echo $group_select;
}

/**
 * Save group id as user meta "instructor_group_id".
 * Basically make a user instructor for a particular group
 */
function groups_grading_UpdateGroupInstructor() {
    global $wpdb, $current_user, $user_ID;

    if (!empty($_REQUEST['user_id']))
        $user_ID = intval($_REQUEST['user_id']);

    if (isset($_REQUEST['instructor_group_id'])) {
        delete_user_meta($user_ID, 'instructor_group_id');
        foreach($_REQUEST['instructor_group_id'] as $id)
        {
            $new_group_id = intval($id);
            add_user_meta($user_ID, 'instructor_group_id', $new_group_id);
        }
    }
}

function wpcw_gg_getFirstUnitLink($course_id) {
    global $wpdb;
    
    $tbl_unit = $wpdb->prefix . 'wpcw_units_meta';
    $unit_id = $wpdb->get_var("SELECT unit_id FROM $tbl_unit WHERE parent_course_id='$course_id' ORDER BY unit_id ASC LIMIT 0,1");
    return get_permalink($unit_id);
}