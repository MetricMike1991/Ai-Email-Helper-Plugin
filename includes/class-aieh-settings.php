<?php
/**
 * Central settings store. Secrets (passwords, API key) are encrypted at rest.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Settings {

	const OPTION = 'aieh_settings';

	/**
	 * Keys whose values are stored encrypted.
	 */
	private static function secret_keys() {
		return array( 'imap_password', 'smtp_password', 'openai_api_key' );
	}

	/**
	 * Default settings.
	 */
	public static function defaults() {
		return array(
			'imap_host'      => '',
			'imap_port'      => 993,
			'imap_encryption'=> 'ssl', // ssl | tls | none
			'imap_user'      => '',
			'imap_password'  => '',
			'imap_validate_cert' => 1,
			'smtp_host'      => '',
			'smtp_port'      => 465,
			'smtp_encryption'=> 'ssl',
			'smtp_user'      => '',
			'smtp_password'  => '',
			'smtp_validate_cert' => 1,
			'from_email'     => '',
			'from_name'      => '',
			'openai_api_key' => '',
			'openai_model'   => 'gpt-4o-mini',
			'fetch_limit'    => 25,
			'auto_summarize' => 1,
			'auto_draft'     => 0,
		);
	}

	/**
	 * Get all settings, with secrets decrypted for internal use.
	 *
	 * @return array
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$out = wp_parse_args( $stored, self::defaults() );
		foreach ( self::secret_keys() as $key ) {
			$out[ $key ] = isset( $stored[ $key ] ) ? AIEH_Crypto::decrypt( $stored[ $key ] ) : '';
		}
		return $out;
	}

	/**
	 * Get a single setting value (decrypted if secret).
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist settings. Secrets are encrypted; blank secret leaves existing value intact.
	 *
	 * @param array $input Raw (already sanitized) values.
	 */
	public static function save( array $input ) {
		$existing = get_option( self::OPTION, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$defaults = self::defaults();
		$clean    = array();

		foreach ( $defaults as $key => $default ) {
			if ( in_array( $key, self::secret_keys(), true ) ) {
				$new = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
				if ( '' === $new ) {
					// Keep previously stored (encrypted) secret when field left blank.
					$clean[ $key ] = isset( $existing[ $key ] ) ? $existing[ $key ] : '';
				} else {
					$clean[ $key ] = AIEH_Crypto::encrypt( $new );
				}
				continue;
			}

			$value = isset( $input[ $key ] ) ? $input[ $key ] : $default;
			if ( is_int( $default ) ) {
				$value = (int) $value;
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			$clean[ $key ] = $value;
		}

		update_option( self::OPTION, $clean );
	}

	/**
	 * Whether IMAP is configured enough to attempt a connection.
	 */
	public static function is_imap_ready() {
		$s = self::all();
		return '' !== $s['imap_host'] && '' !== $s['imap_user'] && '' !== $s['imap_password'];
	}

	/**
	 * Whether the OpenAI key is present.
	 */
	public static function is_openai_ready() {
		return '' !== self::get( 'openai_api_key' );
	}
}
