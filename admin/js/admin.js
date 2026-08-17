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

} )( jQuery );
