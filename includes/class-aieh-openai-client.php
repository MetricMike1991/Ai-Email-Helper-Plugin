<?php
/**
 * Thin wrapper around the OpenAI Chat Completions API using wp_remote_post.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_OpenAI_Client {

	const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Send a chat completion request.
	 *
	 * @param array $messages Array of role/content message arrays.
	 * @param array $args     Optional overrides (model, temperature, max_tokens).
	 * @return string|WP_Error Assistant text or error.
	 */
	public static function chat( array $messages, array $args = array() ) {
		$key = AIEH_Settings::get( 'openai_api_key' );
		if ( '' === $key ) {
			return new WP_Error( 'aieh_no_key', __( 'OpenAI API key is not set.', 'ai-email-helper' ) );
		}

		$model = ! empty( $args['model'] ) ? $args['model'] : AIEH_Settings::get( 'openai_model', 'gpt-4o-mini' );

		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.4,
		);
		if ( isset( $args['max_tokens'] ) ) {
			$body['max_tokens'] = (int) $args['max_tokens'];
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown OpenAI API error.', 'ai-email-helper' );
			return new WP_Error( 'aieh_openai_http', sprintf( '[%d] %s', $code, $msg ) );
		}

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'aieh_openai_empty', __( 'OpenAI returned an empty response.', 'ai-email-helper' ) );
		}

		return trim( $data['choices'][0]['message']['content'] );
	}
}
