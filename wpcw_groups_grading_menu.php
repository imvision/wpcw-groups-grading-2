<?php

function wpcw_groups_grading_menu() {
    add_menu_page('Groups Grading', 'Groups Grading', 'publish_posts', 'wpcw-groups-grading-2', 'wpcw_groups_grading_manage', WP_PLUGIN_URL . '/wpcw-groups-grading-2/icon.png');
    add_submenu_page('wpcw-groups-grading-2', "Instructor Pages", "Instructor Pages", 'publish_posts', "wpcw_groups_grading_InstructorPages", "wpcw_groups_grading_InstructorPages");
    add_submenu_page('wpcw-groups-grading-2', "Groups Grading Help", "Help", 'publish_posts', "wpcw_groups_grading_docs", "wpcw_groups_grading_docs");
    add_submenu_page('wpcw-groups-grading-2', "Plugin Tests", "Plugin Tests", "manage_options", "wpcw_gg_RunTests", "wpcw_gg_RunTests");
}

// Hide Plugin tests item from menu
add_action( 'admin_head', 'wpcw_gg_AdjustMenu', 999 );
function wpcw_gg_AdjustMenu() {
    $status = remove_submenu_page('wpcw-groups-grading-2', 'wpcw_gg_RunTests');
}

function wpcw_groups_grading_manage() {
    echo '<div class="wrap">
            <h1>WPCW Groups Grading - User Management</h1>';

    if (isset($_REQUEST['wpcw-groups-grading-manage-save'])) {

        $invalid_csv = array();
        $users_new = array();
        $user_success = array();

        // Group id
        $group_select = reqVar('select_group_id');

        switch ($group_select) {
            case "1": // existing group
                $group_id = reqVar('wpcw-groups-grading-group-id');
                break;

            case "2": // new group
                $group_name = reqVar('groups_grading_group_name');
                if ($group_name) {
                    // Create a new group
                    $group_id = wpcw_groups_grading_CreateGroup($group_name);

                    // Assign instructor to new group
                    $instructor_id = reqVar('wpcw-groups-grading-instructor-id');
                    if ($instructor_id)
                        wpcw_groups_grading_AddGroupInstructor($group_id, $instructor_id);
                }
                else {
                    $group_id = NULL;
                }
                break;
            default:
                $group_id = NULL;
        }

        // Course id
        $course_id = reqVar('wpcw-groups-grading-course-id');
        $courseTitle = '';
        if ($course_id) {
            $courseDetails = WPCW_courses_getCourseDetails($course_id);
            $courseTitle = $courseDetails->course_title;
        }

        $group_course_rel = false;
        // Add group to the course
        if ($course_id && $group_id) {
            $group_course_rel = true;
            gg_AddGroupToCourse($course_id, $group_id);
        }

        // Membership Level
        $member_level = reqVar('wpcw_groups_grading_membership_level');

        // Parse user
        $users_all = array();
        if (trim($_REQUEST['wpcw-groups-grading-user-emails']) != '') {
            $rows_all = explode("\r\n", $_REQUEST['wpcw-groups-grading-user-emails']);
            $users_all = parseCSVRows($rows_all);
        }

        $create_new_accounts = reqVar('wpcw_create_missing_accounts');

        if (count($users_all) > 0)
            foreach ($users_all as $i => $user_data) {
                $new_pass = '';
                if ($user_data === FALSE) {
                    $invalid_csv[] = $rows_all[$i];
                    continue;
                }

                $user = get_user_by('email', $user_data['user_email']);
                if (!$user && $create_new_accounts) {
                    $user_data['user_login'] = $user_data['user_email'];
                    $new_pass = generatePassword(6);
                    $user_data['user_pass'] = $new_pass;
                    $user_id = wp_insert_user($user_data);
                    $users_new[] = $user_data;
                } else if ($user) {
                    $user_id = $user->ID;
                }

                if ($member_level && $user_id)
                    pmpro_changeMembershipLevel($member_level, $user_id);

                $added_to_group_couse = false;
                if ($group_id && $user_id) {
                    wpcw_groups_grading_AddUserToGroup($user_id, $group_id);
                    $added_to_group_couse = true;
                }

                if ($course_id && $user_id) {
                    wpcw_groups_grading_AddUserToCourse($user_id, $course_id);
                    $added_to_group_couse = true;
                }

                if ($added_to_group_couse OR $create_new_accounts) {
                    $user_success[] = $rows_all[$i];
                    // Send email to user
                    groups_grading_NotifyUser($user_id, $new_pass, $courseTitle, $course_id);
                }
            }

        if ($group_course_rel) {
            echo '<div id="message" class="updated">';
            echo '<p>Group assigned to course.</p>';
            echo '</div>';
        }

        if (trim($_REQUEST['wpcw-groups-grading-user-emails']) == '' OR empty($user_success)) {
            echo '<div id="message" class="error">';
            echo '<p>No users added.</p>';
            echo '</div>';
        }

        // Print errors if any
        if (count($invalid_csv) > 0) {
            echo '<div id="message" class="error">';
            echo '<p>We had an error with some of the users your entered.  Please check the format and enter again:</p>';
            foreach ($invalid_csv as $csv_row) {
                printf('<p>%s</p>', $csv_row);
            }
            echo '</div>';
        }

        // Print users that were added successfully!
        if (count($user_success) > 0) {
            echo '<div id="message" class="updated">';
            echo '<p>The following users were added to the group and/or course:</p>';
            foreach ($user_success as $csv_row) {
                printf('<p>%s</p>', $csv_row);
            }
            echo '</div>';
        }

        // Print list of new users
        if (count($users_new) > 0) {
            echo '<div id="message" class="updated">';
            echo '<p>The following users did not have an account, one was created for them:</p>';
            foreach ($users_new as $user_data) {
                printf('<p>%s</p>', $user_data['user_email']);
            }
            echo '</div>';
        }
    }

    groups_grading_manage_form();

    echo '</div>';
}

