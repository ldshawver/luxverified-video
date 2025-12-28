<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings = get_option( 'luxvv_settings', [] );

$form_id = isset( $settings['forminator_form_id'] )
	? (int) $settings['forminator_form_id']
	: '';
?>

<div class="wrap">
	<h1>LUX Verified – Settings</h1>

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
      value="<?php echo esc_attr(\LuxVerified\Settings::get('agreement_form_id')); ?>"
    />
  </td>
</tr>

<tr>
  <th>W-9 Tax Form (Step 3)</th>
  <td>
    <input type="number"
      name="luxvv_settings[w9_form_id]"
      value="<?php echo esc_attr(\LuxVerified\Settings::get('w9_form_id')); ?>"
    />
  </td>
</tr>
</table>


		<?php submit_button(); ?>
	</form>
</div>
