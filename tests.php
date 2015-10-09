<?php

function wpcw_gg_RunTests() {
    global $wpdb;
    
    echo "<h3>Running Tests...</h3>";
    
    $course_id = wpcw_gg_getFirstCourseId();
    gg_OK($course_id, "First Course Id: {$course_id}");
    
    $unit_url = wpcw_gg_getFirstUnitLink($course_id);
    gg_OK($unit_url!="", "Unit url is {$unit_url}");
}

function wpcw_gg_getFirstCourseId() {
    global $wpdb;
    $tbl = $wpdb->prefix . 'wpcw_courses';
    return $wpdb->get_var("SELECT course_id FROM $tbl ORDER BY course_id ASC LIMIT 0,1");
}

function gg_OK($result, $msg) {
    if($result) {
        echo "<br>{$msg}<br>";
    } else {
        echo "Test Failed -> {$msg}";
    }
}

function gg_NO($result, $msg) {
    if( !$result ) {
        echo "<br>{$msg}<br>";
    } else {
        echo "<br>Test Failed -> {$msg}<br>";
    }
}