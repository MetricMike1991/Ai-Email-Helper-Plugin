<?php
/**
 * To-Do (Kanban) board view.
 *
 * @package AI_Email_Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$columns    = AIEH_Tasks::columns();
$tasks      = AIEH_Tasks::all();
$categories = array( 'Lead', 'Customer', 'Invoice', 'Complaint', 'Supplier', 'Personal', 'Spam', 'Other' );
$priorities = array(
	0 => __( '— none —', 'ai-email-helper' ),
	1 => __( 'Low', 'ai-email-helper' ),
	2 => __( 'Medium', 'ai-email-helper' ),
	3 => __( 'High', 'ai-email-helper' ),
);

$by_col = array();
foreach ( $tasks as $t ) {
	$by_col[ $t->column_id ][] = $t;
}
?>
<div class="wrap aieh-wrap aieh-kanban-wrap">
	<h1>
		<?php esc_html_e( 'AI Email Helper — To-Do Board', 'ai-email-helper' ); ?>
		<button type="button" class="button button-primary" id="aieh-ai-prioritise"><?php esc_html_e( 'AI Prioritise &amp; Sort', 'ai-email-helper' ); ?></button>
		<button type="button" class="button" id="aieh-ai-overview"><?php esc_html_e( 'AI Overview', 'ai-email-helper' ); ?></button>
		<span class="aieh-inline-status" id="aieh-todo-status"></span>
	</h1>

	<div id="aieh-overview-panel" class="aieh-overview-panel" hidden></div>

	<div class="aieh-add-column">
		<input type="text" id="aieh-new-column" placeholder="<?php esc_attr_e( 'New column name…', 'ai-email-helper' ); ?>">
		<input type="text" id="aieh-new-column-desc" placeholder="<?php esc_attr_e( 'What belongs here (AI hint)…', 'ai-email-helper' ); ?>">
		<button type="button" class="button" id="aieh-add-column"><?php esc_html_e( 'Add Column', 'ai-email-helper' ); ?></button>
	</div>

	<div class="aieh-board" id="aieh-board">
		<?php foreach ( $columns as $col ) : ?>
			<div class="aieh-column" data-column="<?php echo esc_attr( $col['id'] ); ?>">
				<div class="aieh-column-head">
					<span class="aieh-col-title" data-column="<?php echo esc_attr( $col['id'] ); ?>" contenteditable="true"><?php echo esc_html( $col['label'] ); ?></span>
					<button type="button" class="aieh-col-delete" title="<?php esc_attr_e( 'Delete column', 'ai-email-helper' ); ?>">&times;</button>
				</div>
				<div class="aieh-col-desc" data-column="<?php echo esc_attr( $col['id'] ); ?>" contenteditable="true" data-placeholder="<?php esc_attr_e( 'Describe what belongs here (AI hint)…', 'ai-email-helper' ); ?>"><?php echo esc_html( $col['description'] ); ?></div>

				<div class="aieh-cards" data-column="<?php echo esc_attr( $col['id'] ); ?>">
					<?php if ( ! empty( $by_col[ $col['id'] ] ) ) : ?>
						<?php foreach ( $by_col[ $col['id'] ] as $t ) : ?>
							<div class="aieh-task" data-id="<?php echo esc_attr( $t->id ); ?>" data-priority="<?php echo (int) $t->priority; ?>">
								<span class="aieh-prio-strip prio-<?php echo (int) $t->priority; ?>"></span>
								<div class="aieh-task-title"><?php echo esc_html( $t->title ); ?></div>

								<div class="aieh-task-meta">
									<?php if ( (int) $t->priority > 0 ) : ?>
										<span class="aieh-prio-badge prio-<?php echo (int) $t->priority; ?>"><?php echo esc_html( $priorities[ (int) $t->priority ] ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $t->category ) : ?><span class="aieh-cat-tag"><?php echo esc_html( $t->category ); ?></span><?php endif; ?>
									<?php if ( ! empty( $t->due_date ) && '0000-00-00 00:00:00' !== $t->due_date ) : ?>
										<span class="aieh-due">📅 <?php echo esc_html( gmdate( 'j M Y', strtotime( $t->due_date ) ) ); ?></span>
									<?php endif; ?>
									<?php if ( 'email' === $t->source ) : ?>
										<span class="aieh-task-badge" title="<?php echo esc_attr( $t->email_subject ); ?>">✉ <?php echo esc_html( $t->email_from ? $t->email_from : __( 'from email', 'ai-email-helper' ) ); ?></span>
									<?php endif; ?>
								</div>

								<?php if ( '' !== trim( (string) $t->notes ) ) : ?>
									<div class="aieh-task-notes"><?php echo nl2br( esc_html( $t->notes ) ); ?></div>
								<?php endif; ?>

								<div class="aieh-task-actions">
									<button type="button" class="button-link aieh-task-edit"><?php esc_html_e( 'Edit', 'ai-email-helper' ); ?></button>
									<button type="button" class="button-link aieh-task-delete"><?php esc_html_e( 'Delete', 'ai-email-helper' ); ?></button>
								</div>

								<div class="aieh-task-form" hidden>
									<input type="text" class="aieh-tf-title" value="<?php echo esc_attr( $t->title ); ?>" placeholder="<?php esc_attr_e( 'Title', 'ai-email-helper' ); ?>">
									<textarea class="aieh-tf-notes" rows="3" placeholder="<?php esc_attr_e( 'Notes…', 'ai-email-helper' ); ?>"><?php echo esc_textarea( $t->notes ); ?></textarea>
									<div class="aieh-tf-row">
										<label><?php esc_html_e( 'Priority', 'ai-email-helper' ); ?>
											<select class="aieh-tf-priority">
												<?php foreach ( $priorities as $pv => $pl ) : ?>
													<option value="<?php echo (int) $pv; ?>" <?php selected( (int) $t->priority, $pv ); ?>><?php echo esc_html( $pl ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
										<label><?php esc_html_e( 'Category', 'ai-email-helper' ); ?>
											<select class="aieh-tf-category">
												<option value=""><?php esc_html_e( '— none —', 'ai-email-helper' ); ?></option>
												<?php foreach ( $categories as $cat ) : ?>
													<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $t->category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
										<label><?php esc_html_e( 'Due', 'ai-email-helper' ); ?>
											<input type="date" class="aieh-tf-due" value="<?php echo ! empty( $t->due_date ) ? esc_attr( gmdate( 'Y-m-d', strtotime( $t->due_date ) ) ) : ''; ?>">
										</label>
									</div>
									<div class="aieh-actions">
										<button type="button" class="button button-primary aieh-task-save"><?php esc_html_e( 'Save', 'ai-email-helper' ); ?></button>
										<button type="button" class="button-link aieh-task-cancel"><?php esc_html_e( 'Cancel', 'ai-email-helper' ); ?></button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<div class="aieh-add-card">
					<input type="text" class="aieh-new-card-title" placeholder="<?php esc_attr_e( '+ Add a card', 'ai-email-helper' ); ?>">
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
