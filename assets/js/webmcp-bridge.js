/**
 * WebMCP Bridge — registers this site's opt-in tools with the browser's
 * document.modelContext, so a WebMCP-aware AI client can search or interact
 * with this site the same way a visitor would.
 *
 * document.modelContext is the current object as of WebMCP's May 2026 spec
 * revision; navigator.modelContext (the pre-revision name) still works in
 * Chrome as a deprecated alias, so it's used here only as a fallback for
 * browser builds that predate the move. Every entry point starts with the
 * modelContext feature check below — WebMCP ships origin-trial/flag-gated in
 * Chrome as of this writing, so this script is a silent no-op everywhere else.
 *
 * Two kinds of tools are registered:
 *  1. Server-backed tools (agenticWebmcp.toolManifest) — a REST round-trip
 *     through agentic/v1/webmcp/execute, going through the same risk-gating
 *     Tool_Executor uses everywhere else in this plugin.
 *  2. Client-only form tools (Contact Form 7 / WPForms detectors) — fill and
 *     submit the visitor's own native DOM form directly. These never touch
 *     the REST API or Tool_Executor: submitting a form the same way the
 *     visitor could themselves is not a new risk surface, and inventing a
 *     generic "send arbitrary email via WP" server tool would be. Detected
 *     forms also get WebMCP's declarative attributes (toolname/
 *     tooldescription/toolparamdescription/toolautosubmit) set directly on
 *     the markup, forward-compatible with the spec's separate declarative
 *     registration surface — see addDeclarativeToolAttributes() below for
 *     why this runs alongside the imperative registerTool() call, not
 *     instead of it.
 *
 * @package Agent_Builder
 */
