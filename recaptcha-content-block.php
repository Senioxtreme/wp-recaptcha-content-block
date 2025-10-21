<?php
/*
Plugin Name: reCAPTCHA, hCaptcha & Turnstile Content Block
Plugin URI: https://github.com/Senioxtreme/wp-recaptcha-content-block/
Description: Aggiunge un blocco Gutenberg e uno shortcode per proteggere contenuti con reCAPTCHA, hCaptcha o Cloudflare Turnstile.
Version: 2.0.0
Author: Senioxtreme / Gemini
Author URI: https://senioxtreme.it
Text Domain: rcb-recaptcha-block
Domain Path: /languages
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Senioxtreme/wp-recaptcha-content-block/',
    __FILE__,
    'wp-recaptcha-content-block'
);
$myUpdateChecker->setBranch('main');

final class RCB_Plugin {
    private static $instance;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'register_block_type' ] );
        add_action( 'init', [ $this, 'register_shortcode' ] );
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'wp_ajax_rcb_verify_captcha', [ $this, 'handle_captcha_verify' ] );
        add_action( 'wp_ajax_nopriv_rcb_verify_captcha', [ $this, 'handle_captcha_verify' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'rcb-recaptcha-block', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script('rcb-block-editor-script', plugins_url( 'block/editor.js', __FILE__ ), [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ], filemtime( plugin_dir_path( __FILE__ ) . 'block/editor.js' ));
        wp_enqueue_style('rcb-block-editor-style', plugins_url( 'block/editor.css', __FILE__ ), [ 'wp-edit-blocks' ], filemtime( plugin_dir_path( __FILE__ ) . 'block/editor.css' ));
    }

    public function register_block_type() {
        register_block_type( 'rcb/recaptcha-content-block', [
            'editor_script'   => 'rcb-block-editor-script',
            'editor_style'    => 'rcb-block-editor-style',
            'render_callback' => [ $this, 'render_protected_content' ],
            'attributes'      => [
                'buttonText' => ['type' => 'string', 'default' => __( 'Mostra il contenuto', 'rcb-recaptcha-block' )],
                'theme' => ['type' => 'string', 'default' => 'light'],
                'mode' => ['type' => 'string', 'default' => 'normal'],
            ],
        ] );
    }

    public function register_shortcode() {
        add_shortcode('contenuto_protetto', [$this, 'handle_shortcode']);
    }

    public function handle_shortcode($atts, $content = null) {
        $attributes = shortcode_atts([
            'buttonText' => __('Mostra il contenuto', 'rcb-recaptcha-block'),
            'theme' => 'light',
            'mode' => 'normal',
        ], $atts);

        return $this->render_protected_content($attributes, $content);
    }
    
    public function render_protected_content( $attributes, $content ) {
        $bypass_roles = get_option('rcb_bypass_roles', ['administrator', 'editor']);
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            if ( ! empty( array_intersect( (array) $user->roles, $bypass_roles ) ) ) {
                return do_shortcode($content);
            }
        }

        $provider = get_option('rcb_provider', 'recaptcha');
        $keys_are_set = false;
        switch($provider) {
            case 'hcaptcha': $keys_are_set = get_option('rcb_hcaptcha_site_key') && get_option('rcb_hcaptcha_secret_key'); break;
            case 'turnstile': $keys_are_set = get_option('rcb_turnstile_site_key') && get_option('rcb_turnstile_secret_key'); break;
            default: $keys_are_set = get_option('rcb_recaptcha_site_key') && get_option('rcb_recaptcha_secret_key'); break;
        }

        if ( ! $keys_are_set ) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<p style="color:red;">' . sprintf(esc_html__( 'Il provider CAPTCHA (%s) non è configurato. Aggiungi le chiavi nelle impostazioni del plugin.', 'rcb-recaptcha-block' ), '<strong>' . ucfirst($provider) . '</strong>') . '</p>';
            }
            return '';
        }
        
        $transient_key = 'rcb_content_' . md5( uniqid( rand(), true ) );
        set_transient( $transient_key, $content, 15 * MINUTE_IN_SECONDS );

        $this->enqueue_frontend_assets();

        $button_text = isset( $attributes['buttonText'] ) ? $attributes['buttonText'] : __( 'Mostra il contenuto', 'rcb-recaptcha-block' );
        $theme = isset($attributes['theme']) ? $attributes['theme'] : 'light';
        $mode = isset($attributes['mode']) ? $attributes['mode'] : 'normal';

        ob_start();
        ?>
        <div class="rcb-container" 
            data-transient-key="<?php echo esc_attr( $transient_key ); ?>"
            data-theme="<?php echo esc_attr($theme); ?>"
            data-mode="<?php echo esc_attr($mode); ?>">
            <button class="rcb-reveal-button"><?php echo esc_html( $button_text ); ?></button>
            <div class="rcb-captcha-wrapper"></div>
            <div class="rcb-protected-content-placeholder"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_frontend_assets() {
        $provider = get_option('rcb_provider', 'recaptcha');
        $script_url = '';
        $site_key = '';

        switch($provider) {
            case 'hcaptcha':
                $script_url = 'https://js.hcaptcha.com/1/api.js?onload=rcbCaptchaInit&render=explicit';
                $site_key = get_option('rcb_hcaptcha_site_key');
                break;
            case 'turnstile':
                $script_url = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=rcbCaptchaInit&render=explicit';
                $site_key = get_option('rcb_turnstile_site_key');
                break;
            case 'recaptcha':
            default:
                $script_url = 'https://www.google.com/recaptcha/api.js?onload=rcbCaptchaInit&render=explicit';
                $site_key = get_option('rcb_recaptcha_site_key');
                break;
        }

        wp_enqueue_style('rcb-block-style', plugins_url( 'block/style.css', __FILE__ ), [], filemtime( plugin_dir_path( __FILE__ ) . 'block/style.css' ));
        wp_enqueue_script('rcb-captcha-api', $script_url, [], null, true);
        wp_enqueue_script('rcb-frontend-script', plugins_url( 'assets/frontend.js', __FILE__ ), ['rcb-captcha-api'], filemtime( plugin_dir_path( __FILE__ ) . 'assets/frontend.js' ), true);

        wp_localize_script( 'rcb-frontend-script', 'rcb_data', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'rcb-verify-nonce' ),
            'provider'        => $provider,
            'site_key'        => $site_key,
            'loading_message' => esc_html__( 'Verifica in corso...', 'rcb-recaptcha-block' ),
            'error_message'   => esc_html__( 'Verifica fallita. Per favore, riprova.', 'rcb-recaptcha-block' ),
        ] );
    }

    public function handle_captcha_verify() {
        check_ajax_referer( 'rcb-verify-nonce', 'nonce' );

        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
        $transient_key = isset( $_POST['transient_key'] ) ? sanitize_text_field( $_POST['transient_key'] ) : '';

        if ( empty( $token ) || empty( $transient_key ) ) {
            wp_send_json_error( [ 'message' => __( 'Richiesta non valida.', 'rcb-recaptcha-block' ) ] );
        }

        $provider = get_option('rcb_provider', 'recaptcha');
        $secret_key = '';
        $verify_url = '';

        switch($provider) {
            case 'hcaptcha':
                $secret_key = get_option('rcb_hcaptcha_secret_key');
                $verify_url = 'https://hcaptcha.com/siteverify';
                break;
            case 'turnstile':
                $secret_key = get_option('rcb_turnstile_secret_key');
                $verify_url = 'https://challenges.cloudflare.com/turnstile/v2/siteverify';
                break;
            case 'recaptcha':
            default:
                $secret_key = get_option('rcb_recaptcha_secret_key');
                $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
                break;
        }

        $response = wp_remote_post( $verify_url, [
            'body' => ['secret' => $secret_key, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR']],
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => __( 'Errore di comunicazione con il server di verifica.', 'rcb-recaptcha-block' ) ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! isset( $body['success'] ) || $body['success'] !== true ) {
            wp_send_json_error( [ 'message' => __( 'Verifica CAPTCHA non riuscita.', 'rcb-recaptcha-block' ) ] );
        }

        $content = get_transient( $transient_key );
        if ( false === $content ) {
            wp_send_json_error( [ 'message' => __( 'Contenuto scaduto o non trovato. Ricarica la pagina e riprova.', 'rcb-recaptcha-block' ) ] );
        }

        delete_transient( $transient_key );
        wp_send_json_success( [ 'html' => do_shortcode(wpautop( $content )) ] );
    }

    public function add_admin_menu() {
        add_options_page( 'Impostazioni CAPTCHA Block', 'CAPTCHA Content Block', 'manage_options', 'rcb-settings', [ $this, 'render_settings_page' ] );
    }
    
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
    
        if ( isset( $_POST['rcb_save_settings'] ) && check_admin_referer( 'rcb_save_settings_nonce' ) ) {
            update_option('rcb_provider', sanitize_text_field($_POST['rcb_provider']));
            update_option('rcb_recaptcha_site_key', sanitize_text_field($_POST['rcb_recaptcha_site_key']));
            update_option('rcb_recaptcha_secret_key', sanitize_text_field($_POST['rcb_recaptcha_secret_key']));
            update_option('rcb_hcaptcha_site_key', sanitize_text_field($_POST['rcb_hcaptcha_site_key']));
            update_option('rcb_hcaptcha_secret_key', sanitize_text_field($_POST['rcb_hcaptcha_secret_key']));
            update_option('rcb_turnstile_site_key', sanitize_text_field($_POST['rcb_turnstile_site_key']));
            update_option('rcb_turnstile_secret_key', sanitize_text_field($_POST['rcb_turnstile_secret_key']));
            
            $bypass_roles = isset($_POST['rcb_bypass_roles']) ? array_map('sanitize_text_field', $_POST['rcb_bypass_roles']) : [];
            update_option('rcb_bypass_roles', $bypass_roles);

            add_settings_error( 'rcb_messages', 'rcb_message', __( 'Impostazioni salvate con successo.', 'rcb-recaptcha-block' ), 'updated' );
        }
    
        settings_errors( 'rcb_messages' );
        $provider = get_option('rcb_provider', 'recaptcha');
        $current_bypass_roles = get_option('rcb_bypass_roles', ['administrator', 'editor']);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Impostazioni CAPTCHA Content Block', 'rcb-recaptcha-block' ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'rcb_save_settings_nonce' ); ?>
                
                <h2><?php esc_html_e('Impostazioni Generali', 'rcb-recaptcha-block'); ?></h2>
                <table class="form-table">
                     <tr valign="top">
                        <th scope="row"><label for="rcb_provider"><?php esc_html_e('Provider CAPTCHA', 'rcb-recaptcha-block'); ?></label></th>
                        <td>
                            <select id="rcb_provider" name="rcb_provider">
                                <option value="recaptcha" <?php selected($provider, 'recaptcha'); ?>>Google reCAPTCHA v2</option>
                                <option value="hcaptcha" <?php selected($provider, 'hcaptcha'); ?>>hCaptcha</option>
                                <option value="turnstile" <?php selected($provider, 'turnstile'); ?>>Cloudflare Turnstile</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Salta CAPTCHA per questi ruoli', 'rcb-recaptcha-block'); ?></th>
                        <td>
                            <?php 
                            $roles = wp_roles()->get_names();
                            foreach ($roles as $role_slug => $role_name) : ?>
                            <label><input type="checkbox" name="rcb_bypass_roles[]" value="<?php echo esc_attr($role_slug); ?>" <?php checked(in_array($role_slug, $current_bypass_roles)); ?>> <?php echo esc_html($role_name); ?></label><br>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Gli utenti con i ruoli selezionati visualizzeranno direttamente il contenuto senza la verifica CAPTCHA.', 'rcb-recaptcha-block'); ?></p>
                        </td>
                    </tr>
                </table>

                <div id="recaptcha-settings" class="provider-settings">
                    <h2>Google reCAPTCHA v2</h2>
                    <p><?php printf( wp_kses( __( 'Ottieni le chiavi dalla %1$sGoogle reCAPTCHA admin console%2$s.', 'rcb-recaptcha-block' ), ['a' => ['href'=>[],'target'=>[]]] ), '<a href="https://www.google.com/recaptcha/admin/create" target="_blank">', '</a>'); ?></p>
                    <table class="form-table">
                        <tr><th scope="row"><label for="rcb_recaptcha_site_key"><?php esc_html_e( 'Site Key', 'rcb-recaptcha-block' ); ?></label></th><td><input type="text" id="rcb_recaptcha_site_key" name="rcb_recaptcha_site_key" value="<?php echo esc_attr( get_option( 'rcb_recaptcha_site_key' ) ); ?>" class="regular-text" /></td></tr>
                        <tr><th scope="row"><label for="rcb_recaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'rcb-recaptcha-block' ); ?></label></th><td><input type="text" id="rcb_recaptcha_secret_key" name="rcb_recaptcha_secret_key" value="<?php echo esc_attr( get_option( 'rcb_recaptcha_secret_key' ) ); ?>" class="regular-text" /></td></tr>
                    </table>
                </div>

                <div id="hcaptcha-settings" class="provider-settings">
                    <h2>hCaptcha</h2>
                    <p><?php printf( wp_kses( __( 'Ottieni le chiavi dal tuo %1$shCaptcha Dashboard%2$s.', 'rcb-recaptcha-block' ), ['a' => ['href'=>[],'target'=>[]]] ), '<a href="https://dashboard.hcaptcha.com/sites" target="_blank">', '</a>'); ?></p>
                    <table class="form-table">
                        <tr><th scope="row"><label for="rcb_hcaptcha_site_key"><?php esc_html_e( 'Site Key', 'rcb-recaptcha-block' ); ?></label></th><td><input type="text" id="rcb_hcaptcha_site_key" name="rcb_hcaptcha_site_key" value="<?php echo esc_attr( get_option( 'rcb_hcaptcha_site_key' ) ); ?>" class="regular-text" /></td></tr>
                        <tr><th scope="row"><label for="rcb_hcaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'rcb-recaptcha-block' ); ?></label></th><td><input type="text" id="rcb_hcaptcha_secret_key" name="rcb_hcaptcha_secret_key" value="<?php echo esc_attr( get_option( 'rcb_hcaptcha_secret_key' ) ); ?>" class="regular-text" /></td></tr>
                    </table>
                </div>

                <div id="turnstile-settings" class="provider-settings">
                    <h2>Cloudflare Turnstile</h2>
                    <p><?php printf( wp_kses( __( 'Ottieni le chiavi dal tuo %1$sCloudflare Dashboard%2$s.', 'rcb-recaptcha-block' ), ['a' => ['href'=>[],'target'=>[]]] ), '<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">', '</a>'); ?></p>
                    <table class="form-table">
                        <tr><th scope="row"><label for="rcb_turnstile_site_key"><?php esc_html_e( 'Site Key', 'rcb-recaptcha-block' ); ?></label></th><td><input type="text" id="rcb_turnstile_site_key" name="rcb_turnstile_site_key" value="<?php echo esc_attr( get_option( 'rcb_turnstile_site_key' ) ); ?>" class="regular-text" /></td></tr>
                        <tr><th scope="row"><label for="rcb_turnstile_secret_key"><?php esc_html_e( 'Secret Key', 'rcb-recaptcha-block' ); ?></label></th><td><input type="text" id="rcb_turnstile_secret_key" name="rcb_turnstile_secret_key" value="<?php echo esc_attr( get_option( 'rcb_turnstile_secret_key' ) ); ?>" class="regular-text" /></td></tr>
                    </table>
                </div>

                <hr>
                <h2><?php esc_html_e('Uso con Shortcode', 'rcb-recaptcha-block'); ?></h2>
                <p><?php esc_html_e("Puoi usare lo shortcode per proteggere contenuti al di fuori dell'editor a blocchi (es. Editor Classico, widget, etc.).", 'rcb-recaptcha-block'); ?></p>
                <p><code>[contenuto_protetto]Il tuo contenuto segreto qui...[/contenuto_protetto]</code></p>
                <p><?php esc_html_e("Puoi anche personalizzare lo shortcode con gli attributi:", 'rcb-recaptcha-block'); ?></p>
                <ul>
                    <li><code>buttonText="Apri il contenuto"</code></li>
                    <li><code>theme="dark"</code></li>
                    <li><code>mode="invisible"</code></li>
                </ul>
                <p><b><?php esc_html_e('Esempio completo:', 'rcb-recaptcha-block'); ?></b> <code>[contenuto_protetto theme="dark" mode="invisible" buttonText="Clicca per la verifica"]Contenuto speciale.[/contenuto_protetto]</code></p>


                <?php submit_button( __( 'Salva Impostazioni', 'rcb-recaptcha-block' ), 'primary', 'rcb_save_settings' ); ?>
            </form>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const providerSelect = document.getElementById('rcb_provider');
                if (providerSelect) {
                    const settingsDivs = document.querySelectorAll('.provider-settings');
                    function toggleProvider() {
                        settingsDivs.forEach(div => div.style.display = 'none');
                        const selectedProvider = providerSelect.value;
                        const el = document.getElementById(selectedProvider + '-settings');
                        if(el) el.style.display = 'block';
                    }
                    providerSelect.addEventListener('change', toggleProvider);
                    toggleProvider();
                }
            });
            </script>
        </div>
        <?php
    }
}
RCB_Plugin::get_instance();

