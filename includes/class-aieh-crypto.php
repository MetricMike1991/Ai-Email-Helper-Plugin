<?php
/**
 * Reversible encryption for stored secrets (IMAP/SMTP passwords, OpenAI key).
 *
 * Uses AES-256-CBC with a key derived from the site's WordPress salts, so
 * secrets in the options table are not stored in plain text.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Crypto {

	const METHOD = 'aes-256-cbc';

	/**
	 * Build the 32-byte encryption key from WP salts.
	 */
	private static function key() {
		$salt = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' );
		if ( '' === $salt ) {
			// Fallback so encryption still works on minimal configs.
			$salt = wp_salt( 'auth' );
		}
		return hash( 'sha256', 'aieh|' . $salt, true );
	}

	/**
	 * Encrypt a plain string. Returns base64( iv . ciphertext ) or '' on failure.
	 *
	 * @param string $plain Plain text.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		if ( '' === (string) $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return '';
		}
		$iv_len = openssl_cipher_iv_length( self::METHOD );
		$iv     = openssl_random_pseudo_bytes( $iv_len );
		$cipher = openssl_encrypt( (string) $plain, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return '';
		}
		return base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a value produced by encrypt(). Returns '' on failure.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		if ( '' === (string) $stored || ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( (string) $stored, true );
		if ( false === $raw ) {
			return '';
		}
		$iv_len = openssl_cipher_iv_length( self::METHOD );
		if ( strlen( $raw ) <= $iv_len ) {
			return '';
		}
		$iv     = substr( $raw, 0, $iv_len );
		$cipher = substr( $raw, $iv_len );
		$plain  = openssl_decrypt( $cipher, self::METHOD, self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}
}
