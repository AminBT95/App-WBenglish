<?php
/**
 * Plugin Name: WB English Mobile Bridge — pilote
 * Description: Cours et quiz mobiles limités au compte élève de test et aux cours autorisés.
 * Version: 0.3.0
 * Requires PHP: 7.4
 * Requires at least: 5.6
 */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/quiz.php';
require_once __DIR__ . '/words.php';

final class WB_English_Mobile_Bridge {
    const NS = 'wbenglish-mobile/v1';
    const OPTION = 'wbenglish_mobile_pilot';
    private static $app_authenticated = false;

    public static function boot() {
        add_action('application_password_did_authenticate', function () { self::$app_authenticated = true; }, 10, 0);
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('admin_menu', function () {
            add_options_page('WB English Mobile', 'WB English Mobile', 'manage_options', 'wbenglish-mobile', array(__CLASS__, 'settings_page'));
        });
        add_action('admin_init', function () {
            register_setting('wbenglish_mobile', self::OPTION, array('sanitize_callback' => array(__CLASS__, 'sanitize_settings')));
        });
        add_filter('rest_post_dispatch', function ($response, $server, $request) {
            if (strpos($request->get_route(), '/' . self::NS . '/') === 0 && $response instanceof WP_REST_Response) {
                $response->header('Cache-Control', 'private, no-store, max-age=0');
                $response->header('Vary', 'Authorization');
            }
            return $response;
        }, 10, 3);
    }
    public static function sanitize_settings($input) {
        $previous = self::settings();
        if (!is_array($input)) {
            add_settings_error(self::OPTION, 'wb_invalid_settings', 'Réglages invalides. Aucun changement enregistré.', 'error');
            return $previous;
        }
        $value = $input['user_id'] ?? '';
        if (!is_scalar($value)) {
            add_settings_error(self::OPTION, 'wb_invalid_student', "Saisissez l’ID numérique ou l’identifiant WordPress de l’élève. Aucun changement enregistré.", 'error');
            return $previous;
        }
        $value = trim((string)$value);
        // Only an explicit zero disables access. Empty/invalid fields must never silently disable it.
        $user_id = 0;
        if ($value !== '0') {
            $user = $value === '' ? false : get_user_by(ctype_digit($value) ? 'id' : 'login', $value);
            if (!$user) {
                add_settings_error(self::OPTION, 'wb_student_not_found', "Compte introuvable : utilisez l’ID WordPress (user_id dans la page de modification du compte) ou son identifiant de connexion. Aucun changement enregistré.", 'error');
                return $previous;
            }
            if (user_can($user, 'manage_options') || user_can($user, 'edit_posts')) {
                add_settings_error(self::OPTION, 'wb_student_privileged', "Ce compte possède des droits d’administration ou d’édition. Choisissez un compte élève dédié sans ces droits. Aucun changement enregistré.", 'error');
                return $previous;
            }
            $user_id = (int)$user->ID;
        }
        $course_value = $input['course_ids'] ?? '';
        if (!is_scalar($course_value)) {
            add_settings_error(self::OPTION, 'wb_invalid_courses', 'IDs de cours invalides. Aucun changement enregistré.', 'error');
            return $previous;
        }
        $ids = preg_split('/[\s,;]+/', (string)$course_value, -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_slice(array_values(array_unique(array_filter(array_map('absint', $ids)))), 0, 20);
        return array('user_id' => $user_id, 'course_ids' => implode(',', $ids), 'quiz_write' => !empty($input['quiz_write']) ? 1 : 0);
    }
    private static function settings() { return (array)get_option(self::OPTION, array()); }
    private static function ids() {
        return array_filter(array_map('absint', explode(',', self::settings()['course_ids'] ?? '')));
    }
    public static function settings_page() {
        if (!current_user_can('manage_options')) { return; }
        $s = self::settings();
        ?>
        <div class="wrap"><h1>WB English Mobile — pilote 0.3.0</h1>
        <p>Lecture des cours et envoi facultatif des quiz. Activez d'abord sur une copie de test. Créez un compte élève dédié, inscrivez-le aux cours de test et générez un mot de passe d'application dans son profil WordPress.</p>
        <p>Seul cet élève peut utiliser cette API. Aucun compte administrateur ou éditeur. Mettre son identifiant à 0 désactive l'accès.</p>
        <?php settings_errors(self::OPTION); ?>
        <form action="options.php" method="post">
        <?php settings_fields('wbenglish_mobile'); ?>
        <table class="form-table">
        <tr><th><label for="wb-user">Élève : ID ou identifiant WordPress</label></th><td><input id="wb-user" type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[user_id]" value="<?php echo esc_attr($s['user_id'] ?? 0); ?>"><p class="description">Saisissez son ID numérique ou son identifiant de connexion (pas son nom affiché). Saisir 0 désactive l’accès.</p>
        <?php $selected = get_user_by('id', (int)($s['user_id'] ?? 0)); if ($selected) { ?>
        <p><strong>Compte enregistré :</strong> <?php echo esc_html($selected->user_login); ?> — ID <?php echo esc_html((string)$selected->ID); ?> — rôles : <?php echo esc_html(implode(', ', $selected->roles)); ?></p>
        <?php } else { ?><p>Aucun élève configuré : accès désactivé.</p><?php } ?></td></tr>
        <tr><th><label for="wb-courses">IDs des cours autorisés</label></th><td><input id="wb-courses" class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[course_ids]" value="<?php echo esc_attr($s['course_ids'] ?? ''); ?>"><p class="description">Exemple : 123,456. Maximum 20. Inscription existante obligatoire.</p></td></tr>
        <tr><th>Envoi des quiz</th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[quiz_write]" value="1" <?php checked(!empty($s['quiz_write'])); ?>> Autoriser l’enregistrement des réponses et scores du compte élève de test dans MasterStudy.</label><p class="description">Pour ce pilote : QCM texte, choix multiples et vrai/faux, sans chronomètre ni tirage aléatoire. Utiliser un cours de test.</p></td></tr>
        </table><?php submit_button(); ?></form>
        <p>Pour ce premier pilote, les cours à durée limitée, à abonnement ou « bientôt disponibles » sont bloqués. Les règles de déblocage progressif MasterStudy sont contrôlées avant la lecture des leçons.</p>
        <p>État : <code><?php echo esc_html(rest_url(self::NS . '/status')); ?></code></p></div>
        <?php
    }
    public static function routes() {
        WB_English_Mobile_Quiz::routes();
        WB_English_Words::routes();
        register_rest_route(self::NS, '/status', array('methods' => 'GET', 'permission_callback' => '__return_true', 'callback' => function () {
            return array('bridge' => '0.3.0', 'mode' => 'words-and-quiz-pilot');
        }));
        foreach (array('/courses' => 'courses', '/courses/(?P<id>\d+)' => 'course', '/courses/(?P<id>\d+)/lessons/(?P<lesson>\d+)' => 'lesson') as $route => $method) {
            register_rest_route(self::NS, $route, array('methods' => 'GET', 'permission_callback' => array(__CLASS__, 'permission'), 'callback' => array(__CLASS__, $method)));
        }
    }
    private static function error($message, $status = 403) { return new WP_Error('wb_mobile_denied', $message, array('status' => $status)); }
    public static function permission() {
        if (!is_ssl()) { return self::error('HTTPS obligatoire.'); }
        if (!is_user_logged_in() || !self::$app_authenticated) { return self::error("Utilisez le mot de passe d'application du compte élève de test.", 401); }
        $uid = get_current_user_id();
        if ($uid !== (int)(self::settings()['user_id'] ?? 0) || current_user_can('edit_posts') || current_user_can('manage_options')) {
            return self::error('Compte non autorisé pour ce pilote.');
        }
        if (!class_exists('STM_LMS_User') || !class_exists('STM_LMS_Course') || !class_exists('MasterStudy\\Lms\\Repositories\\CurriculumRepository')) {
            return self::error('Version MasterStudy incompatible ou extension inactive.', 503);
        }
        return true;
    }
    public static function access($id, $lesson = 0) {
        $post = get_post($id);
        if (!in_array($id, self::ids(), true) || !$post || $post->post_type !== 'stm-courses' || $post->post_status !== 'publish' || $post->post_password) {
            return self::error('Cours non disponible dans ce pilote.', 404);
        }
        $uid = get_current_user_id();
        if ((int)$post->post_author === $uid) { return self::error('Utilisez un élève distinct du formateur.'); }
        $enrollment = STM_LMS_Course::get_user_course($uid, $id);
        if (empty($enrollment)) { return self::error("Cet élève n'est pas inscrit au cours."); }
        // Fail closed for conditions not covered by this pilot; avoid automatic enrollment/expiry notifications.
        if (get_post_meta($id, 'expiration_course', true) || !empty($enrollment['subscription_id']) ||
            (class_exists('STM_LMS_Subscriptions') && STM_LMS_Subscriptions::subscription_enabled()) ||
            (function_exists('is_ms_lms_addon_enabled') && is_ms_lms_addon_enabled('subscriptions'))) {
            return self::error('Cours à durée limitée ou abonnement : validation spécifique nécessaire hors de ce pilote.');
        }
        if (STM_LMS_Helpers::masterstudy_lms_is_course_coming_soon($id)) { return self::error('Cours bientôt disponible.'); }
        if (!STM_LMS_User::has_course_access($id, $lesson ?: '', false)) { return self::error('Accès refusé par MasterStudy.'); }
        if ($lesson && class_exists('STM_LMS_Sequential_Drip_Content')) {
            $settings = STM_LMS_Sequential_Drip_Content::stm_lms_get_settings();
            if ((!empty($settings['lock_before_start']) && !STM_LMS_Sequential_Drip_Content::is_lesson_started($lesson, $id)) ||
                STM_LMS_Sequential_Drip_Content::lesson_is_locked($id, $lesson)) {
                return self::error('Cette leçon est encore verrouillée par MasterStudy.');
            }
        }
        return true;
    }
    private static function card($id) {
        return array('id' => $id, 'title' => html_entity_decode(get_the_title($id), ENT_QUOTES, 'UTF-8'),
            'summary' => wp_strip_all_tags(strip_shortcodes(get_post_field('post_excerpt', $id))),
            'url' => get_permalink($id));
    }
    public static function courses() {
        $courses = array(); $unavailable = 0;
        foreach (self::ids() as $id) {
            if (is_wp_error(self::access($id))) { $unavailable++; continue; }
            $courses[] = self::card($id);
        }
        return array('courses' => $courses, 'unavailable' => $unavailable);
    }
    public static function course($request) {
        $id = absint($request['id']); $access = self::access($id);
        if (is_wp_error($access)) { return $access; }
        $result = self::card($id); $result['sections'] = array();
        $repo = new \MasterStudy\Lms\Repositories\CurriculumRepository();
        foreach ($repo->get_curriculum($id, true) as $section) {
            $items = array();
            foreach ($section['materials'] as $material) {
                $pid = (int)$material['post_id']; $post = get_post($pid);
                if (!$post || $post->post_status !== 'publish' || $post->post_password) { continue; }
                $is_quiz = $post->post_type === 'stm-quizzes';
                $supported = $post->post_type === 'stm-lessons' || $is_quiz;
                $items[] = array('id' => $pid, 'title' => $material['title'], 'type' => $is_quiz ? 'quiz' : ($supported ? $material['lesson_type'] : 'activity'),
                    'supported' => $supported, 'locked' => $supported && is_wp_error(self::access($id, $pid)),
                    'completed' => $supported && (bool)STM_LMS_Lesson::is_lesson_completed(get_current_user_id(), $id, $pid));
            }
            $result['sections'][] = array('title' => $section['title'], 'lessons' => $items);
        }
        return $result;
    }
    public static function lesson($request) {
        $id = absint($request['id']); $lid = absint($request['lesson']);
        $repo = new \MasterStudy\Lms\Repositories\CurriculumRepository();
        $course_ids = array_map('intval', $repo->get_lesson_course_ids($lid));
        $post = get_post($lid);
        if (!in_array($id, $course_ids, true) || !$post || $post->post_type !== 'stm-lessons' || $post->post_status !== 'publish' || $post->post_password) {
            return self::error('Leçon non disponible.', 404);
        }
        $access = self::access($id, $lid); if (is_wp_error($access)) { return $access; }
        $type = get_post_meta($lid, 'type', true) ?: 'text';
        $media_type = get_post_meta($lid, $type === 'audio' ? 'audio_type' : 'video_type', true);
        $url = '';
        if (in_array($type, array('audio', 'video'), true)) {
            if ($media_type === 'file' || $media_type === 'html') {
                $url = wp_get_attachment_url(absint(get_post_meta($lid, $media_type === 'file' ? 'file' : 'lesson_video', true))) ?: '';
            } elseif ($media_type === 'ext_link') { $url = (string)get_post_meta($lid, 'lesson_ext_link_url', true); }
        }
        // Native playback accepts HTTPS only. No credential is attached to media requests.
        if (strtolower((string)wp_parse_url($url, PHP_URL_SCHEME)) !== 'https') { $url = ''; }
        $text = html_entity_decode(wp_strip_all_tags(strip_shortcodes(preg_replace('/<\/(p|div|h[1-6])>|<br\s*\/?>/i', "\n", $post->post_content))), ENT_QUOTES, 'UTF-8');
        return array('id' => $lid, 'title' => html_entity_decode(get_the_title($lid), ENT_QUOTES, 'UTF-8'),
            'type' => $type, 'text' => trim($text), 'media_url' => esc_url_raw($url, array('https')),
            'website_only' => !in_array($type, array('text', 'audio', 'video'), true) || ($type !== 'text' && !$url),
            'url' => STM_LMS_Lesson::get_lesson_url($id, $lid),
            'completed' => (bool)STM_LMS_Lesson::is_lesson_completed(get_current_user_id(), $id, $lid));
    }
}
WB_English_Mobile_Bridge::boot();
