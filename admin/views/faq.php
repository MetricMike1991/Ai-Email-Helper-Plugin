<?php
/**
 * FAQ sources admin view. Add external URLs for the assistant to learn from.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table = AIEH_Activator::faqs_table();
$faqs  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY scanned_at DESC" ); // phpcs:ignore WordPress.DB
?>
<div class="wrap aieh-wrap">
	<h1><?php esc_html_e( 'AI Email Helper — FAQ Sources', 'ai-email-helper' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Paste public URLs (FAQ pages, policy pages, service pages). The assistant uses this content when drafting replies.', 'ai-email-helper' ); ?></p>

	<div class="aieh-faq-add">
		<input type="url" id="aieh-faq-url" class="regular-text" placeholder="https://yourdomain.com/faq">
		<button type="button" class="button button-primary" id="aieh-scan"><?php esc_html_e( 'Scan URL', 'ai-email-helper' ); ?></button>
		<span class="aieh-inline-status" id="aieh-scan-status"></span>
	</div>

	<table class="widefat striped aieh-faq-table" style="margin-top:16px">
		<thead><tr>
			<th><?php esc_html_e( 'Title', 'ai-email-helper' ); ?></th>
			<th><?php esc_html_e( 'URL', 'ai-email-helper' ); ?></th>
			<th><?php esc_html_e( 'Words', 'ai-email-helper' ); ?></th>
			<th><?php esc_html_e( 'Scanned', 'ai-email-helper' ); ?></th>
			<th></th>
		</tr></thead>
		<tbody>
			<?php if ( empty( $faqs ) ) : ?>
				<tr class="aieh-faq-empty"><td colspan="5"><?php esc_html_e( 'No FAQ sources yet.', 'ai-email-helper' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $faqs as $f ) : ?>
					<tr data-id="<?php echo esc_attr( $f->id ); ?>">
						<td><?php echo esc_html( $f->title ); ?></td>
						<td><a href="<?php echo esc_url( $f->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $f->source_url ); ?></a></td>
						<td><?php echo (int) $f->word_count; ?></td>
						<td><?php echo esc_html( $f->scanned_at ); ?></td>
						<td><button type="button" class="button-link aieh-delete-faq"><?php esc_html_e( 'Remove', 'ai-email-helper' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
