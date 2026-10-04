<?php
/** Private word-of-the-day pilot. No public uploads or changes to MasterStudy tables. */
if (!defined('ABSPATH')) { exit; }
final class WB_English_Words {
    const TYPE = 'wb_mobile_word';
    const MAX_BYTES = 1048576;
    public static function boot() {
        add_action('init', function () {
            register_post_type(self::TYPE, array('public' => false, 'show_ui' => false, 'show_in_rest' => false, 'supports' => array('title', 'editor')));
        });
        add_action('admin_menu', function () {
            if (self::reviewer()) { add_menu_page('Mot du jour', 'WB — Mot du jour', 'read', 'wb-words', array(__CLASS__, 'page'), 'dashicons-microphone'); }
        });
        add_action('admin_post_wb_words_save', array(__CLASS__, 'save_admin'));
    }
    public static function reviewer() {
        $id = (int)get_option('wb_words_reviewer', 0);
        $s = (array)get_option(WB_English_Mobile_Bridge::OPTION, array());
        return is_user_logged_in() && (current_user_can('manage_options') || ($id > 0 && get_current_user_id() === $id && $id !== (int)($s['user_id'] ?? 0)));
    }
    private static function error($text, $status = 400) { return new WP_Error('wb_word_error', $text, array('status' => $status)); }
    private static function key($id, $uid) { return 'wb_word_answer_' . (int)$id . '_' . (int)$uid; }
    private static function answer($id, $uid) { return get_option(self::key($id, $uid), false); }
    public static function routes() {
        foreach (array('/words' => array('GET', 'listing'), '/words/(?P<id>\d+)' => array('GET', 'detail'),
            '/words/(?P<id>\d+)/submit' => array('POST', 'submit'),
            '/words/(?P<id>\d+)/audio/(?P<kind>student|teacher)/(?P<index>\d+)' => array('GET', 'audio')) as $path => $spec) {
            register_rest_route(WB_English_Mobile_Bridge::NS, $path, array('methods' => $spec[0],
                'permission_callback' => array('WB_English_Mobile_Bridge', 'permission'), 'callback' => array(__CLASS__, $spec[1])));
        }
    }
    public static function access($id) {
        $p = get_post($id);
        if (!$p || $p->post_type !== self::TYPE || $p->post_status !== 'private' ||
            (string)get_post_meta($id, '_wb_date', true) > current_time('Y-m-d')) { return self::error('Mot indisponible.', 404); }
        return WB_English_Mobile_Bridge::access((int)get_post_meta($id, '_wb_course', true));
    }
    private static function card($p) {
        $status = get_post_meta($p->ID, '_wb_status_' . get_current_user_id(), true);
        return array('id' => (int)$p->ID, 'word' => $p->post_title, 'instruction' => $p->post_content,
            'date' => get_post_meta($p->ID, '_wb_date', true), 'submitted' => in_array($status, array('sent', 'corrected'), true),
            'corrected' => $status === 'corrected');
    }
    public static function listing() {
        $out = array();
        foreach (get_posts(array('post_type' => self::TYPE, 'post_status' => 'private', 'numberposts' => 30,
            'meta_key' => '_wb_date', 'orderby' => 'meta_value', 'order' => 'DESC')) as $p) {
            if (!is_wp_error(self::access($p->ID))) { $out[] = self::card($p); }
        }
        return array('words' => $out);
    }
    public static function detail($r) {
        $id = (int)$r['id']; $ok = self::access($id); if (is_wp_error($ok)) { return $ok; }
        $out = self::card(get_post($id)); $a = self::answer($id, get_current_user_id());
        $out['submitted'] = (bool)$a; $out['corrected'] = $a && !empty($a['feedback']);
        $out['clips'] = $a ? count($a['clips']) : 0;
        $f = $a['feedback'] ?? array();
        $out['feedback'] = array('text' => $f['text'] ?? '', 'audio' => !empty($f['audio']), 'date' => $f['date'] ?? '');
        return $out;
    }
    /** Validate decoded bytes, audio stream and duration, never trust a filename/MIME sent by a client. */
    public static function validate_clip($encoded) {
        if (!is_string($encoded) || strlen($encoded) > 1398104) { return self::error('Audio trop volumineux : 1 Mo maximum.'); }
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) < 44 || strlen($bytes) > self::MAX_BYTES) { return self::error('Fichier audio invalide ou trop volumineux.'); }
        require_once ABSPATH . WPINC . '/ID3/getid3.php';
        // Use a system temporary file, never WordPress's potentially public uploads/temp fallback.
        $handle = tmpfile();
        if (!$handle) { return self::error('Stockage temporaire privé indisponible.', 503); }
        try {
            $path = stream_get_meta_data($handle)['uri'];
            $real = realpath($path);
            foreach (array(ABSPATH, $_SERVER['DOCUMENT_ROOT'] ?? '') as $root) {
                $base = $root !== '' ? realpath($root) : false;
                if ($base && $real && strpos($real, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {
                    return self::error('Configurer un répertoire temporaire PHP hors du répertoire web.', 503);
                }
            }
            if (fwrite($handle, $bytes) !== strlen($bytes)) { return self::error('Écriture audio impossible.', 503); }
            fflush($handle);
            $info = (new getID3())->analyze($path);
        } finally { fclose($handle); }
        $formats = array('mp4' => array('audio/mp4', 'm4a'), 'quicktime' => array('audio/mp4', 'm4a'),
            'webm' => array('audio/webm', 'webm'), 'matroska' => array('audio/webm', 'webm'),
            'ogg' => array('audio/ogg', 'ogg'), 'wav' => array('audio/wav', 'wav'), 'mp3' => array('audio/mpeg', 'mp3'));
        $format = $info['fileformat'] ?? '';
        $seconds = (float)($info['playtime_seconds'] ?? 0);
        if (!isset($formats[$format]) || empty($info['audio']) || !empty($info['video']) || !empty($info['error']) || $seconds <= 0 || $seconds > 62) {
            return self::error('Audio non reconnu ou supérieur à 60 secondes. Utilisez M4A, MP3, WAV, OGG ou WebM audio.');
        }
        return array('data' => base64_encode($bytes), 'mime' => $formats[$format][0], 'extension' => $formats[$format][1], 'seconds' => round($seconds, 1));
    }
    public static function submit($r) {
        global $wpdb;
        $lock = 'wb_word_' . md5($wpdb->prefix . ':' . (int)$r['id']);
        if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== '1') { return self::error('Envoi en cours. Réessayez.', 503); }
        try { return self::submit_locked($r); }
        finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
    }
    private static function submit_locked($r) {
        $id = (int)$r['id']; $ok = self::access($id); if (is_wp_error($ok)) { return $ok; }
        if (strlen($r->get_body()) > 4300000) { return self::error('Envoi trop volumineux.', 413); }
        $b = $r->get_json_params();
        if (!is_array($b) || !is_string($b['request_id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $b['request_id'])) { return self::error('Identifiant d’envoi invalide.'); }
        $uid = get_current_user_id(); $old = self::answer($id, $uid);
        if ($old) { return $old['request_id'] === $b['request_id'] ? self::detail($r) : self::error('Votre réponse a déjà été envoyée.', 409); }
        if (!isset($b['clips']) || !is_array($b['clips']) || count($b['clips']) < 1 || count($b['clips']) > 3) { return self::error('Envoyez entre 1 et 3 enregistrements.'); }
        $clips = array();
        foreach ($b['clips'] as $c) { $clip = self::validate_clip($c); if (is_wp_error($clip)) { return $clip; } $clips[] = $clip; }
        $a = array('request_id' => $b['request_id'], 'clips' => $clips, 'date' => current_time('mysql'), 'feedback' => array());
        // Per-word database lock serializes retries. Never autoload audio.
        if (!add_option(self::key($id, $uid), $a, '', false)) {
            $saved = self::answer($id, $uid);
            return $saved && $saved['request_id'] === $b['request_id'] ? self::detail($r) : self::error('Réponse déjà envoyée ou stockage indisponible.', 409);
        }
        add_post_meta($id, '_wb_submitter', $uid);
        update_post_meta($id, '_wb_status_' . $uid, 'sent');
        return self::detail($r);
    }
    public static function audio($r) {
        $id = (int)$r['id']; $ok = self::access($id); if (is_wp_error($ok)) { return $ok; }
        $a = self::answer($id, get_current_user_id()); $i = (int)$r['index'];
        $c = $r['kind'] === 'student' ? ($a['clips'][$i] ?? null) : ($i === 0 ? ($a['feedback']['audio'] ?? null) : null);
        return $c ?: self::error('Enregistrement introuvable.', 404);
    }
    private static function check_admin() {
        if (!is_ssl() || !self::reviewer()) { wp_die('Accès refusé : connexion HTTPS et compte autorisé requis.', '', array('response' => 403)); }
    }
    private static function form_start($op, $id = 0) {
        echo '<form method="post" action="' . esc_url(set_url_scheme(admin_url('admin-post.php'), 'https')) . '">';
        wp_nonce_field('wb_words_save');
        echo '<input type="hidden" name="action" value="wb_words_save"><input type="hidden" name="op" value="' . esc_attr($op) . '"><input type="hidden" name="id" value="' . (int)$id . '">';
    }
    private static function players($clips) {
        foreach ($clips as $i => $c) {
            // Validated bytes only; this private admin response is not cached, no public attachment URL exists.
            echo '<p>Audio ' . ($i + 1) . ' <audio controls preload="none" src="data:' . esc_attr($c['mime']) . ';base64,' . esc_attr($c['data']) . '"></audio></p>';
        }
    }
    public static function page() {
        self::check_admin(); nocache_headers();
        $s = (array)get_option(WB_English_Mobile_Bridge::OPTION, array());
        $words = get_posts(array('post_type' => self::TYPE, 'post_status' => 'private', 'numberposts' => 30, 'orderby' => 'ID', 'order' => 'DESC'));
        echo '<div class="wrap"><h1>Mot du jour — pilote</h1><p>1 à 3 audios élève (60 s et 1 Mo chacun), une correction texte et/ou audio. Les enregistrements sont conservés dans la base privée de ce pilote.</p>';
        if (isset($_GET['saved'])) { echo '<div class="notice notice-success"><p>Enregistrement effectué.</p></div>'; }
        if (current_user_can('manage_options')) {
            self::form_start('reviewer');
            $u = get_user_by('id', (int)get_option('wb_words_reviewer', 0));
            echo '<p><label>Formateur autorisé : <input name="reviewer" value="' . esc_attr($u ? $u->user_login : '') . '" placeholder="Identifiant WordPress"></label> <button class="button">Enregistrer le formateur</button></p><p>Laisser vide pour réserver la correction aux administrateurs. Ne pas attribuer un rôle administrateur au formateur.</p></form>';
        }
        echo '<h2>Publier un mot</h2>'; self::form_start('create');
        echo '<p><input name="word" required maxlength="120" placeholder="Mot anglais" class="regular-text"></p><p><textarea name="instruction" required maxlength="4000" rows="4" cols="70" placeholder="Définition, exemple et consigne"></textarea></p><p><label>Date de disponibilité <input type="date" name="day" required value="' . esc_attr(current_time('Y-m-d')) . '"></label> <label>Cours <select name="course">';
        foreach (array_filter(array_map('absint', explode(',', $s['course_ids'] ?? ''))) as $cid) { echo '<option value="' . $cid . '">' . esc_html(get_the_title($cid)) . ' (#' . $cid . ')</option>'; }
        echo '</select></label></p><button class="button button-primary">Publier</button></form><hr><h2>Mots et réponses</h2>';
        foreach ($words as $p) {
            echo '<section style="background:white;padding:20px;margin:16px 0;max-width:900px"><h2>' . esc_html($p->post_title) . ' — ' . esc_html(get_post_meta($p->ID, '_wb_date', true)) . '</h2><p>' . nl2br(esc_html($p->post_content)) . '</p>';
            $link = add_query_arg(array('page' => 'wb-words', 'word' => $p->ID), set_url_scheme(admin_url('admin.php'), 'https'));
            echo '<p><a class="button" href="' . esc_url($link) . '">Ouvrir les réponses et corriger</a></p>';
            if (absint($_GET['word'] ?? 0) !== (int)$p->ID) { echo '</section>'; continue; }
            $ids = array_unique(array_merge(array((int)($s['user_id'] ?? 0)), array_map('intval', get_post_meta($p->ID, '_wb_submitter', false))));
            $found = false;
            foreach ($ids as $uid) {
                $a = self::answer($p->ID, $uid); if (!$a) { continue; } $found = true; $u = get_user_by('id', $uid);
                echo '<h3>Réponse : ' . esc_html($u ? $u->user_login : 'Compte supprimé') . ' — ' . esc_html($a['date']) . '</h3>';
                self::players($a['clips']); self::form_start('feedback', $p->ID);
                echo '<input type="hidden" name="student" value="' . $uid . '"><p><textarea name="feedback" maxlength="4000" rows="4" cols="70" placeholder="Votre correction">' . esc_textarea($a['feedback']['text'] ?? '') . '</textarea></p>';
                if (!empty($a['feedback']['audio'])) { self::players(array($a['feedback']['audio'])); }
                echo '<div class="wb-recorder"><input type="hidden" name="audio" value=""><button type="button" class="button wb-record">Enregistrer la correction</button> <button type="button" class="button wb-stop" disabled>Arrêter</button><p>Ou choisir un audio (60 s / 1 Mo maximum) : <input type="file" accept="audio/*" class="wb-file"></p><audio class="wb-preview" controls hidden></audio><p class="wb-message" role="status"></p></div><p><label><input type="checkbox" name="remove_audio" value="1"> Supprimer l’ancien audio de correction</label></p><button class="button button-primary">Enregistrer la correction</button></form>';
            }
            if (!$found) { echo '<p>En attente de réponse de l’élève.</p>'; }
            if (current_user_can('manage_options')) { self::form_start('delete', $p->ID); echo '<p><button class="button" onclick="return confirm(\'Supprimer ce mot et tous ses audios ?\')">Supprimer ce mot et ses réponses</button></p></form>'; }
            echo '</section>';
        }
        echo '</div><script src="' . esc_url(plugins_url('words-admin.js', __FILE__)) . '"></script>';
    }
    public static function save_admin() {
        self::check_admin(); check_admin_referer('wb_words_save');
        $op = sanitize_key($_POST['op'] ?? '');
        if ($op === 'feedback' || $op === 'delete') {
            global $wpdb;
            $lock = 'wb_word_' . md5($wpdb->prefix . ':' . absint($_POST['id'] ?? 0));
            if ((string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== '1') { wp_die('Envoi en cours. Réessayez.'); }
            register_shutdown_function(function () use ($wpdb, $lock) { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); });
        }
        $s = (array)get_option(WB_English_Mobile_Bridge::OPTION, array());
        if ($op === 'reviewer') {
            if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
            $login = sanitize_user(wp_unslash($_POST['reviewer'] ?? '')); $u = $login === '' ? false : get_user_by('login', $login);
            if ($login !== '' && (!$u || (int)$u->ID === (int)($s['user_id'] ?? 0))) { wp_die('Choisissez un compte formateur existant, différent de l’élève.'); }
            update_option('wb_words_reviewer', $u ? (int)$u->ID : 0, false);
        } elseif ($op === 'create') {
            if ((int)wp_count_posts(self::TYPE)->private >= 30) { wp_die('Pilote limité à 30 mots. Un administrateur peut supprimer les anciens tests.'); }
            $word = sanitize_text_field(wp_unslash($_POST['word'] ?? '')); $instruction = sanitize_textarea_field(wp_unslash($_POST['instruction'] ?? ''));
            $day = sanitize_text_field(wp_unslash($_POST['day'] ?? '')); $course = absint($_POST['course'] ?? 0);
            $date = DateTime::createFromFormat('!Y-m-d', $day);
            $cp = get_post($course);
            if (!$word || strlen($word) > 480 || !$instruction || strlen($instruction) > 16000 || !$date || $date->format('Y-m-d') !== $day || !$cp || $cp->post_type !== 'stm-courses' || $cp->post_status !== 'publish' || !in_array($course, array_map('absint', explode(',', $s['course_ids'] ?? '')), true)) { wp_die('Vérifiez le mot, la consigne, la date et le cours autorisé.'); }
            $id = wp_insert_post(wp_slash(array('post_type' => self::TYPE, 'post_status' => 'private', 'post_title' => $word, 'post_content' => $instruction)), true);
            if (is_wp_error($id)) { wp_die(esc_html($id->get_error_message())); }
            update_post_meta($id, '_wb_course', $course); update_post_meta($id, '_wb_date', $day);
        } elseif ($op === 'feedback' || $op === 'delete') {
            $id = absint($_POST['id'] ?? 0); $p = get_post($id);
            if (!$p || $p->post_type !== self::TYPE) { wp_die('Mot introuvable.'); }
            if ($op === 'delete') {
                if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
                $ids = array_unique(array_merge(array((int)($s['user_id'] ?? 0)), array_map('intval', get_post_meta($id, '_wb_submitter', false))));
                foreach ($ids as $uid) { delete_option(self::key($id, $uid)); }
                wp_delete_post($id, true);
            } else {
                $uid = absint($_POST['student'] ?? 0); $a = self::answer($id, $uid); if (!$a) { wp_die('Réponse introuvable.'); }
                $text = sanitize_textarea_field(wp_unslash($_POST['feedback'] ?? '')); if (strlen($text) > 16000) { wp_die('Correction trop longue.'); }
                $clip = !empty($_POST['remove_audio']) ? null : ($a['feedback']['audio'] ?? null);
                if (!empty($_POST['audio'])) { $clip = self::validate_clip(wp_unslash($_POST['audio'])); if (is_wp_error($clip)) { wp_die(esc_html($clip->get_error_message())); } }
                if (!$text && !$clip) { wp_die('Ajoutez un commentaire ou un audio.'); }
                $a['feedback'] = array('text' => $text, 'audio' => $clip, 'date' => current_time('mysql'), 'reviewer' => get_current_user_id());
                if (!update_option(self::key($id, $uid), $a, false)) { wp_die('Correction inchangée ou écriture impossible. Rechargez pour vérifier.'); }
                update_post_meta($id, '_wb_status_' . $uid, 'corrected');
            }
        } else { wp_die('Action inconnue.'); }
        wp_safe_redirect(add_query_arg(array('page' => 'wb-words', 'saved' => '1', 'word' => isset($id) ? $id : 0), set_url_scheme(admin_url('admin.php'), 'https'))); exit;
    }
}
WB_English_Words::boot();
