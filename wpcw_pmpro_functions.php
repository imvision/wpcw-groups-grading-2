<?php

function wpcw_groups_grading_GetMembershipLevels()
{
    global $wpdb;
    if(isset($wpdb->pmpro_membership_levels))
    {
        $sqlQuery = "SELECT * FROM $wpdb->pmpro_membership_levels ORDER BY id ASC";
        $levels = $wpdb->get_results($sqlQuery, OBJECT);
        $levels_select = "<select name='wpcw_groups_grading_membership_level'><option value=''>-- Select --</option>";
        foreach($levels as $level)
        {
            $levels_select .= sprintf('<option value="%d">%s</option>', $level->id, $level->name);
        }
        $levels_select .= "</select>";
        return $levels_select;
    }
    return FALSE;
}

function wpcw_WPUsers()
{
    $author = get_users(array( 'role' => 'author', 'fields' => array('display_name', 'ID')));
    $editor = get_users(array( 'role' => 'editor', 'fields' => array('display_name', 'ID')));
    $admin  = get_users(array( 'role' => 'administrator', 'fields' => array('display_name', 'ID')));
    $users = array_merge($author, $editor, $admin);
    $users_select = '<select name="wpcw-groups-grading-instructor-id"><option>-- Select --</option>';
    foreach($users as $user)
    {
        $users_select .= sprintf('<option value="%d">%s</option>', $user->ID, $user->display_name);
    }
    $users_select .= '</select>';
    return $users_select;
}

function wpcw_groups_grading_UpdateUserMembership($level, $user_id)
{
    if(function_exists('pmpro_changeMembershipLevel'))
        pmpro_changeMembershipLevel($level, $user_id);
}