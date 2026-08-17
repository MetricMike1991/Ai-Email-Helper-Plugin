<?php
/**
 * Inbox admin view. Lists cached emails with AI actions, read/unread + move
 * controls that mirror the real IMAP server, category tags, and filters.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table    = AIEH_Activator::messages_table();
$drafts_t = AIEH_Activator::drafts_table();

// Preset category tags (internal, local-only).
$categories = array( 'Lead', 'Customer', 'Invoice', 'Complaint', 'Supplier', 'Personal', 'Spam', 'Other' );

// Filters. phpcs:disable WordPress.Security.NonceVerification.Recommended
$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';
$cat_filter    = isset( $_GET['cat'] ) ? sanitize_text_field( wp_unslash( $_GET['cat'] ) ) : '';
// phpcs:enable

$where  = array( "folder = 'INBOX'" );
$params = array();
if ( in_array( $status_filter, array( 'unread', 'read', 'replied' ), true ) ) {
	$where[]  = 'status = %s';
	$params[] = $status_filter;
}
if ( '' !== $cat_filter ) {
	$where[]  = 'category = %s';
	$params[] = $cat_filter;
}
$where_sql = implode( ' AND ', $where );
$sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY received_at DESC LIMIT 100";
$messages  = $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB

// Counts for the filter tabs.
$counts = array(
	'all'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE folder = 'INBOX'" ), // phpcs:ignore WordPress.DB
	'unread'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE folder = 'INBOX' AND status = 'unread'" ), // phpcs:ignore WordPress.DB
	'read'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE folder = 'INBOX' AND status = 'read'" ), // phpcs:ignore WordPress.DB
	'replied' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE folder = 'INBOX' AND status = 'replied'" ), // phpcs:ignore WordPress.DB
);

// Server folders for the Move-to dropdown (cached; skipped if IMAP not ready).
$folders = array();
if ( AIEH_Settings::is_imap_ready() ) {
	$maybe = AIEH_Imap_Client::list_folders();
	if ( is_array( $maybe ) ) {
		$folders = $maybe;
	}
}

$base_url = admin_url( 'admin.php?page=ai-email-helper' );
$tabs     = array(
	'all'     => __( 'All', 'ai-email-helper' ),
	'unread'  => __( 'Unread', 'ai-email-helper' ),
	'read'    => __( 'Read', 'ai-email-helper' ),
	'replied' => __( 'Replied', 'ai-email-helper' ),
);
?>
<div class="wrap aieh-wrap">
	<h1>
		<?php esc_html_e( 'AI Email Helper — Inbox', 'ai-email-helper' ); ?>
		<button type="button" class="button button-primary" id="aieh-fetch"><?php esc_html_e( 'Fetch New Email', 'ai-email-helper' ); ?></button>
		<button type="button" class="button" id="aieh-sync-unread"><?php esc_html_e( 'Sync Unread from Server', 'ai-email-helper' ); ?></button>
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

	<ul class="subsubsub aieh-filters">
		<?php $i = 0; foreach ( $tabs as $key => $label ) : $i++; ?>
			<li>
				<a href="<?php echo esc_url( add_query_arg( array( 'status' => $key, 'cat' => $cat_filter ), $base_url ) ); ?>" class="<?php echo $status_filter === $key ? 'current' : ''; ?>">
					<?php echo esc_html( $label ); ?> <span class="count">(<?php echo (int) $counts[ $key ]; ?>)</span>
				</a><?php echo $i < count( $tabs ) ? ' |' : ''; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<form method="get" class="aieh-cat-filter">
		<input type="hidden" name="page" value="ai-email-helper">
		<input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
		<label for="aieh-cat-filter-select"><?php esc_html_e( 'Category:', 'ai-email-helper' ); ?></label>
		<select name="cat" id="aieh-cat-filter-select" onchange="this.form.submit()">
			<option value=""><?php esc_html_e( 'All categories', 'ai-email-helper' ); ?></option>
			<?php foreach ( $categories as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $cat_filter, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
			<?php endforeach; ?>
		</select>
	</form>

	<?php if ( empty( $messages ) ) : ?>
		<p><?php esc_html_e( 'No messages match this view. Try “Fetch New Email” or a different filter.', 'ai-email-helper' ); ?></p>
	<?php else : ?>
		<div class="aieh-list">
			<?php foreach ( $messages as $m ) : ?>
				<div class="aieh-card" data-id="<?php echo esc_attr( $m->id ); ?>" data-status="<?php echo esc_attr( $m->status ); ?>">
					<div class="aieh-card-head aieh-toggle">
						<span class="aieh-caret" aria-hidden="true"></span>
						<span class="aieh-from"><strong><?php echo esc_html( $m->from_name ? $m->from_name : $m->from_email ); ?></strong> &lt;<?php echo esc_html( $m->from_email ); ?>&gt;</span>
						<?php if ( '' !== $m->category ) : ?><span class="aieh-cat-tag"><?php echo esc_html( $m->category ); ?></span><?php endif; ?>
						<span class="aieh-date"><?php echo esc_html( $m->received_at ); ?></span>
						<span class="aieh-badge aieh-status-<?php echo esc_attr( $m->status ); ?>"><?php echo esc_html( $m->status ); ?></span>
					</div>

					<div class="aieh-subject aieh-toggle"><?php echo esc_html( $m->subject ); ?></div>

					<div class="aieh-card-body" hidden>
						<div class="aieh-toolbar">
							<button type="button" class="button-link aieh-mark-read" <?php echo 'unread' === $m->status ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Mark as Read', 'ai-email-helper' ); ?></button>
							<button type="button" class="button-link aieh-mark-unread" <?php echo 'unread' === $m->status ? 'style="display:none"' : ''; ?>><?php esc_html_e( 'Mark as Unread', 'ai-email-helper' ); ?></button>

							<label class="aieh-tool-label"><?php esc_html_e( 'Category:', 'ai-email-helper' ); ?>
								<select class="aieh-category-select">
									<option value=""><?php esc_html_e( '— none —', 'ai-email-helper' ); ?></option>
									<?php foreach ( $categories as $cat ) : ?>
										<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $m->category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>

							<?php if ( ! empty( $folders ) ) : ?>
								<label class="aieh-tool-label"><?php esc_html_e( 'Move to:', 'ai-email-helper' ); ?>
									<select class="aieh-move-select">
										<option value=""><?php esc_html_e( '— choose folder —', 'ai-email-helper' ); ?></option>
										<?php foreach ( $folders as $folder ) : ?>
											<?php if ( 'INBOX' === $folder ) { continue; } ?>
											<option value="<?php echo esc_attr( $folder ); ?>"><?php echo esc_html( $folder ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
							<?php endif; ?>
						</div>

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
							<button type="button" class="button aieh-write"><?php esc_html_e( 'Write Reply', 'ai-email-helper' ); ?></button>
						</div>

						<div class="aieh-draft-area" style="display:none">
							<textarea class="aieh-draft-text" rows="6" placeholder="<?php esc_attr_e( 'Type your reply here, then optionally polish it…', 'ai-email-helper' ); ?>"></textarea>
							<div class="aieh-actions">
								<button type="button" class="button aieh-polish"><?php esc_html_e( 'Improve & match my tone', 'ai-email-helper' ); ?></button>
								<button type="button" class="button button-primary aieh-send"><?php esc_html_e( 'Approve & Send', 'ai-email-helper' ); ?></button>
							</div>
						</div>
					</div>

					<div class="aieh-card-status"></div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
