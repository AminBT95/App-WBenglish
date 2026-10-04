<?php
// Isolated regression checks. These stubs do not replace integration tests on WordPress.
namespace MasterStudy\Lms\Repositories {
    class CurriculumRepository {
        public function get_lesson_course_ids($id) { return $id === 456 ? array(123) : array(999); }
    }
}
namespace {
    define('ABSPATH', '/test/');
    $GLOBALS['uid'] = 7; $GLOBALS['ssl'] = true; $GLOBALS['enrolled'] = true;
    $GLOBALS['allow'] = true; $GLOBALS['locked'] = false; $GLOBALS['private'] = false;
    $GLOBALS['expiration'] = false; $GLOBALS['options'] = array('user_id' => 7, 'course_ids' => '123');
    function add_action() {} function add_filter() {}
    function is_ssl() { return $GLOBALS['ssl']; }
    function is_user_logged_in() { return $GLOBALS['uid'] > 0; }
    function get_current_user_id() { return $GLOBALS['uid']; }
    function current_user_can($cap) { return $GLOBALS['uid'] === 1; }
    function get_option($key, $default = null) { return $GLOBALS['options']; }
    function absint($x) { return abs((int)$x); }
    function get_post($id) { return (object)array('ID'=>$id,'post_type'=>$id === 123 ? 'stm-courses':'stm-lessons','post_status'=>$GLOBALS['private']?'private':'publish','post_password'=>'','post_author'=>2,'post_content'=>'Hello'); }
    function get_post_meta($id, $key, $single = false) { return $key === 'expiration_course' ? $GLOBALS['expiration'] : ($key === 'type' ? 'text' : ''); }
    function is_ms_lms_addon_enabled($addon) { return false; }
    function is_wp_error($x) { return $x instanceof WP_Error; }
    function wp_parse_url($url, $part) { return parse_url($url, $part); }
    function wp_strip_all_tags($s) { return strip_tags($s); }
    function strip_shortcodes($s) { return $s; }
    function get_the_title($id) { return 'Test'; }
    function esc_url_raw($url, $protocols) { return $url; }
    class WP_Error { public $message; public function __construct($code, $message, $data) { $this->message = $message; } }
    class STM_LMS_User { public static function has_course_access($id, $lesson, $add) { if ($add !== false) throw new \Exception('Auto enrollment requested'); return $GLOBALS['allow']; } }
    class STM_LMS_Course { public static function get_user_course($uid, $id) { return $GLOBALS['enrolled'] ? array('user_course_id'=>1) : array(); } }
    class STM_LMS_Helpers { public static function masterstudy_lms_is_course_coming_soon($id) { return false; } }
    class STM_LMS_Lesson {
        public static function get_lesson_url($course, $lesson) { return 'https://example.test/lesson'; }
        public static function is_lesson_completed($uid, $course, $lesson) { return false; }
    }
    class STM_LMS_Sequential_Drip_Content {
        public static function stm_lms_get_settings() { return array(); }
        public static function lesson_is_locked($course, $lesson) { return $GLOBALS['locked']; }
    }
    require __DIR__ . '/../wordpress/wbenglish-mobile-bridge/wbenglish-mobile-bridge.php';
    $count = 0;
    function expect($condition, $label) { global $count; if (!$condition) throw new \Exception('FAIL: '.$label); $count++; echo 'PASS: '.$label."\n"; }
    $auth = new \ReflectionProperty('WB_English_Mobile_Bridge', 'app_authenticated');
    $auth->setAccessible(true);
    expect(is_wp_error(\WB_English_Mobile_Bridge::permission()), 'Cookie session alone cannot authenticate the pilot');
    $auth->setValue(null, true);
    expect(\WB_English_Mobile_Bridge::permission() === true, 'Selected learner with application password accepted');
    $GLOBALS['uid'] = 8;
    expect(is_wp_error(\WB_English_Mobile_Bridge::permission()), 'Another authenticated learner rejected');
    $GLOBALS['uid'] = 1; $GLOBALS['options']['user_id'] = 1;
    expect(is_wp_error(\WB_English_Mobile_Bridge::permission()), 'Administrator rejected even when selected');
    $GLOBALS['uid'] = 7; $GLOBALS['options']['user_id'] = 7; $GLOBALS['ssl'] = false;
    expect(is_wp_error(\WB_English_Mobile_Bridge::permission()), 'Plain HTTP rejected');
    $GLOBALS['ssl'] = true;
    $req = array('id'=>123, 'lesson'=>456);
    expect(!is_wp_error(\WB_English_Mobile_Bridge::lesson($req)), 'Enrolled learner can retrieve allowed lesson');
    expect(is_wp_error(\WB_English_Mobile_Bridge::lesson(array('id'=>123,'lesson'=>999))), 'Lesson from another course rejected');
    $GLOBALS['locked'] = true;
    expect(is_wp_error(\WB_English_Mobile_Bridge::lesson($req)), 'Drip locked lesson rejected');
    $GLOBALS['locked'] = false; $GLOBALS['allow'] = false;
    expect(is_wp_error(\WB_English_Mobile_Bridge::lesson($req)), 'MasterStudy access denial respected');
    $GLOBALS['allow'] = true; $GLOBALS['enrolled'] = false;
    expect(is_wp_error(\WB_English_Mobile_Bridge::lesson($req)), 'No enrollment rejected, no auto enrollment');
    $GLOBALS['enrolled'] = true; $GLOBALS['expiration'] = true;
    expect(is_wp_error(\WB_English_Mobile_Bridge::lesson($req)), 'Expiring course rejected by pilot');
    $GLOBALS['expiration'] = false; $GLOBALS['private'] = true;
    expect(is_wp_error(\WB_English_Mobile_Bridge::lesson($req)), 'Private lesson rejected');
    echo "$count checks passed.\n";
}
