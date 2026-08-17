/* global AIEH, jQuery */
( function ( $ ) {
	'use strict';

	function post( action, data ) {
		return $.post( AIEH.ajaxUrl, $.extend( { action: action, nonce: AIEH.nonce }, data || {} ) );
	}

	function busy( $btn, on ) {
		$btn.prop( 'disabled', on );
	}

	/* ---- Settings: test IMAP ---- */
	$( '#aieh-test-imap' ).on( 'click', function () {
		var $btn = $( this );
		var $st = $( '#aieh-imap-status' ).text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );
		busy( $btn, true );
		post( 'aieh_test_imap' ).done( function ( r ) {
			if ( r.success ) {
				$st.text( r.data.message ).addClass( 'is-ok' );
			} else {
				$st.text( r.data.message ).addClass( 'is-error' );
			}
		} ).fail( function () {
			$st.text( 'Request failed.' ).addClass( 'is-error' );
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* ---- Inbox: fetch ---- */
	$( '#aieh-fetch' ).on( 'click', function () {
		var $btn = $( this );
		var $st = $( '#aieh-fetch-status' ).text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );
		busy( $btn, true );
		post( 'aieh_fetch' ).done( function ( r ) {
			if ( r.success ) {
				$st.text( r.data.message ).addClass( 'is-ok' );
				if ( r.data.new > 0 ) {
					window.location.reload();
				}
			} else {
				$st.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* ---- Inbox: sync unread from server ---- */
	$( '#aieh-sync-unread' ).on( 'click', function () {
		var $btn = $( this );
		var $st = $( '#aieh-fetch-status' ).text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );
		busy( $btn, true );
		post( 'aieh_sync_unread' ).done( function ( r ) {
			if ( r.success ) {
				$st.text( r.data.message ).addClass( 'is-ok' );
				window.location.reload();
			} else {
				$st.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* ---- Inbox: collapse / expand a card ---- */
	$( '.aieh-list' ).on( 'click', '.aieh-toggle', function ( e ) {
		if ( $( e.target ).closest( 'a, button, select, textarea' ).length ) {
			return;
		}
		var $card = $( this ).closest( '.aieh-card' );
		var open = $card.toggleClass( 'is-open' ).hasClass( 'is-open' );
		$card.children( '.aieh-card-body' ).prop( 'hidden', ! open );
	} );

	/* ---- Inbox: mark read / unread (mirrors the IMAP server) ---- */
	function setCardStatus( $card, status ) {
		$card.attr( 'data-status', status );
		$card.find( '.aieh-badge' )
			.attr( 'class', 'aieh-badge aieh-status-' + status )
			.text( status );
		$card.find( '.aieh-mark-read' ).toggle( 'unread' === status );
		$card.find( '.aieh-mark-unread' ).toggle( 'unread' !== status );
	}

	$( '.aieh-list' ).on( 'click', '.aieh-mark-read', function () {
		var $card = $( this ).closest( '.aieh-card' );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		post( 'aieh_mark_read', { id: $card.data( 'id' ) } ).done( function ( r ) {
			if ( r.success ) {
				setCardStatus( $card, 'read' );
				$status.text( '' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} );
	} );

	$( '.aieh-list' ).on( 'click', '.aieh-mark-unread', function () {
		var $card = $( this ).closest( '.aieh-card' );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		post( 'aieh_mark_unread', { id: $card.data( 'id' ) } ).done( function ( r ) {
			if ( r.success ) {
				setCardStatus( $card, 'unread' );
				$status.text( '' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} );
	} );

	/* ---- Inbox: category tag ---- */
	$( '.aieh-list' ).on( 'change', '.aieh-category-select', function () {
		var $card = $( this ).closest( '.aieh-card' );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		post( 'aieh_set_category', { id: $card.data( 'id' ), category: $( this ).val() } ).done( function ( r ) {
			if ( r.success ) {
				$status.text( r.data.message ).removeClass( 'is-error' ).addClass( 'is-ok' );
			} else {
				$status.text( r.data.message ).removeClass( 'is-ok' ).addClass( 'is-error' );
			}
		} );
	} );

	/* ---- Inbox: move to folder ---- */
	$( '.aieh-list' ).on( 'change', '.aieh-move-select', function () {
		var folder = $( this ).val();
		if ( ! folder ) {
			return;
		}
		var $card = $( this ).closest( '.aieh-card' );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		post( 'aieh_move', { id: $card.data( 'id' ), folder: folder } ).done( function ( r ) {
			if ( r.success ) {
				$card.fadeOut( 200, function () {
					$card.remove();
				} );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} );
	} );

	/* ---- Inbox: per-card actions ---- */
	$( '.aieh-list' ).on( 'click', '.aieh-summarize', function () {
		var $card = $( this ).closest( '.aieh-card' );
		var id = $card.data( 'id' );
		var $btn = $( this );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		busy( $btn, true );
		post( 'aieh_summarize', { id: id } ).done( function ( r ) {
			if ( r.success ) {
				$card.find( '.aieh-summary' ).removeClass( 'is-empty' ).text( r.data.summary );
				$status.text( '' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	$( '.aieh-list' ).on( 'click', '.aieh-draft', function () {
		var $card = $( this ).closest( '.aieh-card' );
		var id = $card.data( 'id' );
		var $btn = $( this );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		busy( $btn, true );
		post( 'aieh_draft', { id: id } ).done( function ( r ) {
			if ( r.success ) {
				$card.find( '.aieh-draft-area' ).show();
				$card.find( '.aieh-draft-text' ).val( r.data.draft );
				$status.text( '' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* ---- Write a reply from scratch ---- */
	$( '.aieh-list' ).on( 'click', '.aieh-write', function () {
		var $card = $( this ).closest( '.aieh-card' );
		$card.find( '.aieh-draft-area' ).show();
		$card.find( '.aieh-draft-text' ).focus();
	} );

	/* ---- Add this email to the To-Do board ---- */
	$( '.aieh-list' ).on( 'click', '.aieh-add-todo', function () {
		var $card = $( this ).closest( '.aieh-card' );
		var $btn = $( this );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );
		busy( $btn, true );
		post( 'aieh_add_to_todo', { id: $card.data( 'id' ) } ).done( function ( r ) {
			if ( r.success ) {
				$status.text( r.data.message ).addClass( 'is-ok' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* ---- Polish the typed reply: improve wording + match tone ---- */
	$( '.aieh-list' ).on( 'click', '.aieh-polish', function () {
		var $card = $( this ).closest( '.aieh-card' );
		var $ta = $card.find( '.aieh-draft-text' );
		var body = $ta.val();
		var $btn = $( this );
		var $status = $card.find( '.aieh-card-status' ).removeClass( 'is-error is-ok' );
		if ( ! body.trim() ) {
			$status.text( 'Type a reply first.' ).addClass( 'is-error' );
			return;
		}
		$status.text( AIEH.i18n.working );
		busy( $btn, true );
		post( 'aieh_polish', { id: $card.data( 'id' ), body: body } ).done( function ( r ) {
			if ( r.success ) {
				$ta.val( r.data.body );
				$status.text( 'Polished — review then Approve & Send.' ).addClass( 'is-ok' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	$( '.aieh-list' ).on( 'click', '.aieh-send', function () {
		if ( ! window.confirm( AIEH.i18n.confirm ) ) {
			return;
		}
		var $card = $( this ).closest( '.aieh-card' );
		var id = $card.data( 'id' );
		var body = $card.find( '.aieh-draft-text' ).val();
		var $btn = $( this );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		busy( $btn, true );
		post( 'aieh_send', { id: id, body: body } ).done( function ( r ) {
			if ( r.success ) {
				$status.text( r.data.message ).addClass( 'is-ok' );
				setCardStatus( $card, 'replied' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* ---- FAQ scan ---- */
	$( '#aieh-scan' ).on( 'click', function () {
		var url = $( '#aieh-faq-url' ).val();
		var $btn = $( this );
		var $st = $( '#aieh-scan-status' ).text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );
		busy( $btn, true );
		post( 'aieh_scan_faq', { url: url } ).done( function ( r ) {
			if ( r.success ) {
				$st.text( r.data.message ).addClass( 'is-ok' );
				window.location.reload();
			} else {
				$st.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	$( '.aieh-faq-table' ).on( 'click', '.aieh-delete-faq', function () {
		var $row = $( this ).closest( 'tr' );
		var id = $row.data( 'id' );
		post( 'aieh_delete_faq', { id: id } ).done( function ( r ) {
			if ( r.success ) {
				$row.fadeOut( 200, function () {
					$row.remove();
				} );
			}
		} );
	} );

	/* ---- FAQ: edit toggle + save ---- */
	$( '.aieh-faq-table' ).on( 'click', '.aieh-edit-faq', function () {
		var id = $( this ).closest( 'tr' ).data( 'id' );
		$( '.aieh-faq-edit-row[data-id="' + id + '"]' ).toggle();
	} );

	$( '.aieh-faq-table' ).on( 'click', '.aieh-faq-save', function () {
		var $row = $( this ).closest( '.aieh-faq-edit-row' );
		var id = $row.data( 'id' );
		var title = $row.find( '.aieh-faq-edit-title' ).val();
		var content = $row.find( '.aieh-faq-edit-content' ).val();
		var $btn = $( this );
		var $status = $row.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		busy( $btn, true );
		post( 'aieh_update_faq', { id: id, title: title, content: content } ).done( function ( r ) {
			if ( r.success ) {
				$status.text( r.data.message ).removeClass( 'is-error' ).addClass( 'is-ok' );
				window.location.reload();
			} else {
				$status.text( r.data.message ).removeClass( 'is-ok' ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* ---- Learning: save / delete ---- */
	$( '.aieh-learning-list' ).on( 'click', '.aieh-learning-save', function () {
		var $card = $( this ).closest( '.aieh-learning-card' );
		var id = $card.data( 'id' );
		var $btn = $( this );
		var $status = $card.find( '.aieh-card-status' ).text( AIEH.i18n.working );
		busy( $btn, true );
		post( 'aieh_update_learning', {
			id: id,
			prompt_summary: $card.find( '.aieh-learning-summary' ).val(),
			user_reply: $card.find( '.aieh-learning-reply' ).val(),
			tone_notes: $card.find( '.aieh-learning-notes' ).val()
		} ).done( function ( r ) {
			if ( r.success ) {
				$status.text( r.data.message ).removeClass( 'is-error' ).addClass( 'is-ok' );
			} else {
				$status.text( r.data.message ).removeClass( 'is-ok' ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	$( '.aieh-learning-list' ).on( 'click', '.aieh-learning-delete', function () {
		if ( ! window.confirm( AIEH.i18n.confirm ) ) {
			return;
		}
		var $card = $( this ).closest( '.aieh-learning-card' );
		var id = $card.data( 'id' );
		post( 'aieh_delete_learning', { id: id } ).done( function ( r ) {
			if ( r.success ) {
				$card.fadeOut( 200, function () {
					$card.remove();
				} );
			}
		} );
	} );

	/* ---- Shared: AI-assisted revise for learning replies & FAQ content ---- */
	$( '.aieh-list, .aieh-faq-table' ).on( 'click', '.aieh-ai-revise', function () {
		var $btn = $( this );
		var $row = $btn.closest( '.aieh-learning-card, .aieh-faq-edit-row' );
		var $target = $row.find( $btn.data( 'target' ) );
		var instruction = $row.find( '.aieh-revise-instruction' ).val();
		var $status = $row.find( '.aieh-card-status' ).text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );

		if ( ! instruction ) {
			$status.text( 'Enter an instruction first.' ).addClass( 'is-error' );
			return;
		}

		busy( $btn, true );
		post( 'aieh_ai_revise', { text: $target.val(), instruction: instruction } ).done( function ( r ) {
			if ( r.success ) {
				$target.val( r.data.revised );
				$status.text( 'Suggestion applied — review then Save.' ).addClass( 'is-ok' );
			} else {
				$status.text( r.data.message ).addClass( 'is-error' );
			}
		} ).always( function () {
			busy( $btn, false );
		} );
	} );

	/* =====================================================================
	 * To-Do / Kanban board
	 * ================================================================== */
	if ( $( '#aieh-board' ).length ) {

		var $todoStatus = $( '#aieh-todo-status' );

		/* Drag & drop cards between/within columns. */
		if ( $.fn.sortable ) {
			$( '.aieh-cards' ).sortable( {
				connectWith: '.aieh-cards',
				items: '> .aieh-task',
				placeholder: 'aieh-task-placeholder',
				forcePlaceholderSize: true,
				tolerance: 'pointer',
				cancel: 'input, textarea, select, button, [contenteditable]',
				stop: function () {
					$( '.aieh-cards' ).each( function () {
						var col = $( this ).data( 'column' );
						var ids = $( this ).children( '.aieh-task' ).map( function () {
							return $( this ).data( 'id' );
						} ).get();
						post( 'aieh_task_reorder', { column_id: col, ids: ids } );
					} );
				}
			} );

			/* Drag to reorder columns (grab the header, not the title/delete). */
			$( '#aieh-board' ).sortable( {
				items: '> .aieh-column',
				handle: '.aieh-column-head',
				cancel: '.aieh-col-title, .aieh-col-delete',
				tolerance: 'pointer',
				stop: function () {
					var ids = $( '#aieh-board' ).children( '.aieh-column' ).map( function () {
						return $( this ).data( 'column' );
					} ).get();
					post( 'aieh_column_reorder', { ids: ids } );
				}
			} );
		}

		/* Quick add card (Enter). */
		$( '.aieh-board' ).on( 'keydown', '.aieh-new-card-title', function ( e ) {
			if ( 13 !== e.which ) {
				return;
			}
			e.preventDefault();
			var title = $( this ).val().trim();
			var col = $( this ).closest( '.aieh-column' ).data( 'column' );
			if ( ! title ) {
				return;
			}
			post( 'aieh_task_create', { title: title, column_id: col } ).done( function ( r ) {
				if ( r.success ) {
					window.location.reload();
				}
			} );
		} );

		/* Edit / cancel / save a card. */
		$( '.aieh-board' ).on( 'click', '.aieh-task-edit', function () {
			$( this ).closest( '.aieh-task' ).find( '.aieh-task-form' ).prop( 'hidden', false );
		} );
		$( '.aieh-board' ).on( 'click', '.aieh-task-cancel', function () {
			$( this ).closest( '.aieh-task-form' ).prop( 'hidden', true );
		} );
		$( '.aieh-board' ).on( 'click', '.aieh-task-save', function () {
			var $t = $( this ).closest( '.aieh-task' );
			var $f = $t.find( '.aieh-task-form' );
			post( 'aieh_task_update', {
				id: $t.data( 'id' ),
				title: $f.find( '.aieh-tf-title' ).val(),
				notes: $f.find( '.aieh-tf-notes' ).val(),
				priority: $f.find( '.aieh-tf-priority' ).val(),
				category: $f.find( '.aieh-tf-category' ).val(),
				due_date: $f.find( '.aieh-tf-due' ).val(),
				recurrence: $f.find( '.aieh-tf-recurrence' ).val()
			} ).done( function ( r ) {
				if ( r.success ) {
					window.location.reload();
				}
			} );
		} );

		/* Mark a card complete (timestamps + reschedules if recurring). */
		$( '.aieh-board' ).on( 'click', '.aieh-task-complete', function () {
			var $t = $( this ).closest( '.aieh-task' );
			post( 'aieh_task_complete', { id: $t.data( 'id' ) } ).done( function ( r ) {
				if ( r.success ) {
					window.location.reload();
				}
			} );
		} );

		/* Delete a card. */
		$( '.aieh-board' ).on( 'click', '.aieh-task-delete', function () {
			if ( ! window.confirm( 'Delete this card?' ) ) {
				return;
			}
			var $t = $( this ).closest( '.aieh-task' );
			post( 'aieh_task_delete', { id: $t.data( 'id' ) } ).done( function ( r ) {
				if ( r.success ) {
					$t.fadeOut( 150, function () {
						$t.remove();
					} );
				}
			} );
		} );

		/* Rename column on blur. */
		$( '.aieh-board' ).on( 'blur', '.aieh-col-title', function () {
			post( 'aieh_column_rename', { id: $( this ).data( 'column' ), label: $( this ).text().trim() } );
		} );

		/* Save column AI description on blur. */
		$( '.aieh-board' ).on( 'blur', '.aieh-col-desc', function () {
			post( 'aieh_column_describe', { id: $( this ).data( 'column' ), description: $( this ).text().trim() } );
		} );

		/* Delete column. */
		$( '.aieh-board' ).on( 'click', '.aieh-col-delete', function () {
			if ( ! window.confirm( 'Delete this column? Its cards move to the first column.' ) ) {
				return;
			}
			var id = $( this ).closest( '.aieh-column' ).data( 'column' );
			post( 'aieh_column_delete', { id: id } ).done( function ( r ) {
				if ( r.success ) {
					window.location.reload();
				}
			} );
		} );

		/* Add column. */
		$( '#aieh-add-column' ).on( 'click', function () {
			var label = $( '#aieh-new-column' ).val().trim();
			if ( ! label ) {
				return;
			}
			post( 'aieh_column_add', { label: label, description: $( '#aieh-new-column-desc' ).val().trim() } ).done( function ( r ) {
				if ( r.success ) {
					window.location.reload();
				}
			} );
		} );

		/* AI prioritise & sort. */
		$( '#aieh-ai-prioritise' ).on( 'click', function () {
			var $btn = $( this );
			$todoStatus.text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );
			busy( $btn, true );
			post( 'aieh_ai_prioritise' ).done( function ( r ) {
				if ( r.success ) {
					$todoStatus.text( r.data.message ).addClass( 'is-ok' );
					window.location.reload();
				} else {
					$todoStatus.text( r.data.message ).addClass( 'is-error' );
				}
			} ).always( function () {
				busy( $btn, false );
			} );
		} );

		/* AI overview briefing. */
		$( '#aieh-ai-overview' ).on( 'click', function () {
			var $btn = $( this );
			$todoStatus.text( AIEH.i18n.working ).removeClass( 'is-error is-ok' );
			busy( $btn, true );
			post( 'aieh_ai_overview' ).done( function ( r ) {
				if ( r.success ) {
					$todoStatus.text( '' );
					$( '#aieh-overview-panel' ).text( r.data.overview ).prop( 'hidden', false );
				} else {
					$todoStatus.text( r.data.message ).addClass( 'is-error' );
				}
			} ).always( function () {
				busy( $btn, false );
			} );
		} );
	}

} )( jQuery );
