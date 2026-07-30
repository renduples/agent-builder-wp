/**
 * Shared search filter for the vertical settings-style navigation.
 *
 * Auto-initialises every `.agentic-settings-sidebar` on the page that contains
 * a search input. Filtering matches against each nav item's
 * `data-filter-label` (falling back to its text), hides non-matching items and
 * now-empty groups, and toggles the `.agentic-settings-nav-empty` message.
 *
 * Plain vanilla JS — no build step or dependencies.
 */
( function () {
	'use strict';

	function initSidebar( sidebar ) {
		var input = sidebar.querySelector( 'input[type="search"]' );
		var nav = sidebar.querySelector( '.agentic-settings-nav' );
		if ( ! input || ! nav ) {
			return;
		}

		var groups = nav.querySelectorAll( '.agentic-settings-nav-group' );
		var empty = nav.querySelector( '.agentic-settings-nav-empty' );

		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			var anyVisible = false;

			groups.forEach( function ( group ) {
				var groupHasMatch = false;
				group.querySelectorAll( '.agentic-settings-nav-item' ).forEach( function ( item ) {
					var label = ( item.getAttribute( 'data-filter-label' ) || item.textContent ).toLowerCase();
					var match = q === '' || label.indexOf( q ) !== -1;
					item.parentNode.hidden = ! match;
					if ( match ) {
						groupHasMatch = true;
						anyVisible = true;
					}
				} );
				group.hidden = ! groupHasMatch;
			} );

			if ( empty ) {
				empty.hidden = anyVisible;
			}
		} );
	}

	function init() {
		document.querySelectorAll( '.agentic-settings-sidebar' ).forEach( initSidebar );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
