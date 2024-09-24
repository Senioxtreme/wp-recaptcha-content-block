<?php
/*
Plugin Name: reCAPTCHA Content Block
Description: Aggiunge un blocco Gutenberg che protegge un qualunque blocco o contenuto con reCAPTCHA.
Version: 1.1.3
Author: Senioxtreme
Author URI: https://senioxtreme.it
*/

require plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/Senioxtreme/wp-recaptcha-content-block/',
    __FILE__,
    'wp-recaptcha-content-block'
);

$myUpdateChecker->setBranch('main');

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'enqueue_block_editor_assets', 'rcb_enqueue_block_editor_assets' );

function rcb_enqueue_block_editor_assets() {
    wp_register_script(
        'rcb-block-editor-script',
        plugins_url( 'block/editor.js', __FILE__ ),
        array( 'wp-blocks', 'wp-element', 'wp-block-editor' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'block/editor.js' )
    );
    wp_enqueue_script( 'rcb-block-editor-script' );

    wp_register_style(
        'rcb-block-editor-style',
        plugins_url( 'block/editor.css', __FILE__ ),
        array( 'wp-edit-blocks' ),
        filemtime( plugin_dir_path( __FILE__ ) . 'block/editor.css' )
    );
    wp_enqueue_style( 'rcb-block-editor-style' );
}

add_action( 'enqueue_block_assets', 'rcb_enqueue_block_assets' );

function rcb_enqueue_block_assets() {
    wp_register_style(
        'rcb-block-style',
        plugins_url( 'block/style.css', __FILE__ ),
        array(),
        filemtime( plugin_dir_path( __FILE__ ) . 'block/style.css' )
    );
    wp_enqueue_style( 'rcb-block-style' );
}

add_action( 'init', 'rcb_register_block_type' );

function rcb_register_block_type() {
    register_block_type( 'rcb/recaptcha-content-block', array(
        'editor_script'   => 'rcb-block-editor-script',
        'editor_style'    => 'rcb-block-editor-style',
        'style'           => 'rcb-block-style',
        'render_callback' => 'rcb_render_protected_content',
    ) );
}

function rcb_render_protected_content( $attributes, $content ) {
    $button_text = isset( $attributes['buttonText'] ) ? $attributes['buttonText'] : 'Mostra il contenuto';
    $container_id = 'rcb-content-' . uniqid();

    $site_key = get_option( 'rcb_site_key', '' );

    if ( empty( $site_key ) ) {
        return '<p>reCAPTCHA non è configurato correttamente. Per favore, configura le chiavi nelle impostazioni del plugin.</p>';
    }

    wp_enqueue_script(
        'google-recaptcha',
        'https://www.google.com/recaptcha/api.js?onload=rcb_onload&render=explicit&hl=it',
        array(),
        null,
        true
    );

    wp_add_inline_script( 'google-recaptcha', rcb_inline_script() );

    $output  = '<div id="' . esc_attr( $container_id ) . '" class="rcb-container">';
    $output .= '<button class="rcb-reveal-button">' . esc_html( $button_text ) . '</button>';
    $output .= '<div class="rcb-protected-content" style="display:none;">' . $content . '</div>';
    $output .= '</div>';

    return $output;
}

function rcb_inline_script() {
    $site_key = esc_js( get_option( 'rcb_site_key', '' ) );

    $script = "
    window.rcb_onload = function() {
        console.log('Funzione rcb_onload eseguita');
        var buttons = document.querySelectorAll('.rcb-container button.rcb-reveal-button');
        buttons.forEach(function(button) {
            button.addEventListener('click', function() {
                console.log('Bottone cliccato');
                var container = button.parentElement;

                var captchaContainer = document.createElement('div');
                captchaContainer.className = 'rcb-recaptcha-container';

                container.insertBefore(captchaContainer, button.nextSibling);

                grecaptcha.render(captchaContainer, {
                    'sitekey': '{$site_key}',
                    'callback': function() {
                        console.log('reCAPTCHA completato con successo');
                        container.querySelector('.rcb-protected-content').style.display = 'block';
                        captchaContainer.style.display = 'none';
                    }
                });

                button.style.display = 'none';
            }, { once: true });
        });
    };
    ";

    return $script;
}

add_action( 'admin_menu', 'rcb_add_admin_menu' );

function rcb_add_admin_menu() {
    add_options_page(
        'Impostazioni reCAPTCHA Content Block',
        'reCAPTCHA Content Block',
        'manage_options',
        'rcb-settings',
        'rcb_settings_page'
    );
}

function rcb_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['rcb_save_settings'] ) && check_admin_referer( 'rcb_save_settings' ) ) {
        $site_key = sanitize_text_field( $_POST['rcb_site_key'] );
        $secret_key = sanitize_text_field( $_POST['rcb_secret_key'] );

        if ( ! empty( $site_key ) && ! empty( $secret_key ) ) {
            update_option( 'rcb_site_key', $site_key );
            update_option( 'rcb_secret_key', $secret_key );

            add_settings_error( 'rcb_messages', 'rcb_message', 'Impostazioni salvate.', 'updated' );
        } else {
            add_settings_error( 'rcb_messages', 'rcb_message', 'Le chiavi non possono essere vuote.', 'error' );
        }
    }

    settings_errors( 'rcb_messages' );

    $site_key   = esc_attr( get_option( 'rcb_site_key', '' ) );
    $secret_key = esc_attr( get_option( 'rcb_secret_key', '' ) );

    ?>
    <div class="wrap">
        <h1>Impostazioni reCAPTCHA Content Block</h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'rcb_save_settings' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Site Key</th>
                    <td><input type="text" name="rcb_site_key" value="<?php echo esc_attr( $site_key ); ?>" size="40" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Secret Key</th>
                    <td><input type="text" name="rcb_secret_key" value="<?php echo esc_attr( $secret_key ); ?>" size="40" /></td>
                </tr>
            </table>
            <?php submit_button( 'Salva Impostazioni', 'primary', 'rcb_save_settings' ); ?>
        </form>
    </div>
    <?php
}
?>
