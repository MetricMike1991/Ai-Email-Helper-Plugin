<?php
/**
 * Inbox admin view. Lists cached emails with AI actions.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table    = AIEH_Activator::messages_table();
$messages = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY received_at DESC LIMIT 100" ); // phpcs:ignore WordPress.DB
$drafts_t = AIEH_Activator::drafts_table();
?>
<div class="wrap aieh-wrap">
	<h1>
		<?php esc_html_e( 'AI Email Helper — Inbox', 'ai-email-helper' ); ?>
		<button type="button" class="button button-primary" id="aieh-fetch"><?php esc_html_e( 'Fetch New Email', 'ai-email-helper' ); ?></button>
		<span class="aieh-inline-status" id="aieh-fetch-status"></span>
	</h1>

	<?php if ( ! AIEH_Settings::is_imap_ready() ) : ?>
		<div class="notice notice-info"><p>
			<?php
			printf(
				/* translators: %s: settings link */
				esc_html__( 'Connect your mailbox first in %s.', 'ai-email-helper' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=ai-email-helper-settings' ) ) . '">' . esc_html__( 'Settings', 'ai-email-helper' ) . '</a>'
			);
			?>
		</p></div>
	<?php endif; ?>

	<?php if ( empty( $messages ) ) : ?>
		<p><?php esc_html_e( 'No messages cached yet. Click “Fetch New Email”.', 'ai-email-helper' ); ?></p>
	<?php else : ?>
		<div class="aieh-list">
			<?php foreach ( $messages as $m ) : ?>
				<?php $draft = $wpdb->get_var( $wpdb->prepare( "SELECT draft_text FROM {$drafts_t} WHERE message_id = %d ORDER BY created_at DESC LIMIT 1", $m->id ) ); // phpcs:ignore WordPress.DB ?>
				<div class="aieh-card" data-id="<?php echo esc_attr( $m->id ); ?>" data-status="<?php echo esc_attr( $m->status ); ?>">
					<div class="aieh-card-head">
						<span class="aieh-from"><strong><?php echo esc_html( $m->from_name ? $m->from_name : $m->from_email ); ?></strong> &lt;<?php echo esc_html( $m->from_email ); ?>&gt;</span>
						<span class="aieh-date"><?php echo esc_html( $m->received_at ); ?></span>
						<span class="aieh-badge aieh-status-<?php echo esc_attr( $m->status ); ?>"><?php echo esc_html( $m->status ); ?></span>
					</div>
					<div class="aieh-subject"><?php echo esc_html( $m->subject ); ?></div>

					<div class="aieh-summary <?php echo $m->summary ? '' : 'is-empty'; ?>">
						<?php echo $m->summary ? esc_html( $m->summary ) : ''; ?>
					</div>

					<details class="aieh-body">
						<summary><?php esc_html_e( 'View full email', 'ai-email-helper' ); ?></summary>
						<pre><?php echo esc_html( $m->body_text ); ?></pre>
					</details>

					<div class="aieh-actions">
						<button type="button" class="button aieh-summarize"><?php esc_html_e( 'Summarize', 'ai-email-helper' ); ?></button>
						<button type="button" class="button aieh-draft"><?php esc_html_e( 'Suggest Reply', 'ai-email-helper' ); ?></button>
					</div>

					<div class="aieh-draft-area" <?php echo $draft ? '' : 'style="display:none"'; ?>>
						<textarea class="aieh-draft-text" rows="6"><?php echo esc_textarea( (string) $draft ); ?></textarea>
						<div class="aieh-actions">
							<button type="button" class="button button-primary aieh-send"><?php esc_html_e( 'Approve & Send', 'ai-email-helper' ); ?></button>
						</div>
					</div>

					<div class="aieh-card-status"></div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
