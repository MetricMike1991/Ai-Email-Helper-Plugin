<?php
/**
 * IMAP reader. Fetches recent messages from the configured mailbox and caches
 * them in the local messages table. Requires the PHP imap extension.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Imap_Client {

	/**
	 * Is the PHP imap extension available on this host?
	 */
	public static function has_imap_extension() {
		return function_exists( 'imap_open' );
	}

	/**
	 * Build the IMAP mailbox connection string, e.g. {mail.host.com:993/imap/ssl}INBOX.
	 *
	 * @param array  $s      Settings.
	 * @param string $folder Mailbox folder.
	 * @return string
	 */
	private static function mailbox_string( array $s, $folder = 'INBOX' ) {
		$host = preg_replace( '#^https?://#', '', (string) $s['imap_host'] );
		$port = (int) $s['imap_port'];
		$enc  = $s['imap_encryption'];

		$flags = '/imap';
		if ( 'ssl' === $enc ) {
			$flags .= '/ssl';
		} elseif ( 'tls' === $enc ) {
			$flags .= '/tls';
		} else {
			$flags .= '/notls';
		}
		if ( empty( $s['imap_validate_cert'] ) ) {
			// SiteGround mail certs are often issued for the server hostname, not the domain.
			$flags .= '/novalidate-cert';
		}
		return '{' . $host . ':' . $port . $flags . '}' . $folder;
	}

	/**
	 * Open an IMAP stream. Returns resource or WP_Error.
	 *
	 * @param string $folder Folder.
	 * @return resource|WP_Error
	 */
	public static function connect( $folder = 'INBOX' ) {
		if ( ! self::has_imap_extension() ) {
			return new WP_Error( 'aieh_no_imap', __( 'The PHP IMAP extension is not enabled on this server. Ask SiteGround support to enable it.', 'ai-email-helper' ) );
		}
		$s = AIEH_Settings::all();
		if ( ! AIEH_Settings::is_imap_ready() ) {
			return new WP_Error( 'aieh_imap_config', __( 'IMAP settings are incomplete.', 'ai-email-helper' ) );
		}

		$mailbox = self::mailbox_string( $s, $folder );
		$stream  = @imap_open( $mailbox, $s['imap_user'], $s['imap_password'], 0, 1 );
		if ( false === $stream ) {
			$err = imap_last_error();
			imap_errors();
			return new WP_Error( 'aieh_imap_connect', sprintf( /* translators: %s: error message */ __( 'IMAP connection failed: %s', 'ai-email-helper' ), $err ? $err : 'unknown error' ) );
		}
		return $stream;
	}

	/**
	 * Test the connection and return mailbox info or WP_Error.
	 *
	 * @return array|WP_Error
	 */
	public static function test_connection() {
		$stream = self::connect();
		if ( is_wp_error( $stream ) ) {
			return $stream;
		}
		$check = imap_check( $stream );
		$count = $check ? (int) $check->Nmsgs : 0;
		imap_close( $stream );
		return array( 'messages' => $count );
	}

	/**
	 * Fetch recent messages and store new ones. Returns count of new messages or WP_Error.
	 *
	 * @param string $folder Folder.
	 * @return int|WP_Error
	 */
	public static function fetch_recent( $folder = 'INBOX' ) {
		$stream = self::connect( $folder );
		if ( is_wp_error( $stream ) ) {
			return $stream;
		}

		$limit = max( 1, (int) AIEH_Settings::get( 'fetch_limit', 25 ) );
		$total = imap_num_msg( $stream );
		if ( 0 === $total ) {
			imap_close( $stream );
			return 0;
		}

		$start = max( 1, $total - $limit + 1 );
		$new   = 0;

		for ( $i = $total; $i >= $start; $i-- ) {
			$uid = imap_uid( $stream, $i );
			if ( self::message_exists( $uid, $folder ) ) {
				continue;
			}
			$stored = self::store_message( $stream, $i, $uid, $folder );
			if ( $stored ) {
				$new++;
			}
		}

		imap_errors();
		imap_close( $stream );
		return $new;
	}

	/**
	 * Whether a message with the given UID/folder is already cached.
	 */
	private static function message_exists( $uid, $folder ) {
		global $wpdb;
		$table = AIEH_Activator::messages_table();
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE imap_uid = %d AND folder = %s", $uid, $folder ) ); // phpcs:ignore WordPress.DB
		return ! empty( $found );
	}

	/**
	 * Read one message from the stream and insert into the cache table.
	 *
	 * @param resource $stream IMAP stream.
	 * @param int      $msgno  Message sequence number.
	 * @param int      $uid    IMAP UID.
	 * @param string   $folder Folder.
	 * @return bool
	 */
	private static function store_message( $stream, $msgno, $uid, $folder ) {
		global $wpdb;

		$overview = imap_fetch_overview( $stream, $msgno, 0 );
		if ( empty( $overview[0] ) ) {
			return false;
		}
		$o = $overview[0];

		$from_email = '';
		$from_name  = '';
		if ( ! empty( $o->from ) ) {
			$parsed = imap_rfc822_parse_adrlist( $o->from, '' );
			if ( ! empty( $parsed[0] ) ) {
				$from_email = ( isset( $parsed[0]->mailbox ) ? $parsed[0]->mailbox : '' ) . '@' . ( isset( $parsed[0]->host ) ? $parsed[0]->host : '' );
				$from_name  = isset( $parsed[0]->personal ) ? self::decode_mime( $parsed[0]->personal ) : '';
			}
		}

		$subject = isset( $o->subject ) ? self::decode_mime( $o->subject ) : '';
		$body    = self::get_body( $stream, $msgno );

		$received = isset( $o->date ) ? gmdate( 'Y-m-d H:i:s', strtotime( $o->date ) ) : current_time( 'mysql', true );

		$table  = AIEH_Activator::messages_table();
		$result = $wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			array(
				'imap_uid'    => (int) $uid,
				'folder'      => $folder,
				'message_id'  => isset( $o->message_id ) ? substr( (string) $o->message_id, 0, 191 ) : '',
				'from_email'  => substr( sanitize_email( $from_email ), 0, 191 ),
				'from_name'   => substr( sanitize_text_field( $from_name ), 0, 191 ),
				'to_email'    => isset( $o->to ) ? sanitize_text_field( $o->to ) : '',
				'subject'     => sanitize_text_field( $subject ),
				'body_text'   => wp_kses_post( $body ),
				'status'      => 'new',
				'received_at' => $received,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (bool) $result;
	}

	/**
	 * Extract a readable plain-text body from a message.
	 *
	 * @param resource $stream IMAP stream.
	 * @param int      $msgno  Message number.
	 * @return string
	 */
	private static function get_body( $stream, $msgno ) {
		$structure = imap_fetchstructure( $stream, $msgno );
		if ( empty( $structure ) ) {
			return '';
		}

		// Simple (non-multipart) message.
		if ( empty( $structure->parts ) ) {
			$body = imap_body( $stream, $msgno );
			return self::decode_part( $body, isset( $structure->encoding ) ? $structure->encoding : 0 );
		}

		$plain = '';
		$html  = '';
		foreach ( $structure->parts as $index => $part ) {
			$part_no = (string) ( $index + 1 );
			$data    = imap_fetchbody( $stream, $msgno, $part_no );
			$decoded = self::decode_part( $data, isset( $part->encoding ) ? $part->encoding : 0 );
			$subtype = isset( $part->subtype ) ? strtoupper( $part->subtype ) : '';

			if ( 'PLAIN' === $subtype && '' === $plain ) {
				$plain = $decoded;
			} elseif ( 'HTML' === $subtype && '' === $html ) {
				$html = $decoded;
			}
		}

		if ( '' !== $plain ) {
			return $plain;
		}
		if ( '' !== $html ) {
			return wp_strip_all_tags( $html );
		}
		return '';
	}

	/**
	 * Decode a body part based on its IMAP encoding constant.
	 */
	private static function decode_part( $data, $encoding ) {
		switch ( (int) $encoding ) {
			case 3: // BASE64.
				$data = base64_decode( $data );
				break;
			case 4: // QUOTED-PRINTABLE.
				$data = quoted_printable_decode( $data );
				break;
		}
		if ( ! mb_check_encoding( $data, 'UTF-8' ) ) {
			$data = mb_convert_encoding( $data, 'UTF-8', mb_detect_encoding( $data, 'UTF-8, ISO-8859-1, Windows-1252', true ) ?: 'ISO-8859-1' );
		}
		return $data;
	}

	/**
	 * Decode MIME-encoded header text (e.g. subjects with =?UTF-8?...).
	 */
	private static function decode_mime( $text ) {
		$decoded = '';
		$parts   = imap_mime_header_decode( (string) $text );
		foreach ( $parts as $part ) {
			$charset = 'default' === $part->charset ? 'UTF-8' : $part->charset;
			$decoded .= ( 'UTF-8' === strtoupper( $charset ) ) ? $part->text : @mb_convert_encoding( $part->text, 'UTF-8', $charset );
		}
		return $decoded;
	}
}