function gg_AddGroupToCourse($course_id, $group_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gg_course_groups';
    $course_group_link = $wpdb->get_row("SELECT * FROM $table_name WHERE group_id='$group_id' AND course_id='$course_id'");

    if (!$course_group_link) {
        $wpdb->insert(
                $table_name, array(
            'course_id' => $course_id,
            'group_id' => $group_id
                ), array(
            '%d',
            '%d'
                )
        );
    }
}

function groups_grading_manage_form() {
    ?>
    <div>
        <form method="post" action="">
            <table class="wp-list-table widefat plugins">
                <tbody>

                    <tr class="active" id="select_group_type">
                        <td><strong>Select or create group</strong> (Optional)</td>
                    </tr>
                    <tr>
                        <td>
                            <input type="radio" name="select_group_id" id="select_existing_group" value="1" /> 
                            Existing Group 
                            <input type="radio" name="select_group_id" id="select_new_group" value="2" /> 
                            New Group
                        </td>
                    </tr>
                    <tr id="existing_group">
                        <td>
                            <table>
                                <tr>
                                    <td>Select an existing group</td>
                                    <td><?php echo wpcw_groups_grading_GetGroups(); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr id="new_group">
                        <td>
                            <table>
                                <tr>
                                    <td>New Group Title</td>
                                    <td><input type="text" name="groups_grading_group_name" ></td>
                                </tr>
                                <tr>
                                    <td>Select Group Instructor</td>
                                    <td><?php echo wpcw_WPUsers(); ?></td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr class="active">
                        <td><strong>Select course to add to</strong> (Optional)</td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo wpcw_groups_grading_GetCourses(); ?>
                        </td>
                    </tr>

                    <tr class="active">
                        <td><strong>Select Membership Level</strong> (optional)</td>
                    </tr>
                    <tr>
                        <td>
                            <?php echo wpcw_groups_grading_GetMembershipLevels(); ?>
                        </td>
                    </tr>

                    <tr class="active">
                        <td><strong>Add people by E-mail address</strong> (one at each line)</td>
                    </tr>
                    <tr>
                        <td>
                            <input type="checkbox" name="wpcw_create_missing_accounts" value="1" checked /> 
                            <strong>Create new accounts for anyone missing</strong> 
                        </td>
                    </tr>
                    <tr>
                        <td>
                            (if checked for every email address not found in database, 
                            a new account will be created and that person will be notified by email)
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <textarea name="wpcw-groups-grading-user-emails" style="width: 400px;height: 400px;float:left;"></textarea>
                            <div style="float: left;margin-left: 20px;">
                                <strong>Valid Formats</strong>
                                <br/>
                                <ol>
                                    <li>First Name, Last Name, email</li>
                                    <li>First Name, Last Name, email,</li>
                                    <li>Name, email</li>
                                    <li>email</li>
                                </ol>
                                <br />
                                <span>Note: email can be given as 'user@example.com' or '&lt;user@example.com&gt;'</span>
                            </div>

                        </td>
                    </tr>

                    <tr>
                        <td><input type="submit" name="wpcw-groups-grading-manage-save" value="Save"</td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>

    <script type="text/javascript">
        (function($) {
            $('#existing_group').hide();
            $('#new_group').hide();

            $('#select_existing_group').click(function() {
                $('#existing_group').show();
                $('#new_group').hide();
            });

            $('#select_new_group').click(function() {
                $('#existing_group').hide();
                $('#new_group').show();
            });
        })(jQuery);
    </script>
    <?php
}

