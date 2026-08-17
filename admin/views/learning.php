<?php
/**
 * Learning admin view. Search, edit, and delete tone examples learned from
 * approved replies. Use when underlying facts change (e.g. hours, pricing).
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$entries = AIEH_Learning_Store::search( $search, 100 );
$total   = AIEH_Learning_Store::count( $search );
?>
<div class="wrap aieh-wrap">
	<h1><?php esc_html_e( 'AI Email Helper — Learning', 'ai-email-helper' ); ?></h1>
	<p class="description"><?php esc_html_e( 'These are examples built from replies you approved & sent. The assistant uses recent ones to match your tone. Edit or remove an entry if the information in it is out of date.', 'ai-email-helper' ); ?></p>

	<form method="get" class="aieh-search-form">
		<input type="hidden" name="page" value="ai-email-helper-learning">
		<input type="search" name="s" class="regular-text" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search summaries, replies, notes…', 'ai-email-helper' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'ai-email-helper' ); ?></button>
		<?php if ( '' !== $search ) : ?>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ai-email-helper-learning' ) ); ?>"><?php esc_html_e( 'Clear', 'ai-email-helper' ); ?></a>
		<?php endif; ?>
	</form>

	<p class="description"><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d entry', '%d entries', $total, 'ai-email-helper' ), $total ) ); ?></p>

	<?php if ( empty( $entries ) ) : ?>
		<p><?php esc_html_e( 'No learning entries yet — they are created automatically when you Approve & Send a reply.', 'ai-email-helper' ); ?></p>
	<?php else : ?>
		<div class="aieh-list aieh-learning-list">
			<?php foreach ( $entries as $entry ) : ?>
				<div class="aieh-card aieh-learning-card" data-id="<?php echo esc_attr( $entry->id ); ?>">
					<div class="aieh-card-head">
						<span><?php esc_html_e( 'Learned', 'ai-email-helper' ); ?></span>
						<span class="aieh-date"><?php echo esc_html( $entry->created_at ); ?></span>
					</div>

					<label class="aieh-field-label"><?php esc_html_e( 'Context (what was being replied to)', 'ai-email-helper' ); ?></label>
					<textarea class="aieh-learning-summary" rows="2"><?php echo esc_textarea( $entry->prompt_summary ); ?></textarea>

					<label class="aieh-field-label"><?php esc_html_e( 'Reply text', 'ai-email-helper' ); ?></label>
					<textarea class="aieh-learning-reply" rows="5"><?php echo esc_textarea( $entry->user_reply ); ?></textarea>

					<label class="aieh-field-label"><?php esc_html_e( 'Tone notes (optional)', 'ai-email-helper' ); ?></label>
					<textarea class="aieh-learning-notes" rows="2"><?php echo esc_textarea( $entry->tone_notes ); ?></textarea>

					<div class="aieh-revise-row">
						<input type="text" class="aieh-revise-instruction regular-text" placeholder="<?php esc_attr_e( 'e.g. our opening hours changed to 6am-9pm — update this reply', 'ai-email-helper' ); ?>">
						<button type="button" class="button aieh-ai-revise" data-target=".aieh-learning-reply"><?php esc_html_e( 'Ask AI to revise', 'ai-email-helper' ); ?></button>
					</div>

					<div class="aieh-actions">
						<button type="button" class="button button-primary aieh-learning-save"><?php esc_html_e( 'Save', 'ai-email-helper' ); ?></button>
						<button type="button" class="button-link-delete aieh-learning-delete"><?php esc_html_e( 'Delete', 'ai-email-helper' ); ?></button>
					</div>

					<div class="aieh-card-status"></div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
