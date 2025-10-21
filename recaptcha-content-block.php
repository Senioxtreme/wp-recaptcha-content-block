<?php
/*
Plugin Name: reCAPTCHA Content Block (Refactored)
Plugin URI: https://github.com/Senioxtreme/wp-recaptcha-content-block/
Description: Aggiunge un blocco Gutenberg che protegge un qualunque blocco o contenuto con reCAPTCHA, con validazione sicura lato server.
Version: 2.0.0
Author: Senioxtreme
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
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'wp_ajax_rcb_verify_recaptcha', [ $this, 'handle_recaptcha_verify' ] );
        add_action( 'wp_ajax_nopriv_rcb_verify_recaptcha', [ $this, 'handle_recaptcha_verify' ] );
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'rcb-recaptcha-block', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public function enqueue_editor_assets() {
        wp_enqueue_script(
            'rcb-block-editor-script',
            plugins_url( 'block/editor.js', __FILE__ ),
            [ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-i18n' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'block/editor.js' )
        );

        wp_enqueue_style(
            'rcb-block-editor-style',
            plugins_url( 'block/editor.css', __FILE__ ),
            [ 'wp-edit-blocks' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'block/editor.css' )
        );
    }

    public function register_block_type() {
        register_block_type( 'rcb/recaptcha-content-block', [
            'editor_script'   => 'rcb-block-editor-script',
            'editor_style'    => 'rcb-block-editor-style',
            'render_callback' => [ $this, 'render_protected_content' ],
            'attributes'      => [
                'buttonText' => [
                    'type'    => 'string',
                    'default' => __( 'Mostra il contenuto', 'rcb-recaptcha-block' ),
                ],
            ],
        ] );
    }

    public function render_protected_content( $attributes, $content ) {
        $site_key = get_option( 'rcb_site_key', '' );
        $secret_key = get_option( 'rcb_secret_key', '' );

        if ( ( empty( $site_key ) || empty( $secret_key ) ) ) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<p style="color:red;">' . esc_html__( 'reCAPTCHA non è configurato. Aggiungi Site Key e Secret Key nelle impostazioni del plugin.', 'rcb-recaptcha-block' ) . '</p>';
            }
            return '';
        }

        if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            return $content;
        }

        $transient_key = 'rcb_content_' . md5( uniqid( rand(), true ) );
        set_transient( $transient_key, $content, 15 * MINUTE_IN_SECONDS );

        $this->enqueue_frontend_assets();

        $button_text = isset( $attributes['buttonText'] ) ? $attributes['buttonText'] : __( 'Mostra il contenuto', 'rcb-recaptcha-block' );

        ob_start();
        ?>
        <div class="rcb-container" data-transient-key="<?php echo esc_attr( $transient_key ); ?>">
            <button class="rcb-reveal-button"><?php echo esc_html( $button_text ); ?></button>
            <div class="rcb-recaptcha-wrapper"></div>
            <div class="rcb-protected-content-placeholder"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'rcb-block-style',
            plugins_url( 'block/style.css', __FILE__ ),
            [],
            filemtime( plugin_dir_path( __FILE__ ) . 'block/style.css' )
        );

        wp_enqueue_script(
            'google-recaptcha',
            'https://www.google.com/recaptcha/api.js?onload=rcbInit&render=explicit',
            [], null, true
        );

        wp_enqueue_script(
            'rcb-frontend-script',
            plugins_url( 'assets/frontend.js', __FILE__ ),
            [ 'google-recaptcha' ],
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/frontend.js' ),
            true
        );

        wp_localize_script( 'rcb-frontend-script', 'rcb_data', [
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'site_key'        => get_option( 'rcb_site_key', '' ),
            'nonce'           => wp_create_nonce( 'rcb-verify-nonce' ),
            'loading_message' => esc_html__( 'Verifica in corso...', 'rcb-recaptcha-block' ),
            'error_message'   => esc_html__( 'Verifica fallita. Per favore, riprova.', 'rcb-recaptcha-block' ),
        ] );
    }

    public function handle_recaptcha_verify() {
        check_ajax_referer( 'rcb-verify-nonce', 'nonce' );

        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
        $transient_key = isset( $_POST['transient_key'] ) ? sanitize_text_field( $_POST['transient_key'] ) : '';

        if ( empty( $token ) || empty( $transient_key ) ) {
            wp_send_json_error( [ 'message' => __( 'Richiesta non valida.', 'rcb-recaptcha-block' ) ] );
        }

        $secret_key = get_option( 'rcb_secret_key' );
        $response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret_key,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => __( 'Errore di comunicazione con i server di Google.', 'rcb-recaptcha-block' ) ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body['success'] ) || $body['success'] !== true ) {
            wp_send_json_error( [ 'message' => __( 'Verifica reCAPTCHA non riuscita.', 'rcb-recaptcha-block' ) ] );
        }

        $content = get_transient( $transient_key );

        if ( false === $content ) {
            wp_send_json_error( [ 'message' => __( 'Contenuto scaduto o non trovato. Ricarica la pagina e riprova.', 'rcb-recaptcha-block' ) ] );
        }

        delete_transient( $transient_key );
        wp_send_json_success( [ 'html' => wpautop( $content ) ] );
    }

    public function add_admin_menu() {
        add_options_page(
            __( 'Impostazioni reCAPTCHA Content Block', 'rcb-recaptcha-block' ),
            __( 'reCAPTCHA Content Block', 'rcb-recaptcha-block' ),
            'manage_options',
            'rcb-settings',
            [ $this, 'render_settings_page' ]
        );
    }
    
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
    
        if ( isset( $_POST['rcb_save_settings'] ) && check_admin_referer( 'rcb_save_settings_nonce' ) ) {
            $site_key = sanitize_text_field( $_POST['rcb_site_key'] );
            $secret_key = sanitize_text_field( $_POST['rcb_secret_key'] );
    
            update_option( 'rcb_site_key', $site_key );
            update_option( 'rcb_secret_key', $secret_key );
            
            add_settings_error( 'rcb_messages', 'rcb_message', __( 'Impostazioni salvate con successo.', 'rcb-recaptcha-block' ), 'updated' );
        }
    
        settings_errors( 'rcb_messages' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Impostazioni reCAPTCHA Content Block', 'rcb-recaptcha-block' ); ?></h1>
            <p><?php printf(
                wp_kses(
                    __( 'Per ottenere le chiavi, registra il tuo sito presso la %1$sGoogle reCAPTCHA admin console%2$s. Usa chiavi di tipo v2 "I\'m not a robot" Checkbox.', 'rcb-recaptcha-block' ),
                    [ 'a' => [ 'href' => [], 'target' => [] ] ]
                ),
                '<a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">',
                '</a>'
            ); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field( 'rcb_save_settings_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="rcb_site_key"><?php esc_html_e( 'Site Key', 'rcb-recaptcha-block' ); ?></label></th>
                        <td><input type="text" id="rcb_site_key" name="rcb_site_key" value="<?php echo esc_attr( get_option( 'rcb_site_key' ) ); ?>" size="40" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="rcb_secret_key"><?php esc_html_e( 'Secret Key', 'rcb-recaptcha-block' ); ?></label></th>
                        <td><input type="text" id="rcb_secret_key" name="rcb_secret_key" value="<?php echo esc_attr( get_option( 'rcb_secret_key' ) ); ?>" size="40" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Salva Impostazioni', 'rcb-recaptcha-block' ), 'primary', 'rcb_save_settings' ); ?>
            </form>
        </div>
        <?php
    }
}

RCB_Plugin::get_instance();

