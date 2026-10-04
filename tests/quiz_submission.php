<?php
// Isolated persistence tests using the REAL MasterStudy quiz scoring class.
// Usage: php tests/quiz_submission.php /path/to/masterstudy-lms-learning-management-system
namespace MasterStudy\Lms\Repositories {
    class CurriculumRepository { public function get_lesson_course_ids($id) { return $id === 500 ? array(123) : array(999); } }
    class QuizRepository { public function get($id) { return $GLOBALS['quiz']; } }
}
namespace {
    define('ABSPATH', '/test/');
    function add_action() {} function do_action($name, ...$args) { $GLOBALS['hooks']++; if ($name === 'masterstudy_lms_user_quiz_added' && !empty($GLOBALS['grades_enabled'])) { $GLOBALS['grade_updates']++; $GLOBALS['grade_percent'] = $args[0]['progress']; } }
    function wp_strip_all_tags($s) { return strip_tags($s); } function strip_shortcodes($s) { return $s; }
    function wp_json_encode($x) { return json_encode($x); }
    function wp_hash($x,$scheme) { return hash_hmac('sha256',$x,'test-key'); }
    function absint($x) { return abs((int)$x); }
    function get_current_user_id() { return 7; }
    function get_option($key,$default=null) { return array('quiz_write'=>$GLOBALS['write']); }
    function is_wp_error($x) { return $x instanceof WP_Error; }
    function wp_unslash($x) { return is_array($x) ? array_map('wp_unslash',$x) : stripslashes($x); }
    function wp_kses_post($s) { return $s; }
    function esc_url($s) { return $s; }
    function wp_cache_delete() {}
    function current_time($s) { return '2026-10-04 18:00:00'; }
    function get_post($id) { return (object)array('post_type'=>$id===500?'stm-quizzes':'stm-questions','post_status'=>'publish','post_password'=>'','post_title'=>'Question '.$id,'post_content'=>''); }
    function get_post_meta($id,$key,$single=true) { return $GLOBALS['meta'][$id][$key] ?? ''; }
    function is_ms_lms_addon_enabled($addon) { return $addon === 'grades' && !empty($GLOBALS['grades_enabled']); }
    function masterstudy_lms_user_can_write_course_progress($uid,$cid) { return $GLOBALS['access']; }
    function stm_lms_user_answers_name($db) { return 'answers'; }
    function stm_lms_user_quizzes_name($db) { return 'quizzes'; }
    function stm_lms_get_user_quizzes($u,$q,$c,$f,$total) { return count($GLOBALS['wpdb']->quizzes); }
    function stm_lms_get_user_last_quiz($u,$q,$f,$c) { $rows=$GLOBALS['wpdb']->quizzes; return $rows ? end($rows) : null; }
    class WP_Error { public $message; public function __construct($code,$message,$data) { $this->message=$message; } }
    class STM_LMS_Options { public static function get_option($key) { return 'unlimited'; } }
    class STM_LMS_Course { public static function update_course_progress($u,$c) { $GLOBALS['progress_updates']++; } }
    class WB_English_Mobile_Bridge {
        const OPTION = 'test'; const NS = 'test';
        public static function access($course,$quiz) { return $GLOBALS['access'] ? true : new WP_Error('denied','denied',array()); }
    }
    class Request implements \ArrayAccess {
        public $body; public $quiz=500;
        public function __construct($body=array()) { $this->body=$body; }
        public function get_json_params() { return $this->body; }
        public function offsetExists($offset): bool { return true; }
        public function offsetGet($offset): mixed { return $offset==='id'?123:$this->quiz; }
        public function offsetSet($offset,$value): void {} public function offsetUnset($offset): void {}
    }
    class FakeDB {
        public $options='options', $answers=array(), $quizzes=array(), $receipts=array(), $insert_id=0, $fail=false, $busy=false, $engine='InnoDB';
        private $snapshot;
        public function prepare($sql,...$args) { return array($sql,$args); }
        public function get_var($query) {
            [$sql,$args]=$query;
            if (strpos($sql,'GET_LOCK')!==false) return $this->busy?0:1;
            if (strpos($sql,'RELEASE_LOCK')!==false) return 1;
            if (strpos($sql,'SELECT ENGINE')!==false) return $this->engine;
            if (strpos($sql,'SELECT option_value')!==false) return $this->receipts[$args[0]] ?? null;
            throw new \Exception('Unexpected query');
        }
        public function query($query) {
            if ($query==='START TRANSACTION') { $this->snapshot=serialize(array($this->answers,$this->quizzes,$this->receipts)); return 0; }
            if ($query==='COMMIT') return 0;
            if ($query==='ROLLBACK') { [$this->answers,$this->quizzes,$this->receipts]=unserialize($this->snapshot); return 0; }
            if (is_array($query)) { [$sql,$args]=$query; $this->receipts[$args[0]]=$args[1]; return 1; }
            throw new \Exception('Unexpected mutation');
        }
        public function insert($table,$row) {
            if ($this->fail && count($this->answers)===1) return false;
            $this->insert_id++;
            if ($table==='answers') $this->answers[]=$row;
            else { $row['user_quiz_id']=$this->insert_id; $this->quizzes[]=$row; }
            return 1;
        }
    }
    $plugin=$argv[1] ?? '';
    if (!is_file($plugin.'/_core/lms/classes/quiz.php')) { fwrite(STDERR,"Supply MasterStudy plugin path.\n"); exit(2); }
    require $plugin.'/_core/lms/classes/quiz.php';
    require __DIR__.'/../wordpress/wbenglish-mobile-bridge/quiz.php';
    function reset_fixture() {
        $GLOBALS['grades_enabled']=false; $GLOBALS['grade_updates']=0; $GLOBALS['wpdb']=new FakeDB(); $GLOBALS['write']=true; $GLOBALS['access']=true; $GLOBALS['hooks']=0; $GLOBALS['progress_updates']=0;
        $GLOBALS['quiz']=array('title'=>'Quiz de test','questions'=>array(501,502,503),'quiz_attempts'=>'unlimited','attempts'=>null,
            'passing_grade'=>60,'re_take_cut'=>0,'retry_after_passing'=>true);
        $GLOBALS['meta']=array(
          501=>array('type'=>'single_choice','answers'=>array(array('text'=>'Hello','isTrue'=>true),array('text'=>'Goodbye','isTrue'=>false))),
          502=>array('type'=>'multi_choice','answers'=>array(array('text'=>'Apple','isTrue'=>true),array('text'=>'Pear','isTrue'=>true),array('text'=>'Car','isTrue'=>false))),
          503=>array('type'=>'true_false','answers'=>array(array('text'=>'True','isTrue'=>true),array('text'=>'False','isTrue'=>false)))
        );
    }
    $count=0;
    function expect($ok,$name) { global $count; if (!$ok) throw new \Exception('FAIL: '.$name); $count++; echo 'PASS: '.$name."\n"; }
    function payload() {
        $q=WB_English_Mobile_Quiz::read(new Request());
        return array('request_id'=>str_repeat('a',32),'version_token'=>$q['version_token'], 'answers'=>array('501'=>array('0'),'502'=>array('0','1'),'503'=>array('0')));
    }
    reset_fixture();
    $data=WB_English_Mobile_Quiz::read(new Request());
    expect(!isset($data['questions'][0]['grading']) && !isset($data['questions'][0]['options'][0]['value']) && strpos(json_encode($data),'isTrue')===false,'No answer keys sent to app');
    $p=payload(); $r=WB_English_Mobile_Quiz::submit(new Request($p));
    expect(!is_wp_error($r) && $r['score']===100 && $r['passed']===true,'Native MasterStudy grades three supported question types');
    expect(count($wpdb->answers)===3 && count($wpdb->quizzes)===1 && $GLOBALS['progress_updates']===1,'Answers, native attempt and progress update written');
    $again=WB_English_Mobile_Quiz::submit(new Request($p));
    expect($again===$r && count($wpdb->quizzes)===1 && $GLOBALS['progress_updates']===1,'Lost response retry does not duplicate attempt or hooks');
    $p['request_id']=str_repeat('b',32);
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && count($wpdb->quizzes)===1,'Old form cannot create another attempt with a new request ID');
    reset_fixture(); $p=payload(); $p['answers']['501']=array('1'); $p['answers']['502']=array('0','1','2'); $p['score']=100;
    $r=WB_English_Mobile_Quiz::submit(new Request($p));
    expect($r['score']===33 && !$r['passed'],'Tampered score ignored and wrong multi-choice selection fails');
    reset_fixture(); $p=payload(); $p['answers']['501']=array('8');
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->answers,'Unknown option rejected without writes');
    reset_fixture(); $p=payload(); unset($p['answers']['501']); $p['answers']['999']=array('0');
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->answers,'Injected foreign question rejected');
    reset_fixture(); $p=payload(); $wpdb->fail=true;
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->answers && !$wpdb->quizzes && !$wpdb->receipts,'Failed write rolls back partial answers and receipt');
    reset_fixture(); $p=payload(); $GLOBALS['write']=false;
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->quizzes,'Disabled write permission respected');
    reset_fixture(); $p=payload(); $GLOBALS['access']=false;
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->quizzes,'Enrollment or drip access revocation respected at submit');
    reset_fixture(); $p=payload(); $wpdb->busy=true;
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->quizzes,'Concurrent submission lock prevents write');
    reset_fixture(); $p=payload(); $wpdb->engine='MyISAM';
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->quizzes,'Nontransactional tables fail closed');
    reset_fixture(); $GLOBALS['meta'][500]['duration']=5;
    expect(is_wp_error(WB_English_Mobile_Quiz::read(new Request())),'Timed quiz rejected by first pilot');
    reset_fixture(); $p=payload(); $GLOBALS['meta'][501]['answers'][0]['isTrue']=false; $GLOBALS['meta'][501]['answers'][1]['isTrue']=true;
    expect(is_wp_error(WB_English_Mobile_Quiz::submit(new Request($p))) && !$wpdb->quizzes,'Changed correct answer invalidates open form');
    reset_fixture(); $p=payload(); WB_English_Mobile_Quiz::submit(new Request($p)); $GLOBALS['quiz']['quiz_attempts']='limited';$GLOBALS['quiz']['attempts']=1;
    expect(WB_English_Mobile_Quiz::read(new Request())['can_submit']===false,'Attempt limit applied');
    reset_fixture(); $p=payload(); WB_English_Mobile_Quiz::submit(new Request($p)); $GLOBALS['quiz']['retry_after_passing']=false;
    expect(WB_English_Mobile_Quiz::read(new Request())['can_submit']===false,'Retry after passing rule applied');
    reset_fixture(); $GLOBALS['grades_enabled']=true; $p=payload(); $r=WB_English_Mobile_Quiz::submit(new Request($p));
    expect(!is_wp_error($r) && $r['score']===100 && $GLOBALS['grade_updates']===1 && $GLOBALS['grade_percent']===100,'Grades enabled: percentage stored and native grade hook receives score');
    reset_fixture(); $GLOBALS['quiz']['required_answers_ids']='[]';
    expect(!is_wp_error(WB_English_Mobile_Quiz::read(new Request())),'Empty JSON list of required questions accepted');
    reset_fixture(); $GLOBALS['quiz']['required_answers_ids']=array(501);
    expect(is_wp_error(WB_English_Mobile_Quiz::read(new Request())),'Actual required question remains blocked');
    reset_fixture(); $GLOBALS['quiz']['random_answers']=true;
    expect(is_wp_error(WB_English_Mobile_Quiz::read(new Request())),'Randomized answers produce a specific refusal');
    echo "$count quiz checks passed.\n";
}
