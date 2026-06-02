/* global ReadMeWP, jQuery */
( function ( $ ) {
	'use strict';

	const $search      = $( '#readmewp-user-search' );
	const $suggestions = $( '#readmewp-user-suggestions' );
	const $selected    = $( '#readmewp-selected-users' );

	if ( ! $search.length ) {
		return;
	}

	let searchTimer = null;

	// -------------------------------------------------------------------------
	// Live search
	// -------------------------------------------------------------------------
	$search.on( 'input', function () {
		const term = $( this ).val().trim();
		clearTimeout( searchTimer );

		if ( term.length < 2 ) {
			$suggestions.hide().empty();
			return;
		}

		searchTimer = setTimeout( function () {
			// J-01: Use $.post (not $.get) so the search term isn't logged in
			// server access logs as a query-string parameter.
			$.post( ReadMeWP.ajaxUrl, {
				action : 'readmewp_user_search',
				nonce  : ReadMeWP.nonce,
				term   : term,
			} )
				.done( function ( response ) {
					$suggestions.empty();

					if ( ! response.success || ! response.data.length ) {
						$suggestions.hide();
						return;
					}

					response.data.forEach( function ( user ) {
						// J-03: Guard against missing/non-numeric user.id before
						// using it in a selector — prevents selector-injection.
						const uid = parseInt( user.id, 10 );
						if ( ! uid ) {
							return;
						}
						// Skip already-selected users.
						if ( $selected.find( '[data-user-id="' + uid + '"]' ).length ) {
							return;
						}
						$suggestions.append(
							$( '<li>' )
								.text( user.label )
								.data( 'user', { id: uid, label: user.label } )
						);
					} );

					if ( $suggestions.children().length ) {
						$suggestions.show();
					} else {
						$suggestions.hide();
					}
				} )
				// J-02: Surface AJAX failures (expired nonce, server error) so
				// they don't silently vanish in the UI.
				.fail( function () {
					$suggestions.hide().empty();
					// eslint-disable-next-line no-console
					console.warn( 'ReadMeWP: user search request failed. The nonce may have expired — try reloading the page.' );
				} );
		}, 250 );
	} );

	// -------------------------------------------------------------------------
	// Select a suggested user
	// -------------------------------------------------------------------------
	$suggestions.on( 'click', 'li', function () {
		const user = $( this ).data( 'user' );
		addUser( user );
		$suggestions.hide().empty();
		$search.val( '' );
	} );

	// -------------------------------------------------------------------------
	// Remove a selected user
	// -------------------------------------------------------------------------
	$selected.on( 'click', '.readmewp-remove-user', function () {
		$( this ).closest( 'li' ).remove();
	} );

	// -------------------------------------------------------------------------
	// Keyboard: close suggestions on Escape
	// -------------------------------------------------------------------------
	$search.on( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			$suggestions.hide().empty();
		}
	} );

	// -------------------------------------------------------------------------
	// Close suggestions when clicking outside
	// -------------------------------------------------------------------------
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '.readmewp-user-search-wrap' ).length ) {
			$suggestions.hide().empty();
		}
	} );

	// -------------------------------------------------------------------------
	// Helper: add a user chip to the selected list
	// -------------------------------------------------------------------------
	function addUser( user ) {
		$selected.append(
			$( '<li>' )
				.attr( 'data-user-id', user.id )
				.append(
					$( '<span>' )
						.addClass( 'readmewp-user-label' )
						.text( user.label )
				)
				.append(
					$( '<button>' )
						.attr( 'type', 'button' )
						.addClass( 'readmewp-remove-user' )
						.attr( 'aria-label', ReadMeWP.strings.remove )
						.html( '&#x2715;' )
				)
				.append(
					$( '<input>' )
						.attr( 'type', 'hidden' )
						.attr( 'name', 'readmewp_creator_users[]' )
						.val( user.id )
				)
		);
	}
} )( jQuery );
