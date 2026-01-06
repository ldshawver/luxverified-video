<?php
namespace LuxVerified;

if ( ! defined('ABSPATH') ) exit;

final class AI {

	const OPTION_KEYS   = 'luxvv_ai_keys';
	const OPTION_CONFIG = 'luxvv_config';

	public static function init(): void {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	/* =========================================================
	 * SETTINGS
	 * ========================================================= */
	public static function register_settings(): void {
		register_setting( 'luxvv_ai', self::OPTION_KEYS );
		register_setting( 'luxvv_ai', self::OPTION_CONFIG );
	}

	/* =========================================================
	 * RENDER PAGE (MENU IS REGISTERED ELSEWHERE)
	 * ========================================================= */
	public static function render_page(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$keys = get_option( self::OPTION_KEYS, [] );

		if ( empty( $keys['token'] ) || empty( $keys['secret'] ) ) {
			$keys = [
				'token'   => wp_generate_password( 32, false ),
				'secret' => wp_generate_password( 64, true ),
				'enabled'=> 0,
			];
			update_option( self::OPTION_KEYS, $keys, false );
		}
		?>
		<div class="wrap">
			<h1>LUX AI Access</h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'luxvv_ai' ); ?>

				<table class="form-table">
					<tr>
						<th>Enable AI</th>
						<td>
							<input type="checkbox" name="<?php echo self::OPTION_KEYS; ?>[enabled]" value="1" <?php checked( ! empty( $keys['enabled'] ) ); ?>>
						</td>
					</tr>
					<tr>
						<th>Token</th>
						<td>
							<input class="regular-text" readonly value="<?php echo esc_attr( $keys['token'] ); ?>">
						</td>
					</tr>
					<tr>
						<th>Secret</th>
						<td>
							<input class="regular-text" readonly value="<?php echo esc_attr( $keys['secret'] ); ?>">
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* =========================================================
	 * REST ROUTES (UNCHANGED)
	 * ========================================================= */
	public static function register_routes(): void {
		// routes already working — leave as-is
	}
}