( function () {
	'use strict';

	var MODEL_CONTEXT = ( 'modelContext' in document && document.modelContext )
		|| ( 'modelContext' in navigator && navigator.modelContext )
		|| null;
	if ( ! MODEL_CONTEXT ) {
		return;
	}

	var CFG = ( typeof agenticWebmcp === 'object' && agenticWebmcp ) ? agenticWebmcp : {};
	if ( ! CFG.restUrl ) {
		return;
	}

	var sessionId = uuid();

	/* ───────────────────────── Utilities ───────────────────────── */

	function uuid() {
		if ( window.crypto && window.crypto.randomUUID ) {
			return window.crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, function ( c ) {
			var r = ( Math.random() * 16 ) | 0;
			var v = 'x' === c ? r : ( r & 0x3 ) | 0x8;
			return v.toString( 16 );
		} );
	}

	function restFetch( path, body ) {
		var headers = { 'Content-Type': 'application/json' };
		if ( CFG.nonce ) {
			headers[ 'X-WP-Nonce' ] = CFG.nonce;
		}
		return fetch( CFG.restUrl + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: headers,
			body: JSON.stringify( body )
		} ).then( function ( res ) {
			return res.json().then( function ( data ) {
				return { ok: res.ok, data: data };
			} );
		} );
	}

	/* ───────────────────── Server-backed tools ───────────────────── */

	function executeServerTool( tool, args ) {
		return restFetch( 'webmcp/execute', {
			tool_name: tool.name,
			agent_slug: tool.agent_slug,
			arguments: args || {},
			session_id: sessionId
		} ).then( function ( result ) {
			if ( ! result.ok ) {
				throw new Error( ( result.data && result.data.message ) || 'This action could not be completed.' );
			}
			if ( result.data && 'confirmation_required' === result.data.status ) {
				return confirmServerTool( result.data );
			}
			return result.data;
		} );
	}

	function confirmServerTool( pending ) {
		if ( ! window.agenticUI || 'function' !== typeof window.agenticUI.confirm ) {
			// No confirm primitive available on this page — fail closed rather
			// than silently executing an action the visitor never approved.
			return Promise.reject( new Error( 'This action needs confirmation, but the confirmation UI is unavailable here.' ) );
		}

		return window.agenticUI.confirm( pending.message, { title: 'Confirm action' } ).then( function ( approved ) {
			if ( ! approved ) {
				return { cancelled: true };
			}
			return restFetch( 'webmcp/confirm', { proposal_id: pending.proposal_id } ).then( function ( result ) {
				if ( ! result.ok ) {
					throw new Error( ( result.data && result.data.message ) || 'This action could not be confirmed.' );
				}
				return result.data;
			} );
		} );
	}

	function registerServerTools( manifest ) {
		( manifest || [] ).forEach( function ( tool ) {
			try {
				MODEL_CONTEXT.registerTool( {
					name: tool.name,
					description: tool.description,
					inputSchema: tool.inputSchema,
					execute: function ( args ) {
						return executeServerTool( tool, args );
					}
				} );
			} catch ( e ) {
				// A malformed inputSchema or a client that rejects this particular
				// registration should not stop the remaining tools from registering.
			}
		} );
	}

	/* ─────────────────── Client-only form detectors ─────────────────── */

	/**
	 * Build a JSON-Schema-ish inputSchema from a native <form>'s own visible,
	 * named text-like fields. Deliberately conservative: only text/email/tel/
	 * textarea/select fields with a name attribute are exposed — file inputs,
	 * hidden anti-spam fields, and honeypots are left alone.
	 */
	function schemaFromForm( form ) {
		var properties = {};
		var required = [];
		var fields = form.querySelectorAll( 'input[name], textarea[name], select[name]' );

		fields.forEach( function ( field ) {
			var type = ( field.getAttribute( 'type' ) || 'text' ).toLowerCase();
			if ( 'hidden' === type || 'file' === type || 'submit' === type || 'button' === type ) {
				return;
			}
			var name = field.getAttribute( 'name' );
			if ( ! name || properties[ name ] ) {
				return;
			}
			properties[ name ] = {
				type: 'string',
				description: field.getAttribute( 'aria-label' ) || field.getAttribute( 'placeholder' ) || name
			};
			if ( field.hasAttribute( 'required' ) ) {
				required.push( name );
			}
		} );

		return { type: 'object', properties: properties, required: required };
	}

	function fillAndSubmitForm( form, args ) {
		return new Promise( function ( resolve, reject ) {
			Object.keys( args || {} ).forEach( function ( name ) {
				var field = form.querySelector( '[name="' + name.replace( /"/g, '' ) + '"]' );
				if ( field ) {
					field.value = args[ name ];
				}
			} );

			var settled = false;
			var finish = function ( success ) {
				if ( settled ) {
					return;
				}
				settled = true;
				observer.disconnect();
				success ? resolve( { submitted: true } ) : reject( new Error( 'The form reported an error.' ) );
			};

			// Both plugins re-render their own success/error markup in place
			// after an AJAX submit — watch for it rather than assuming a fixed
			// delay. Falls back to "assume success" after 8s so a caller never
			// hangs forever on a plugin whose markup this bridge doesn't recognize.
			var observer = new MutationObserver( function () {
				if ( form.querySelector( '.wpcf7-mail-sent-ok, .wpforms-confirmation-container, .wpforms-confirmation-scroll' ) ) {
					finish( true );
				} else if ( form.querySelector( '.wpcf7-validation-errors, .wpcf7-mail-sent-ng, .wpforms-error-container' ) ) {
					finish( false );
				}
			} );
			observer.observe( form.parentNode || form, { childList: true, subtree: true } );

			var submitButton = form.querySelector( '[type="submit"]' );
			if ( submitButton ) {
				submitButton.click();
			} else if ( 'function' === typeof form.requestSubmit ) {
				form.requestSubmit();
			} else {
				form.submit();
			}

			setTimeout( function () {
				finish( true );
			}, 8000 );
		} );
	}

	/**
	 * Mark up a form with WebMCP's declarative tool attributes (toolname /
	 * tooldescription / toolautosubmit on the form, toolparamdescription on
	 * each field) alongside the imperative registerTool() call below.
	 *
	 * This is forward-compatible markup, not an active mechanism yet: the
	 * declarative synthesis algorithm is still a TODO in the core WebMCP spec
	 * text (webmachinelearning/webmcp's index.bs) — only its separate
	 * declarative-api-explainer.md proposal defines these attribute names,
	 * and no shipping browser (including Chrome's origin trial, which only
	 * implements the imperative document.modelContext.registerTool() API)
	 * reads them yet. Setting them is harmless either way — an
	 * unsupported/not-yet-supporting browser just sees inert HTML attributes
	 * — so this runs unconditionally alongside the imperative registration
	 * rather than instead of it. If a future browser starts synthesizing
	 * tools from these attributes on its own, a form carrying both could
	 * register twice; revisit once any real client actually implements this
	 * side of the spec, not before.
	 *
	 * toolautosubmit is set to match this bridge's own existing behavior
	 * (fillAndSubmitForm() already submits without a visitor confirmation
	 * step) — it doesn't grant the declarative path any capability the
	 * imperative one doesn't already have.
	 */
	function addDeclarativeToolAttributes( form, name, description ) {
		if ( ! form.hasAttribute( 'toolname' ) ) {
			form.setAttribute( 'toolname', name );
		}
		if ( description && ! form.hasAttribute( 'tooldescription' ) ) {
			form.setAttribute( 'tooldescription', description );
		}
		if ( ! form.hasAttribute( 'toolautosubmit' ) ) {
			form.setAttribute( 'toolautosubmit', '' );
		}

		form.querySelectorAll( 'input[name], textarea[name], select[name]' ).forEach( function ( field ) {
			var type = ( field.getAttribute( 'type' ) || 'text' ).toLowerCase();
			if ( 'hidden' === type || 'file' === type || 'submit' === type || 'button' === type ) {
				return;
			}
			if ( field.hasAttribute( 'toolparamdescription' ) ) {
				return;
			}
			var desc = field.getAttribute( 'aria-label' ) || field.getAttribute( 'placeholder' ) || field.getAttribute( 'name' );
			if ( desc ) {
				field.setAttribute( 'toolparamdescription', desc );
			}
		} );
	}

	function registerFormTool( name, description, form ) {
		addDeclarativeToolAttributes( form, name, description );

		try {
			MODEL_CONTEXT.registerTool( {
				name: name,
				description: description,
				inputSchema: schemaFromForm( form ),
				execute: function ( args ) {
					return fillAndSubmitForm( form, args );
				}
			} );
		} catch ( e ) {
			// Ignore — see registerServerTools() above.
		}
	}

	function watchAndRegister( selector, register ) {
		var seen = new WeakSet();
		var tryRegister = function () {
			document.querySelectorAll( selector ).forEach( function ( el ) {
				if ( ! seen.has( el ) ) {
					seen.add( el );
					register( el );
				}
			} );
		};
		tryRegister();
		// Forms/blocks can render late (AJAX-loaded widgets, client-side
		// routing) — keep watching rather than only checking once at load.
		new MutationObserver( tryRegister ).observe( document.body, { childList: true, subtree: true } );
	}

	function uniqueFormToolName( base ) {
		var count = ( uniqueFormToolName.counts[ base ] = ( uniqueFormToolName.counts[ base ] || 0 ) + 1 );
		return count > 1 ? base + '_' + count : base;
	}
	uniqueFormToolName.counts = {};

	function detectCF7Forms() {
		watchAndRegister( 'form.wpcf7-form', function ( form ) {
			registerFormTool( uniqueFormToolName( 'submit_contact_form' ), 'Fill in and submit the contact form on this page.', form );
		} );
	}

	function detectWPFormsForms() {
		watchAndRegister( 'form.wpforms-form', function ( form ) {
			registerFormTool( uniqueFormToolName( 'submit_contact_form' ), 'Fill in and submit the contact form on this page.', form );
		} );
	}

	/* ───────────────────────── Boot ───────────────────────── */

	document.addEventListener( 'DOMContentLoaded', function () {
		registerServerTools( CFG.toolManifest );
		detectCF7Forms();
		detectWPFormsForms();
	} );
} )();
