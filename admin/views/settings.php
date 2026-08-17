<?php
/**
 * Settings admin view.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s = AIEH_Settings::all();

/**
 * Print a masked placeholder for secret fields so we never echo the secret.
 *
 * @param string $val Secret value.
 * @return string
 */
$has_secret = static function ( $val ) {
	return '' !== $val ? esc_attr__( '•••••••• (unchanged — leave blank to keep)', 'ai-email-helper' ) : '';
};
?>
<div class="wrap aieh-wrap">
	<h1><?php esc_html_e( 'AI Email Helper — Settings', 'ai-email-helper' ); ?></h1>
	<?php settings_errors( 'aieh' ); ?>

	<?php if ( ! AIEH_Imap_Client::has_imap_extension() ) : ?>
		<div class="notice notice-warning"><p>
			<?php esc_html_e( 'The PHP IMAP extension is not enabled on this server, so email reading will not work. Ask SiteGround support to enable php-imap.', 'ai-email-helper' ); ?>
		</p></div>
	<?php endif; ?>

	<form method="post" action="">
		<?php wp_nonce_field( 'aieh_save_settings' ); ?>

		<h2><?php esc_html_e( 'Incoming Mail (IMAP)', 'ai-email-helper' ); ?></h2>
		<p class="description"><?php esc_html_e( 'For SiteGround, use your mail server host (e.g. mail.yourdomain.com), port 993, SSL, and your full email address with an app password.', 'ai-email-helper' ); ?></p>
		<table class="form-table" role="presentation">
			<tr><th><label for="imap_host"><?php esc_html_e( 'IMAP Host', 'ai-email-helper' ); ?></label></th>
				<td><input name="imap_host" id="imap_host" type="text" class="regular-text" value="<?php echo esc_attr( $s['imap_host'] ); ?>" placeholder="mail.yourdomain.com"></td></tr>
			<tr><th><label for="imap_port"><?php esc_html_e( 'IMAP Port', 'ai-email-helper' ); ?></label></th>
				<td><input name="imap_port" id="imap_port" type="number" value="<?php echo esc_attr( $s['imap_port'] ); ?>" class="small-text"></td></tr>
			<tr><th><label for="imap_encryption"><?php esc_html_e( 'Encryption', 'ai-email-helper' ); ?></label></th>
				<td><select name="imap_encryption" id="imap_encryption">
					<?php foreach ( array( 'ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None' ) as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['imap_encryption'], $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th><label for="imap_user"><?php esc_html_e( 'Email / Username', 'ai-email-helper' ); ?></label></th>
				<td><input name="imap_user" id="imap_user" type="text" class="regular-text" value="<?php echo esc_attr( $s['imap_user'] ); ?>" placeholder="you@yourdomain.com"></td></tr>
			<tr><th><label for="imap_password"><?php esc_html_e( 'Password', 'ai-email-helper' ); ?></label></th>
				<td><input name="imap_password" id="imap_password" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_secret( $s['imap_password'] ) ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'SSL Certificate', 'ai-email-helper' ); ?></th>
				<td><label><input name="imap_validate_cert" type="checkbox" value="1" <?php checked( (int) $s['imap_validate_cert'], 1 ); ?>> <?php esc_html_e( 'Validate SSL certificate', 'ai-email-helper' ); ?></label>
				<p class="description"><?php esc_html_e( 'Uncheck this if you get a “certificate failure / hostname mismatch” error. On SiteGround the mail certificate is issued for the server hostname (e.g. gnldmXXXX.siteground.biz), not your domain, so validation must be turned off.', 'ai-email-helper' ); ?></p></td></tr>
		</table>
		<p><button type="button" class="button" id="aieh-test-imap"><?php esc_html_e( 'Test IMAP Connection', 'ai-email-helper' ); ?></button> <span class="aieh-inline-status" id="aieh-imap-status"></span></p>

		<h2><?php esc_html_e( 'Outgoing Mail (SMTP)', 'ai-email-helper' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th><label for="smtp_host"><?php esc_html_e( 'SMTP Host', 'ai-email-helper' ); ?></label></th>
				<td><input name="smtp_host" id="smtp_host" type="text" class="regular-text" value="<?php echo esc_attr( $s['smtp_host'] ); ?>" placeholder="mail.yourdomain.com"></td></tr>
			<tr><th><label for="smtp_port"><?php esc_html_e( 'SMTP Port', 'ai-email-helper' ); ?></label></th>
				<td><input name="smtp_port" id="smtp_port" type="number" value="<?php echo esc_attr( $s['smtp_port'] ); ?>" class="small-text"></td></tr>
			<tr><th><label for="smtp_encryption"><?php esc_html_e( 'Encryption', 'ai-email-helper' ); ?></label></th>
				<td><select name="smtp_encryption" id="smtp_encryption">
					<?php foreach ( array( 'ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'None' ) as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['smtp_encryption'], $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></td></tr>
			<tr><th><label for="smtp_user"><?php esc_html_e( 'Email / Username', 'ai-email-helper' ); ?></label></th>
				<td><input name="smtp_user" id="smtp_user" type="text" class="regular-text" value="<?php echo esc_attr( $s['smtp_user'] ); ?>"></td></tr>
			<tr><th><label for="smtp_password"><?php esc_html_e( 'Password', 'ai-email-helper' ); ?></label></th>
				<td><input name="smtp_password" id="smtp_password" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_secret( $s['smtp_password'] ) ); ?>"></td></tr>
			<tr><th><label for="from_name"><?php esc_html_e( 'From Name', 'ai-email-helper' ); ?></label></th>
				<td><input name="from_name" id="from_name" type="text" class="regular-text" value="<?php echo esc_attr( $s['from_name'] ); ?>"></td></tr>
			<tr><th><label for="from_email"><?php esc_html_e( 'From Email', 'ai-email-helper' ); ?></label></th>
				<td><input name="from_email" id="from_email" type="email" class="regular-text" value="<?php echo esc_attr( $s['from_email'] ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'SSL Certificate', 'ai-email-helper' ); ?></th>
				<td><label><input name="smtp_validate_cert" type="checkbox" value="1" <?php checked( (int) $s['smtp_validate_cert'], 1 ); ?>> <?php esc_html_e( 'Validate SSL certificate', 'ai-email-helper' ); ?></label>
				<p class="description"><?php esc_html_e( 'Uncheck this if sending fails with a certificate/hostname mismatch error (same SiteGround reason as IMAP above).', 'ai-email-helper' ); ?></p></td></tr>
		</table>

		<h2><?php esc_html_e( 'OpenAI', 'ai-email-helper' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th><label for="openai_api_key"><?php esc_html_e( 'API Key', 'ai-email-helper' ); ?></label></th>
				<td><input name="openai_api_key" id="openai_api_key" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $has_secret( $s['openai_api_key'] ) ); ?>">
				<p class="description"><?php esc_html_e( 'Stored encrypted. Get a key at platform.openai.com.', 'ai-email-helper' ); ?></p></td></tr>
			<tr><th><label for="openai_model"><?php esc_html_e( 'Model', 'ai-email-helper' ); ?></label></th>
				<td><input name="openai_model" id="openai_model" type="text" class="regular-text" value="<?php echo esc_attr( $s['openai_model'] ); ?>" placeholder="gpt-4o-mini"></td></tr>
		</table>

		<h2><?php esc_html_e( 'Automation', 'ai-email-helper' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th><label for="fetch_limit"><?php esc_html_e( 'Emails to fetch per run', 'ai-email-helper' ); ?></label></th>
				<td><input name="fetch_limit" id="fetch_limit" type="number" class="small-text" value="<?php echo esc_attr( $s['fetch_limit'] ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Auto-summarize new email', 'ai-email-helper' ); ?></th>
				<td><label><input name="auto_summarize" type="checkbox" value="1" <?php checked( (int) $s['auto_summarize'], 1 ); ?>> <?php esc_html_e( 'Summarize automatically when new mail arrives', 'ai-email-helper' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Auto-draft replies', 'ai-email-helper' ); ?></th>
				<td><label><input name="auto_draft" type="checkbox" value="1" <?php checked( (int) $s['auto_draft'], 1 ); ?>> <?php esc_html_e( 'Also generate a suggested draft (you still approve before sending)', 'ai-email-helper' ); ?></label></td></tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'ai-email-helper' ), 'primary', 'aieh_settings_submit' ); ?>
	</form>
</div>
