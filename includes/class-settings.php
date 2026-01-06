<?php
namespace LuxVerified;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    private static $instance = null;

    public static function instance() : self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function admin_menu() {
        add_menu_page(
            __( 'LUX Verified', 'lux-verified-video' ),
            __( 'LUX Verified', 'lux-verified-video' ),
            'manage_options',
            'luxvv',
            [ $this, 'settings_page' ],
            'dashicons-yes',
            60
        );
    }

    public function register_settings() {
        register_setting( 'luxvv-settings', 'luxvv_bunny_library_id' );
        register_setting( 'luxvv-settings', 'luxvv_bunny_hostname' );
        register_setting( 'luxvv-settings', 'luxvv_bunny_api_key' );
        register_setting( 'luxvv-settings', 'luxvv_bunny_webhook_url' );
        register_setting( 'luxvv-settings', 'luxvv_forminator_id' );
        register_setting( 'luxvv-settings', 'luxvv_regmagic_shortcode' );
        register_setting( 'luxvv-settings', 'luxvv_w9_iframe' );
        register_setting( 'luxvv-settings', 'luxvv_badge_location' );
        register_setting( 'luxvv-settings', 'luxvv_pmpro_levels' );
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LUX Verified Settings', 'lux-verified-video' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'luxvv-settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Bunny Library ID</th>
                        <td><input type="text" name="luxvv_bunny_library_id" value="<?php echo esc_attr( get_option( 'luxvv_bunny_library_id' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Bunny CDN Hostname</th>
                        <td><input type="text" name="luxvv_bunny_hostname" value="<?php echo esc_attr( get_option( 'luxvv_bunny_hostname' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Bunny API Key</th>
                        <td><input type="text" name="luxvv_bunny_api_key" value="<?php echo esc_attr( get_option( 'luxvv_bunny_api_key' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td><input type="text" name="luxvv_bunny_webhook_url" value="<?php echo esc_url( get_option( 'luxvv_bunny_webhook_url' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Forminator Form (Step 1)</th>
                        <td><input type="text" name="luxvv_forminator_id" value="<?php echo esc_attr( get_option( 'luxvv_forminator_id' ) ); ?>" class="regular-text">
                            <p class="description">Shortcode: [forminator_form id="<?php echo esc_attr( get_option( 'luxvv_forminator_id', '11099' ) ); ?>"]</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">RegistrationMagic Shortcode (Step 2)</th>
                        <td><input type="text" name="luxvv_regmagic_shortcode" value="<?php echo esc_attr( get_option( 'luxvv_regmagic_shortcode' ) ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">W9 / Adobe Iframe (Step 3)</th>
                        <td>
                            <textarea name="luxvv_w9_iframe" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'luxvv_w9_iframe' ) ); ?></textarea>
                            <p class="description">Paste the full iframe here.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Badge Location</th>
                        <td>
                            <select name="luxvv_badge_location">
                                <?php $loc = get_option( 'luxvv_badge_location', 'both' ); ?>
                                <option value="profile" <?php selected( $loc, 'profile' ); ?>>Profile</option>
                                <option value="directory" <?php selected( $loc, 'directory' ); ?>>Directory</option>
                                <option value="both" <?php selected( $loc, 'both' ); ?>>Both</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">PMPro Levels allowed to upload</th>
                        <td><input type="text" name="luxvv_pmpro_levels" value="<?php echo esc_attr( get_option( 'luxvv_pmpro_levels', '' ) ); ?>" class="regular-text">
                            <p class="description">Comma-separated level IDs.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