function wpcw_groups_grading_InstructorPages() {
    global $wpdb;
    ?>
    <div class="wrap">
        <h1>WPCW Groups Grading - Instructor Pages</h1>
    </div>
    <?php
    $course_group_table = $wpdb->prefix . 'gg_course_groups';
    $groups_group_table = $wpdb->prefix . 'groups_group';
    $groups_user_group_table = $wpdb->prefix . 'groups_user_group';
    $wpcw_progress_table = $wpdb->prefix . 'wpcw_user_progress_quizzes';
    $wpcw_quizzes = $wpdb->prefix . 'wpcw_quizzes';
    $courses_table = $wpdb->prefix . 'wpcw_courses';
    $Gradebook_url = admin_url('admin.php?page=WPCW_showPage_GradeBook&course_id=');
    $sql = "SELECT  us.ID, us.display_name, gg.group_id, gg.`name` AS group_name,cg.course_id, crs.course_title
            FROM $wpdb->usermeta um
            INNER JOIN $wpdb->users us
            ON um.user_id = us.ID
            INNER JOIN $groups_group_table gg
            ON gg.`group_id` = um.`meta_value`
            LEFT JOIN $course_group_table cg
            ON gg.group_id = cg.group_id
            LEFT JOIN $courses_table crs
            ON crs.course_id = cg.course_id
            WHERE meta_key='instructor_group_id'
            ORDER BY um.user_id";
    $result = $wpdb->get_results($sql);
    $users = array();
    $last_user_id = '';
    foreach ($result as $idata) {
        if ($idata->ID != $last_user_id) {
            $last_user_id = $idata->ID;
        }
        $quiz_sql = "SELECT COUNT(*) 
                FROM $wpcw_progress_table pq
                INNER JOIN $wpcw_quizzes qz
                ON pq.`quiz_id` = qz.`quiz_id`
                WHERE qz.parent_course_id = '$idata->course_id'
                AND pq.quiz_needs_marking IN (1,2)
                AND user_id IN 
                (SELECT user_id FROM $groups_user_group_table WHERE group_id='$idata->group_id')";
        $quiz_need_marking = $wpdb->get_var($quiz_sql);
        $users[$last_user_id]['display_name'] = $idata->display_name;
        $users[$last_user_id][] = array(
            'group_id' => $idata->group_id,
            'group_name' => $idata->group_name,
            'course_id' => $idata->course_id,
            'course_title' => $idata->course_title,
            'quiz_unmarked' => $quiz_need_marking
        );
    }
    ?>

    <table class="wp-list-table widefat fixed users" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 30%;">Instructor Name</th>
                <th>Group & Course</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $id => $user) : ?>
                <tr>
                    <td><?php echo $user['display_name']; ?></td>
                    <td>
                        <table>
                            <?php foreach ($user as $row) : ?>
                                <?php if (is_array($row)) : ?>
                                    <tr>
                                        <td>
                                            <?php echo $row['group_name']; ?>
                                            <?php if ($row['course_id'] != '') : ?>
                                                (<?php echo $row['course_title']; ?>)
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['course_id'] != '') : ?>
                                                <a href="<?php echo $Gradebook_url . $row['course_id'] . '&filter=' . $row['group_name']; ?>">View Gradebook</a>
                                                <span>(<?php echo $row['quiz_unmarked'];?>)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>   
    </table>

    <?php
}

