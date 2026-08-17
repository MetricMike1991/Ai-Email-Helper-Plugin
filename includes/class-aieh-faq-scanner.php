<?php
/**
 * Scans external URLs, extracts readable text, and stores it as FAQ knowledge
 * the assistant can draw on when drafting replies.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Faq_Scanner {

	/**
	 * Fetch a URL, extract main text, and upsert into the FAQ table.
	 *
	 * @param string $url URL to scan.
	 * @return array|WP_Error Row data or error.
	 */
	public static function scan_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'aieh_bad_url', __( 'Please enter a valid, public http(s) URL.', 'ai-email-helper' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'user-agent' => 'AI-Email-Helper/' . AIEH_VERSION . '; ' . home_url(),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error( 'aieh_fetch_http', sprintf( /* translators: %d: HTTP status */ __( 'Fetch failed with HTTP status %d.', 'ai-email-helper' ), $code ) );
		}

		$html               = wp_remote_retrieve_body( $response );
		list( $title, $text ) = self::extract_text( $html );

		if ( '' === $text ) {
			return new WP_Error( 'aieh_no_text', __( 'No readable text could be extracted from that page.', 'ai-email-helper' ) );
		}

		global $wpdb;
		$table = AIEH_Activator::faqs_table();
		$now   = current_time( 'mysql', true );
		$words = str_word_count( wp_strip_all_tags( $text ) );

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE source_url = %s", $url ) ); // phpcs:ignore WordPress.DB

		$data = array(
			'source_url' => $url,
			'title'      => $title,
			'content'    => $text,
			'word_count' => $words,
			'status'     => 'active',
			'scanned_at' => $now,
		);

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing ), array( '%s', '%s', '%s', '%d', '%s', '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB
			$data['id'] = (int) $existing;
		} else {
			$wpdb->insert( $table, $data, array( '%s', '%s', '%s', '%d', '%s', '%s' ) ); // phpcs:ignore WordPress.DB
			$data['id'] = (int) $wpdb->insert_id;
		}

		return $data;
	}

	/**
	 * Very lightweight HTML -> title + text extraction (no external deps).
	 *
	 * @param string $html Raw HTML.
	 * @return array [title, text]
	 */
	private static function extract_text( $html ) {
		$title = '';
		if ( preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $m ) ) {
			$title = sanitize_text_field( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
		}

		// Strip non-content blocks.
		$html = preg_replace( '#<(script|style|noscript|nav|footer|header|form|svg)\b[^>]*>.*?</\1>#is', ' ', $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/(\s*\n\s*){2,}/', "\n\n", $text );
		$text = trim( $text );

		// Cap stored content to keep prompts manageable.
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > 20000 ) {
			$text = mb_substr( $text, 0, 20000 );
		}

		return array( $title, $text );
	}

	/**
	 * Fetch one FAQ source by id.
	 *
	 * @param int $id Entry id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = AIEH_Activator::faqs_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Search/list FAQ sources, newest scan first.
	 *
	 * @param string $search Optional search term (matches title/content/url).
	 * @return array
	 */
	public static function search( $search = '' ) {
		global $wpdb;
		$table  = AIEH_Activator::faqs_table();
		$search = trim( (string) $search );

		if ( '' === $search ) {
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY scanned_at DESC" ); // phpcs:ignore WordPress.DB
		}

		$like = '%' . $wpdb->esc_like( $search ) . '%';
		return $wpdb->get_results( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE title LIKE %s OR content LIKE %s OR source_url LIKE %s ORDER BY scanned_at DESC",
				$like,
				$like,
				$like
			)
		);
	}

	/**
	 * Update a FAQ entry's title/content directly (e.g. when facts changed).
	 *
	 * @param int    $id      Entry id.
	 * @param string $title   Updated title.
	 * @param string $content Updated content.
	 * @return bool
	 */
	public static function update( $id, $title, $content ) {
		global $wpdb;
		$table   = AIEH_Activator::faqs_table();
		$content = trim( (string) $content );
		$words   = str_word_count( wp_strip_all_tags( $content ) );

		return false !== $wpdb->update( // phpcs:ignore WordPress.DB
			$table,
			array(
				'title'      => sanitize_text_field( $title ),
				'content'    => $content,
				'word_count' => $words,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a FAQ source.
	 *
	 * @param int $id Entry id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = AIEH_Activator::faqs_table();
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Return a concatenated knowledge snippet from active FAQs, capped in length.
	 *
	 * @param int $max_chars Maximum characters.
	 * @return string
	 */
	public static function knowledge_context( $max_chars = 6000 ) {
		global $wpdb;
		$table = AIEH_Activator::faqs_table();
		$rows  = $wpdb->get_results( "SELECT title, source_url, content FROM {$table} WHERE status = 'active' ORDER BY scanned_at DESC" ); // phpcs:ignore WordPress.DB

		$out = '';
		foreach ( $rows as $row ) {
			$chunk = '### ' . $row->title . ' (' . $row->source_url . ")\n" . $row->content . "\n\n";
			if ( strlen( $out ) + strlen( $chunk ) > $max_chars ) {
				$chunk = substr( $chunk, 0, max( 0, $max_chars - strlen( $out ) ) );
				$out  .= $chunk;
				break;
			}
			$out .= $chunk;
		}
		return trim( $out );
	}
}
