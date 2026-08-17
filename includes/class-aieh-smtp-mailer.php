<?php
/**
 * Sends approved replies through the configured SMTP server using PHPMailer.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIEH_Smtp_Mailer {

	/**
	 * Send a reply. Returns true or WP_Error.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Subject.
	 * @param string $body    Plain-text body.
	 * @return true|WP_Error
	 */
	public static function send( $to, $subject, $body ) {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'aieh_bad_recipient', __( 'Invalid recipient email address.', 'ai-email-helper' ) );
		}

		$s = AIEH_Settings::all();
		if ( '' === $s['smtp_host'] || '' === $s['smtp_user'] || '' === $s['smtp_password'] ) {
			return new WP_Error( 'aieh_smtp_config', __( 'SMTP settings are incomplete.', 'ai-email-helper' ) );
		}

		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

		$mail = new \PHPMailer\PHPMailer\PHPMailer( true );
		try {
			$mail->isSMTP();
			$mail->Host       = preg_replace( '#^https?://#', '', (string) $s['smtp_host'] );
			$mail->Port       = (int) $s['smtp_port'];
			$mail->SMTPAuth   = true;
			$mail->Username   = $s['smtp_user'];
			$mail->Password   = $s['smtp_password'];

			if ( 'ssl' === $s['smtp_encryption'] ) {
				$mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
			} elseif ( 'tls' === $s['smtp_encryption'] ) {
				$mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			} else {
				$mail->SMTPSecure = '';
				$mail->SMTPAutoTLS = false;
			}

			if ( empty( $s['smtp_validate_cert'] ) ) {
				$mail->SMTPOptions = array(
					'ssl' => array(
						'verify_peer'       => false,
						'verify_peer_name'  => false,
						'allow_self_signed' => true,
					),
				);
			}

			$from_email = '' !== $s['from_email'] ? $s['from_email'] : $s['smtp_user'];
			$from_name  = '' !== $s['from_name'] ? $s['from_name'] : $from_email;

			$mail->setFrom( $from_email, $from_name );
			$mail->addAddress( $to );
			$mail->Subject = $subject;
			$mail->Body    = $body;
			$mail->isHTML( false );
			$mail->CharSet = 'UTF-8';

			$mail->send();
			return true;
		} catch ( \Exception $e ) {
			return new WP_Error( 'aieh_smtp_send', sprintf( /* translators: %s: error */ __( 'Failed to send: %s', 'ai-email-helper' ), $mail->ErrorInfo ) );
		}
	}
}
