<?php
function get_user_by($field, $value) {
    $users = array(7 => (object)array('ID'=>7,'user_login'=>'eleve-test','roles'=>array('subscriber')),
        1 => (object)array('ID'=>1,'user_login'=>'admin','roles'=>array('administrator')),
        8 => (object)array('ID'=>8,'user_login'=>'editor','roles'=>array('editor')));
    if ($field === 'id') return $users[(int)$value] ?? false;
    foreach ($users as $user) if ($user->user_login === $value) return $user;
    return false;
}
function user_can($user, $cap) { return $user->ID === 1 || ($user->ID === 8 && $cap === 'edit_posts'); }
function add_settings_error($setting, $code, $message, $type) { $GLOBALS['last_error'] = $code; }
require __DIR__ . '/bridge_access.php';
$GLOBALS['options'] = array('user_id'=>7,'course_ids'=>'123');
$r = WB_English_Mobile_Bridge::sanitize_settings(array('user_id'=>'7', 'course_ids'=>'123,456'));
expect($r['user_id'] === 7 && $r['course_ids'] === '123,456', 'Existing numeric student ID saved');
$r = WB_English_Mobile_Bridge::sanitize_settings(array('user_id'=>'eleve-test', 'course_ids'=>'123'));
expect($r['user_id'] === 7, 'Login resolves to saved numeric ID');
expect(WB_English_Mobile_Bridge::sanitize_settings($r) === $r, 'Repeated WordPress sanitization is stable');
foreach (array('999'=>'wb_student_not_found','1'=>'wb_student_privileged','editor'=>'wb_student_privileged',''=>'wb_student_not_found') as $value=>$error) {
    $r=WB_English_Mobile_Bridge::sanitize_settings(array('user_id'=>$value,'course_ids'=>'456'));
    expect($r === $GLOBALS['options'] && $GLOBALS['last_error'] === $error, 'Invalid or privileged selection preserves configuration and explains refusal: '.$value);
}
$r=WB_English_Mobile_Bridge::sanitize_settings(array('user_id'=>'0','course_ids'=>'123'));
expect($r['user_id'] === 0, 'Explicit zero still disables pilot');
$r=WB_English_Mobile_Bridge::sanitize_settings(array('user_id'=>array(7),'course_ids'=>'123'));
expect($r === $GLOBALS['options'], 'Malformed student field preserves configuration');
