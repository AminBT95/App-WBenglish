<?php
// Isolated tests with real WordPress getID3. Pass a WordPress root as argv[1].
define('ABSPATH', rtrim($argv[1] ?? '', '/') . '/'); define('WPINC', 'wp-includes');
$GLOBALS['uid']=7; $GLOBALS['course_access']=true; $GLOBALS['now']='2026-10-04'; $GLOBALS['store']=array(); $GLOBALS['meta']=array();
function add_action() {} function is_user_logged_in(){return $GLOBALS['uid']>0;}
function current_user_can($cap){return $GLOBALS['uid']===1;} function get_current_user_id(){return $GLOBALS['uid'];}
function get_option($key,$default=false){return $GLOBALS['store'][$key]??$default;}
function add_option($key,$value,$deprecated='',$autoload=null){if(isset($GLOBALS['store'][$key]))return false;if($autoload!==false)throw new Exception('Audio autoloaded');$GLOBALS['store'][$key]=$value;return true;}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key]=$v;}
function add_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key][]=$v;return true;}
function get_post_meta($id,$key,$single=true){return $key==='_wb_date'?($id===11?'2026-10-05':'2026-10-04'):123;}
function get_post($id){return $id===10||$id===11?(object)array('ID'=>$id,'post_type'=>'wb_mobile_word','post_status'=>'private','post_title'=>'Journey','post_content'=>'Use journey in a sentence.'):null;}
function current_time($f){return $f==='Y-m-d'?$GLOBALS['now']:$GLOBALS['now'].' 18:00:00';}
function is_wp_error($x){return $x instanceof WP_Error;}
function wp_tempnam($name){return tempnam(sys_get_temp_dir(),$name);}
class WP_Error {public $message;public function __construct($code,$message,$data){$this->message=$message;}}
class WB_English_Mobile_Bridge {const OPTION='settings';const NS='test';public static function access($id){return $GLOBALS['course_access']&&$id===123?true:new WP_Error('denied','Denied',array());}}
class DB {public $prefix='wp_';public $locked=false;public $released=0;public function prepare($s,$v){return $s;}public function get_var($s){if(strpos($s,'RELEASE_LOCK')!==false){$this->released++;return 1;}return $this->locked?0:1;}}
$GLOBALS['wpdb']=new DB();
class Request implements ArrayAccess {public $params,$body;public function __construct($body=array(),$params=array()){$this->body=$body;$this->params=array_merge(array('id'=>10),$params);}public function get_body(){return json_encode($this->body);}public function get_json_params(){return $this->body;}public function offsetExists(mixed $o):bool{return isset($this->params[$o]);}public function offsetGet(mixed $o):mixed{return $this->params[$o]??null;}public function offsetSet(mixed $o,mixed $v):void{$this->params[$o]=$v;}public function offsetUnset(mixed $o):void{unset($this->params[$o]);}}
require __DIR__.'/../wordpress/wbenglish-mobile-bridge/words.php';
$n=0;function expect($v,$label){global $n;if(!$v)throw new Exception('FAIL: '.$label);$n++;echo 'PASS: '.$label."\n";}
// One second of real, silent 8 kHz mono PCM WAV (not a fake parser result).
$pcm=str_repeat("\0",16000);$wav='RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,1,8000,16000,2,16).'data'.pack('V',strlen($pcm)).$pcm;
$clip=base64_encode($wav);
$v=WB_English_Words::validate_clip($clip);
expect(!is_wp_error($v)&&$v['extension']==='wav'&&$v['seconds']===1.0,'Real WordPress parser validates WAV and duration');
expect(is_wp_error(WB_English_Words::validate_clip(base64_encode(str_repeat('<?php evil();',20)))),'Disguised executable rejected');
expect(is_wp_error(WB_English_Words::validate_clip('***')),'Invalid base64 rejected');
expect(is_wp_error(WB_English_Words::validate_clip(str_repeat('A',1398105))),'Oversized audio rejected before decoding');
$slow=substr_replace($wav,pack('V',100),24,4);$slow=substr_replace($slow,pack('V',200),28,4);
expect(is_wp_error(WB_English_Words::validate_clip(base64_encode($slow))),'Overlong audio rejected using actual duration');
$GLOBALS['uid']=8;$GLOBALS['store']['wb_words_reviewer']=8;
expect(WB_English_Words::reviewer(),'Explicitly assigned reviewer accepted');
$GLOBALS['store']['settings']=array('user_id'=>8);
expect(!WB_English_Words::reviewer(),'Reviewer cannot also be selected student');
$GLOBALS['uid']=7;
expect(!WB_English_Words::reviewer(),'Student cannot review');
$r=new Request(array('request_id'=>str_repeat('a',32),'clips'=>array($clip,$clip)));
$result=WB_English_Words::submit($r);
expect(!is_wp_error($result)&&$result['submitted']&&$result['clips']===2,'Multiple learner clips saved');
expect(!isset($result['feedback']['audio']['data']),'Detail does not expose audio payload');
expect(count($GLOBALS['meta'][10]['_wb_submitter'])===1,'Reviewer index written once');
WB_English_Words::submit($r);
expect(count($GLOBALS['meta'][10]['_wb_submitter'])===1,'Retry same request is idempotent');
$r2=new Request(array('request_id'=>str_repeat('b',32),'clips'=>array($clip)));
expect(is_wp_error(WB_English_Words::submit($r2)),'Second submission cannot replace first');
$a=WB_English_Words::audio(new Request(array(),array('kind'=>'student','index'=>0)));
expect(!is_wp_error($a)&&$a['data']===$clip,'Student can read own recording');
$GLOBALS['uid']=9;
expect(is_wp_error(WB_English_Words::audio(new Request(array(),array('kind'=>'student','index'=>0,'student'=>7)))),'Foreign learner cannot retrieve another student recording via injected ID');
expect(is_wp_error(WB_English_Words::submit(new Request(array('request_id'=>str_repeat('c',32),'clips'=>array())))),'Empty submission rejected');
expect(is_wp_error(WB_English_Words::submit(new Request(array('request_id'=>str_repeat('c',32),'clips'=>array($clip,$clip,$clip,$clip))))),'More than three clips rejected');
$GLOBALS['uid']=7;
$GLOBALS['store']['wb_word_answer_10_7']['feedback']=array('text'=>'Good sentence!','date'=>'2026-10-04','audio'=>$v);
$d=WB_English_Words::detail($r);
expect($d['corrected']&&$d['feedback']['text']==='Good sentence!'&&$d['feedback']['audio']===true,'Text and audio feedback visible to owner');
$GLOBALS['uid']=9;
$d=WB_English_Words::detail($r);
expect(!$d['submitted']&&!$d['corrected']&&$d['feedback']['text']==='','No other learner feedback leaks');
$GLOBALS['uid']=7;$GLOBALS['course_access']=false;
expect(is_wp_error(WB_English_Words::audio(new Request(array(),array('kind'=>'student','index'=>0)))),'Revoked course access blocks audio');
expect(is_wp_error(WB_English_Words::submit($r)),'Revoked course access blocks submission even on retry');
$GLOBALS['course_access']=true;
expect(is_wp_error(WB_English_Words::detail(new Request(array(),array('id'=>11)))),'Future word hidden');
$GLOBALS['wpdb']->locked=true;
expect(is_wp_error(WB_English_Words::submit($r)),'Concurrent submission fails closed');
expect($GLOBALS['wpdb']->released===6,'Acquired locks released on successful and rejected submissions');
echo "$n word-of-day checks passed.\n";