function wpcw_groups_grading_docs() {
    ?>
    <div class="wrap">
        <h1>WPCW Groups Grading - Documentation</h1>

        <h3>Requirements</h3>
        <p>
        <ol>
            <li><a href="http://flyplugins.com/">WP Courseware</a> plugin</li>
            <li><a href="http://www.itthinx.com/plugins/groups">Groups</a> plugin</li>
            <li>Paid Membership Pro (Optional)</li>
        </ol>
    </p>
    <hr>

    <h3>Using Groups Grading</h3>
    <p>A list of users is required. User list should be in CSV format. 
        <br />Following format are examples of valid formats:
    </p>
    <ul>
        <li>First Name, Last Name, email</li>
        <li>First Name, Last Name, email,</li>
        <li>Name, email</li>
        <li>email</li>
    </ul>
    <p>
        If the given email address is not already in the system, 
        a new account is created for that email address and user will be notified with an email.
    </p>
    <p>
        All other settings in the form are optional. So this can be used to add people to a course, 
        a group, upgrade membership or all of these in one go.
    </p>
    <p>
        New groups can be created and instructor can be assigned from here as well. To create new Courses go to
        Training Courses.
    </p>

        <!-- <p>This plugin grants Authors and Editors access to Courseware Gradebook.</p> -->
    <p>
        For existing groups edit any user's profile and assign groups to him.
        <br />
        The instructor should be either an Author/Editor or have 'publish_post' capability.
    </p>
    </div>

    <?php
}

function parseCSVRows($rows_all) {
    $users = array();
    if (count($rows_all) > 0)
        foreach ($rows_all as $row) {
            $row = rtrim(trim($row), ",");
            $row_array = explode(',', $row);
            $num_fields = count($row_array);
            $user_data = array();

            switch ($num_fields) {
                case "1":
                    $user_data['user_email'] = $row_array[0];
                    break;

                case "2":
                    $user_data['display_name'] = $row_array[0];
                    $user_data['first_name'] = $row_array[0];
                    $user_data['user_email'] = $row_array[1];
                    break;

                case "3":
                    $user_data['display_name'] = sprintf('%s %s', $row_array[0], $row_array[1]);
                    $user_data['first_name'] = $row_array[0];
                    $user_data['last_name'] = $row_array[1];
                    $user_data['user_email'] = $row_array[2];
                    break;
                default:
                    $users[] = FALSE;
                    continue;
            }

            // Remove '<' & '>' chars from email address
            if (isset($user_data['user_email']))
                $user_data['user_email'] = trim(str_replace(array('>', '<'), '', $user_data['user_email']));

            if (filter_var($user_data['user_email'], FILTER_VALIDATE_EMAIL) !== FALSE) {

                $users[] = $user_data;
            } else {
                $users[] = FALSE;
            }
        }
    return $users;
}

function groups_grading_NotifyUser($user_id, $new_pass, $coursename, $course_id) {
    $site_name = get_bloginfo();
    $youraccount_URL = admin_url('profile.php');
    $login_url = site_url('register');
    $user = get_user_by('id', $user_id);
    $course_url = wpcw_gg_getFirstUnitLink($course_id);

    $content[] = sprintf('Dear %s,', $user->display_name);
    $content[] = '';
    $content[] = sprintf('You have been added to the course: %s', $coursename);
    $content[] = sprintf('First, <a href="%s">click here to log in</a>', $login_url);
    $content[] = sprintf('Then, <a href="%s">go to the start of the course</a>', $course_url);
    $content[] = '<i>We suggest you bookmark the course page!</i>';
    $content[] = '';

    if ($new_pass != '') {
        $siteurl = site_url();
        $content[] = sprintf('You also have a new account on %s', $siteurl);
        $content[] = sprintf('Username: %s', $user->user_login);
        $content[] = sprintf('Password: %s', $new_pass);
        $content[] = sprintf('Firstname: %s', $user->first_name);
        $content[] = sprintf('Lastname: %s', $user->last_name);
        $content[] = '';
        $content[] = sprintf('You can update your name & password on your account page: %s', $youraccount_URL);
        $content[] = '';
    }
    $content[] = 'Thank you!';
    $content[] = $site_name;

    $email_body = implode('<br>', $content);
    $subject = 'You have been added the course: ' . $coursename;
    wp_mail($user->user_email, $subject, $email_body);
}

function generatePassword($length = 8) {
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
}

function reqVar($name, $default = NULL) {
    if (isset($_REQUEST[$name]) && $_REQUEST[$name] != "") {
        return $_REQUEST[$name];
    } else {
        return $default;
    }
}
