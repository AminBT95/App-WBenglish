<?php
if (!defined('ABSPATH')) { exit; }

/** First native quiz pilot: fixed text QCM, no timer/bank/advanced scoring. */
final class WB_English_Mobile_Quiz {
    private static function error($message, $status = 400) {
        return new WP_Error('wb_quiz_error', $message, array('status' => $status));
    }
    private static function text($value) {
        return html_entity_decode(wp_strip_all_tags(strip_shortcodes((string)$value)), ENT_QUOTES, 'UTF-8');
    }
    public static function routes() {
        $base = '/courses/(?P<id>\d+)/quizzes/(?P<quiz>\d+)';
        foreach (array($base => array('GET', 'read'), $base . '/submit' => array('POST', 'submit')) as $path => $route) {
            register_rest_route(WB_English_Mobile_Bridge::NS, $path, array(
                'methods' => $route[0], 'callback' => array(__CLASS__, $route[1]),
                'permission_callback' => array('WB_English_Mobile_Bridge', 'permission'),
            ));
        }
    }
    private static function guard($course, $quiz) {
        $access = WB_English_Mobile_Bridge::access($course, $quiz);
        if (is_wp_error($access)) { return $access; }
        $post = get_post($quiz);
        $repo = new \MasterStudy\Lms\Repositories\CurriculumRepository();
        if (!$post || $post->post_type !== 'stm-quizzes' || $post->post_status !== 'publish' || $post->post_password ||
            !in_array($course, array_map('intval', $repo->get_lesson_course_ids($quiz)), true)) {
            return self::error('Quiz non disponible dans ce cours.', 404);
        }
        foreach (array('stm_lms_get_user_quizzes', 'stm_lms_get_user_last_quiz', 'stm_lms_user_answers_name', 'stm_lms_user_quizzes_name', 'masterstudy_lms_user_can_write_course_progress') as $function) {
            if (!function_exists($function)) { return self::error('Version MasterStudy non compatible avec ce pilote quiz.', 503); }
        }
        if (!class_exists('STM_LMS_Quiz') || !class_exists('MasterStudy\\Lms\\Repositories\\QuizRepository')) {
            return self::error('Moteur de quiz MasterStudy indisponible.', 503);
        }
        return true;
    }
    private static function schema($course, $quiz) {
        $q = (new \MasterStudy\Lms\Repositories\QuizRepository())->get($quiz);
        if (!$q) { return self::error('Quiz introuvable.', 404); }
        if (STM_LMS_Quiz::get_quiz_duration($quiz) > 0 || !empty($q['random_questions']) || !empty($q['random_answers']) || !empty($q['required_answers_ids']) ||
            (function_exists('is_ms_lms_addon_enabled') && is_ms_lms_addon_enabled('grades'))) {
            return self::error('Pour ce test, choisir un quiz sans chronomètre, tirage aléatoire, questions obligatoires spécifiques ou module Grades.');
        }
        $ids = array_values(array_unique(array_filter(array_map('absint', $q['questions'] ?? array()))));
        if (!$ids || count($ids) > 50) { return self::error('Le quiz de test doit contenir entre 1 et 50 questions.'); }
        $questions = array();
        foreach ($ids as $id) {
            $post = get_post($id); $type = get_post_meta($id, 'type', true);
            $answers = get_post_meta($id, 'answers', true);
            if (!$post || $post->post_type !== 'stm-questions' || $post->post_status !== 'publish' || $post->post_password ||
                !in_array($type, array('single_choice', 'multi_choice', 'true_false'), true) || !is_array($answers) || count($answers) < 2 || count($answers) > 20) {
                return self::error('Ce pilote accepte les questions publiées : choix unique, choix multiples et vrai/faux.');
            }
            $options = array(); $correct_count = 0; $values = array();
            foreach (array_values($answers) as $index => $answer) {
                if (!is_array($answer) || !isset($answer['text']) || !is_scalar($answer['text']) || !empty($answer['text_image']['url'])) {
                    return self::error('Les réponses illustrées ne sont pas encore prises en charge.');
                }
                $text = (string)$answer['text'];
                if (self::text($text) === '' || in_array($text, $values, true)) { return self::error('Chaque réponse doit contenir un texte distinct.'); }
                $values[] = $text;
                $correct_count += !empty($answer['isTrue']) ? 1 : 0;
                $options[] = array('id' => (string)$index, 'label' => self::text($text), 'value' => $text);
            }
            if (!$correct_count || ($type !== 'multi_choice' && $correct_count !== 1)) { return self::error('Vérifier les bonnes réponses dans le quiz WordPress.'); }
            $questions[] = array('id' => $id, 'title' => self::text($post->post_title), 'text' => self::text($post->post_content), 'type' => $type, 'options' => $options, 'grading' => $answers);
        }
        $attempts = (int)stm_lms_get_user_quizzes(get_current_user_id(), $quiz, $course, array(), true);
        $last = stm_lms_get_user_last_quiz(get_current_user_id(), $quiz, array(), $course);
        $mode = $q['quiz_attempts'] ?: STM_LMS_Options::get_option('quiz_attempts');
        $can = true; $reason = '';
        if ($mode === 'limited' && (int)$q['attempts'] > 0 && $attempts >= (int)$q['attempts']) { $can = false; $reason = 'Nombre de tentatives autorisées atteint.'; }
        if (!empty($last) && $last['status'] === 'passed' && empty($q['retry_after_passing'])) { $can = false; $reason = 'Quiz déjà réussi. La reprise après réussite est désactivée.'; }
        $grade = (float)($q['passing_grade'] ?? 0); $cut = (float)($q['re_take_cut'] ?? 0);
        if ($grade < 0 || $grade > 100 || $cut < 0 || $cut > 100) { return self::error('Seuil de réussite ou pénalité invalide.'); }
        $data = array('id' => $quiz, 'title' => self::text($q['title']), 'questions' => $questions, 'attempts' => $attempts,
            'passing_grade' => $grade, 'retake_cut' => $cut, 'can_submit' => $can, 'blocked_reason' => $reason,
            'last_result' => $last ? self::result($last) : null);
        // Bound to the learner, course, full grading schema and current native attempt count.
        $data['version_token'] = wp_hash(wp_json_encode(array(get_current_user_id(), $course, $data)), 'auth');
        return $data;
    }
    private static function result($row) {
        return array('attempt_id' => (int)$row['user_quiz_id'], 'score' => (int)$row['progress'], 'passed' => $row['status'] === 'passed',
            'status' => $row['status'], 'saved_at' => (string)($row['created_at'] ?? ''));
    }
    public static function read($request) {
        $course = absint($request['id']); $quiz = absint($request['quiz']);
        $guard = self::guard($course, $quiz); if (is_wp_error($guard)) { return $guard; }
        $data = self::schema($course, $quiz); if (is_wp_error($data)) { return $data; }
        foreach ($data['questions'] as &$question) {
            unset($question['grading']);
            foreach ($question['options'] as &$option) { unset($option['value']); }
            unset($option);
        }
        unset($question, $data['retake_cut']);
        $settings = get_option(WB_English_Mobile_Bridge::OPTION, array());
        if (empty($settings['quiz_write'])) { $data['can_submit'] = false; $data['blocked_reason'] = 'Activer « Envoi des quiz » dans les réglages WB English Mobile.'; }
        return $data;
    }
    private static function grade_answers($data, $submitted) {
        if (!is_array($submitted) || count($submitted) !== count($data['questions'])) { return self::error('Répondre à toutes les questions avant de valider.'); }
        $rows = array(); $correct_count = 0;
        foreach ($data['questions'] as $question) {
            $id = $question['id']; $selection = $submitted[(string)$id] ?? null;
            if (!is_array($selection) || !$selection || count($selection) > count($question['options']) || ($question['type'] !== 'multi_choice' && count($selection) !== 1)) {
                return self::error('Réponse manquante ou format invalide.');
            }
            $values = array(); $seen = array();
            foreach ($selection as $index) {
                if ((!is_int($index) && !is_string($index)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$index) || !isset($question['options'][(int)$index]) || isset($seen[(string)$index])) {
                    return self::error('Option inconnue ou répétée.');
                }
                $seen[(string)$index] = true; $values[] = $question['options'][(int)$index]['value'];
            }
            $answer = $question['type'] === 'multi_choice' ? $values : $values[0];
            $correct = (bool)STM_LMS_Quiz::check_answer($id, $answer, $question['grading']);
            $stored = $question['type'] === 'multi_choice' ? implode(',', STM_LMS_Quiz::encode_answers($values)) : $values[0];
            $rows[] = array('question_id' => $id, 'user_answer' => $stored, 'correct_answer' => $correct ? 1 : 0, 'questions_order' => '');
            $correct_count += $correct ? 1 : 0;
        }
        $score = 100 * $correct_count / count($data['questions']);
        if ($data['attempts'] > 0) { $score *= pow((100 - $data['retake_cut']) / 100, $data['attempts']); }
        return array('rows' => $rows, 'score' => (int)round($score));
    }
    public static function submit($request) {
        global $wpdb;
        $course = absint($request['id']); $quiz = absint($request['quiz']); $uid = get_current_user_id();
        $guard = self::guard($course, $quiz); if (is_wp_error($guard)) { return $guard; }
        $settings = get_option(WB_English_Mobile_Bridge::OPTION, array());
        if (empty($settings['quiz_write']) || !masterstudy_lms_user_can_write_course_progress($uid, $course)) { return self::error('Envoi des quiz non autorisé.', 403); }
        $body = $request->get_json_params();
        if (!is_array($body) || !is_string($body['request_id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $body['request_id']) || !is_string($body['version_token'] ?? null)) { return self::error('Requête de quiz invalide.'); }
        $lock = 'wbm_quiz_' . md5($uid . ':' . $course . ':' . $quiz);
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) !== '1') { return self::error('Une validation est déjà en cours. Réessayez dans quelques secondes.', 409); }
        $transaction = false;
        try {
            $receipt_key = $lock . '_receipt';
            // Direct DB read avoids stale object caches during concurrent retries.
            $stored = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $receipt_key));
            $receipt = $stored ? json_decode($stored, true) : null;
            if (is_array($receipt) && hash_equals($receipt['request_id'], $body['request_id'])) { return $receipt['result']; }
            $data = self::schema($course, $quiz); if (is_wp_error($data)) { return $data; }
            if (!$data['can_submit']) { return self::error($data['blocked_reason'], 409); }
            if (!hash_equals($data['version_token'], $body['version_token'])) { return self::error('Le quiz ou les tentatives ont changé. Revenez au programme puis rouvrez le quiz.', 409); }
            $graded = self::grade_answers($data, $body['answers'] ?? null); if (is_wp_error($graded)) { return $graded; }
            $answers_table = stm_lms_user_answers_name($wpdb); $quizzes_table = stm_lms_user_quizzes_name($wpdb);
            foreach (array($answers_table, $quizzes_table, $wpdb->options) as $table) {
                $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
                if (strtoupper((string)$engine) !== 'INNODB') { return self::error('Ce test exige des tables InnoDB pour enregistrer la tentative sans écriture partielle.', 503); }
            }
            if ($wpdb->query('START TRANSACTION') === false) { return self::error('Impossible de démarrer l’enregistrement.', 503); }
            $transaction = true;
            foreach ($graded['rows'] as $answer) {
                $answer += array('user_id' => $uid, 'course_id' => $course, 'quiz_id' => $quiz, 'attempt_number' => $data['attempts'] + 1);
                if ($wpdb->insert($answers_table, $answer) !== 1) { throw new RuntimeException('answer_write_failed'); }
            }
            $row = array('user_id' => $uid, 'course_id' => $course, 'quiz_id' => $quiz, 'progress' => $graded['score'],
                'status' => $graded['score'] >= $data['passing_grade'] ? 'passed' : 'failed',
                'sequency' => wp_json_encode(array_column($data['questions'], 'id')), 'created_at' => current_time('mysql'));
            if ($wpdb->insert($quizzes_table, $row) !== 1) { throw new RuntimeException('quiz_write_failed'); }
            $row['user_quiz_id'] = (int)$wpdb->insert_id;
            $result = self::result($row);
            $receipt_json = wp_json_encode(array('request_id' => $body['request_id'], 'result' => $result));
            $sql = $wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'", $receipt_key, $receipt_json);
            if ($wpdb->query($sql) === false || $wpdb->query('COMMIT') === false) { throw new RuntimeException('receipt_write_failed'); }
            $transaction = false;
            wp_cache_delete($receipt_key, 'options');
            // Match native side effects after durable answers + attempt + retry receipt.
            try {
                $hook_row = $row; unset($hook_row['user_quiz_id']);
                do_action('masterstudy_lms_user_quiz_added', $hook_row);
                STM_LMS_Course::update_course_progress($uid, $course);
                do_action('stm_lms_quiz_' . $row['status'], $uid, $quiz, $row['progress'], $course);
            } catch (Throwable $error) {
                $result['notice'] = 'Tentative enregistrée ; la mise à jour de progression doit être vérifiée dans WordPress.';
            }
            return $result;
        } catch (Throwable $error) {
            return self::error('Enregistrement non confirmé. Réessayez avec le même formulaire ; en cas de doute, vérifiez la tentative dans WordPress.', 503);
        } finally {
            if ($transaction) { $wpdb->query('ROLLBACK'); }
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }
}
