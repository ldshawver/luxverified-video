<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings = get_option( 'luxvv_settings', [] );

$form_id = isset( $settings['forminator_form_id'] )
	? (int) $settings['forminator_form_id']
	: '';
?>

<div class="wrap">
	<h1>LUX Verified – Settings</h1>

	<h2>Health Check</h2>
	<table class="widefat striped">
		<tbody>
			<tr>
				<th>Version</th>
				<td><?php echo esc_html( LUXVV_VERSION ); ?></td>
			</tr>
			<tr>
				<th>Tables</th>
				<td>
					<?php if ( empty( $health['missing_tables'] ) ) : ?>
						<span>OK</span>
					<?php else : ?>
						<span>Missing: <?php echo esc_html( implode( ', ', $health['missing_tables'] ) ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th>Admin Menu</th>
				<td><?php echo ! empty( $health['menu_attached'] ) ? 'Attached' : 'Missing'; ?></td>
			</tr>
			<tr>
				<th>Bunny Config</th>
				<td>
					<?php
					$bunny_ok = ! empty( $health['bunny_library_id'] ) && ! empty( $health['bunny_api_key'] ) && ! empty( $health['bunny_cdn_host'] );
					echo $bunny_ok ? 'Configured' : 'Missing';
					?>
				</td>
			</tr>
			<tr>
				<th>REST Status</th>
				<td><?php echo ! empty( $health['rest_url'] ) ? esc_html( $health['rest_url'] ) : 'Unavailable'; ?></td>
			</tr>
		</tbody>
	</table>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'luxvv_settings_group' );
		do_settings_sections( 'luxvv_settings' );
		?>

		<h2>Verification Forms</h2>

		<table class="form-table">
			<tr>
				<th>Content Creator Agreement (Step 2)</th>
				<td>
					<input type="number"
						   name="luxvv_settings[agreement_form_id]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'agreement_form_id' ) ); ?>"
					/>
				</td>
			</tr>

			<tr>
				<th>W-9 Tax Form (Step 3)</th>
				<td>
					<input type="number"
						   name="luxvv_settings[w9_form_id]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'w9_form_id' ) ); ?>"
					/>
				</td>
			</tr>
		</table>

		<h2>Bunny Stream</h2>
		<table class="form-table">
			<tr>
				<th>Library ID</th>
				<td>
					<input class="regular-text"
						   name="luxvv_settings[bunny_library_id]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'bunny_library_id' ) ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th>API Key (AccessKey)</th>
				<td>
					<input class="regular-text"
						   type="password"
						   name="luxvv_settings[bunny_api_key]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'bunny_api_key' ) ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th>CDN Hostname</th>
				<td>
					<input class="regular-text"
						   name="luxvv_settings[bunny_cdn_host]"
						   placeholder="vz-xxxxx.b-cdn.net"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'bunny_cdn_host' ) ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th>Webhook URL</th>
				<td>
					<input class="regular-text"
						   name="luxvv_settings[bunny_webhook_url]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'bunny_webhook_url' ) ); ?>"
					/>
				</td>
			</tr>
		</table>

		<h2>Analytics</h2>
		<table class="form-table">
			<tr>
				<th>Min View Seconds</th>
				<td>
					<input type="number"
						   name="luxvv_settings[analytics_min_view_seconds]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'analytics_min_view_seconds' ) ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th>Retention Days</th>
				<td>
					<input type="number"
						   name="luxvv_settings[analytics_retention_days]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'analytics_retention_days' ) ); ?>"
					/>
				</td>
			</tr>
		</table>

		<h2>Payout Tiers (JSON)</h2>
		<p>Define CPM tiers in cents. Example: <code>[{"min_views":0,"cpm_cents":250}]</code></p>
		<textarea class="large-text code" rows="6" name="luxvv_settings[payout_tiers_json]"><?php echo esc_textarea( \LuxVerified\Settings::get( 'payout_tiers_json' ) ); ?></textarea>

	<h2>Payout Bonus Thresholds</h2>
	<table class="form-table">
			<tr>
				<th>CTR Bonus Threshold (decimal)</th>
				<td>
					<input type="text"
						   name="luxvv_settings[payout_ctr_bonus_threshold]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'payout_ctr_bonus_threshold' ) ); ?>"
					/>
					<p class="description">Example: 0.05 = 5% CTR</p>
				</td>
			</tr>
			<tr>
				<th>Retention Bonus Threshold (decimal)</th>
				<td>
					<input type="text"
						   name="luxvv_settings[payout_retention_bonus_threshold]"
						   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'payout_retention_bonus_threshold' ) ); ?>"
					/>
					<p class="description">Example: 0.75 = 75% retention</p>
				</td>
			</tr>
	</table>

	<h2>1099-NEC Threshold</h2>
	<table class="form-table">
		<tr>
			<th>Annual Threshold (USD)</th>
			<td>
				<input type="number"
					   name="luxvv_settings[payout_1099_threshold]"
					   value="<?php echo esc_attr( \LuxVerified\Settings::get( 'payout_1099_threshold' ) ); ?>"
				/>
				<p class="description">Creators at or above this amount are included in the 1099 export.</p>
			</td>
		</tr>
	</table>


	<?php submit_button(); ?>
	</form>
</div>
