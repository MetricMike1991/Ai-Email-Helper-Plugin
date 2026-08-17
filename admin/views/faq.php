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
$table  = AIEH_Activator::faqs_table();
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$faqs   = AIEH_Faq_Scanner::search( $search );
?>
<div class="wrap aieh-wrap">
	<h1><?php esc_html_e( 'AI Email Helper — FAQ Sources', 'ai-email-helper' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Paste public URLs (FAQ pages, policy pages, service pages). The assistant uses this content when drafting replies.', 'ai-email-helper' ); ?></p>

	<div class="aieh-faq-add">
		<input type="url" id="aieh-faq-url" class="regular-text" placeholder="https://yourdomain.com/faq">
		<button type="button" class="button button-primary" id="aieh-scan"><?php esc_html_e( 'Scan URL', 'ai-email-helper' ); ?></button>
		<span class="aieh-inline-status" id="aieh-scan-status"></span>
	</div>

	<form method="get" class="aieh-search-form">
		<input type="hidden" name="page" value="ai-email-helper-faq">
		<input type="search" name="s" class="regular-text" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search title, URL, content…', 'ai-email-helper' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Search', 'ai-email-helper' ); ?></button>
		<?php if ( '' !== $search ) : ?>
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=ai-email-helper-faq' ) ); ?>"><?php esc_html_e( 'Clear', 'ai-email-helper' ); ?></a>
		<?php endif; ?>
	</form>

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
						<td>
							<button type="button" class="button-link aieh-edit-faq"><?php esc_html_e( 'Edit', 'ai-email-helper' ); ?></button>
							&middot;
							<button type="button" class="button-link aieh-delete-faq"><?php esc_html_e( 'Remove', 'ai-email-helper' ); ?></button>
						</td>
					</tr>
					<tr class="aieh-faq-edit-row" data-id="<?php echo esc_attr( $f->id ); ?>" style="display:none">
						<td colspan="5">
							<label class="aieh-field-label"><?php esc_html_e( 'Title', 'ai-email-helper' ); ?></label>
							<input type="text" class="regular-text aieh-faq-edit-title" value="<?php echo esc_attr( $f->title ); ?>">

							<label class="aieh-field-label"><?php esc_html_e( 'Content', 'ai-email-helper' ); ?></label>
							<textarea class="aieh-faq-edit-content" rows="8"><?php echo esc_textarea( $f->content ); ?></textarea>

							<div class="aieh-revise-row">
								<input type="text" class="aieh-revise-instruction regular-text" placeholder="<?php esc_attr_e( 'e.g. our opening hours changed to 6am-9pm — update this', 'ai-email-helper' ); ?>">
								<button type="button" class="button aieh-ai-revise" data-target=".aieh-faq-edit-content"><?php esc_html_e( 'Ask AI to revise', 'ai-email-helper' ); ?></button>
							</div>

							<div class="aieh-actions">
								<button type="button" class="button button-primary aieh-faq-save"><?php esc_html_e( 'Save', 'ai-email-helper' ); ?></button>
							</div>
							<div class="aieh-card-status"></div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

