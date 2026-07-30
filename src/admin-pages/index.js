/**
 * Multi-page React admin surfaces (tools, skills list, approvals, logs, etc.).
 * Agents list intentionally excluded (WordPress plugins-style UI later).
 */
import { createRoot, useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Spinner,
	Notice,
	ToggleControl,
	SearchControl,
	ExternalLink,
} from '@wordpress/components';
import { AdminPage, Panel } from '../shared/components';

function bootConfig() {
	return window.agenticAdminPage || { page: 'tools', tab: '' };
}

/**
 * Standard admin footer: policy blurb + support/docs + legal links.
 * Matches PHP agentic-page-footer used on classic admin screens.
 */
function AdminPageFooter( { footer } ) {
	const f = footer || bootConfig().footer || {};
	const docUrl = f.doc_url || 'https://agentic-plugin.com/agent-tools/';
	const supportUrl = f.support_url || 'https://agentic-plugin.com/support/';
	const promoUrl = f.promo_url || 'admin.php?page=agentic-upgrade-pro';
	const promoLabel =
		f.promo_label || __( 'Upgrade to Pro', 'agent-builder' );
	const policy =
		f.policy ||
		__(
			'Assistants only use the tools you allow. Higher-risk actions still follow Approvals and your safety settings.',
			'agent-builder'
		);

	return (
		<div className="agentic-page-footer agentic-page-footer--react">
			<span className="agentic-page-footer-left">
				{ policy && (
					<span className="agentic-page-footer-policy">
						{ policy }{ ' ' }
					</span>
				) }
				{ __( 'Need help?', 'agent-builder' ) }{ ' ' }
				<a href={ supportUrl } target="_blank" rel="noopener noreferrer">
					{ __( 'Visit our Support Center', 'agent-builder' ) }
				</a>
				{ ' | ' }
				<a href={ docUrl } target="_blank" rel="noopener noreferrer">
					{ __( 'Documentation', 'agent-builder' ) }
				</a>
				{ ' | ' }
				<a
					href={ promoUrl }
					target={ f.is_pro ? '_blank' : undefined }
					rel={ f.is_pro ? 'noopener noreferrer' : undefined }
				>
					{ promoLabel }
				</a>
			</span>
			<span className="agentic-page-footer-right">
				<a
					href={
						f.terms_url ||
						'https://agentic-plugin.com/terms-of-service/'
					}
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Terms of Service', 'agent-builder' ) }
				</a>
				{ ' | ' }
				<a
					href={
						f.privacy_url ||
						'https://agentic-plugin.com/privacy-policy/'
					}
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Privacy Policy', 'agent-builder' ) }
				</a>
				{ ' | ' }
				<a
					href={
						f.gdpr_url || 'https://agentic-plugin.com/gdpr-policy/'
					}
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'GDPR Policy', 'agent-builder' ) }
				</a>
			</span>
		</div>
	);
}

function usePageData( page, tab ) {
	const [ state, setState ] = useState( {
		loading: true,
		error: '',
		data: null,
	} );
	// Extra query args (e.g. period for Activity) from the current admin URL.
	const extraQuery = useMemo( () => {
		try {
			const sp = new URLSearchParams( window.location.search );
			const period = sp.get( 'period' );
			return period ? { period } : {};
		} catch ( e ) {
			return {};
		}
	}, [] );

	const reload = useCallback(
		( opts = {} ) => {
			const silent = !! opts.silent;
			if ( ! silent ) {
				setState( ( s ) => ( { ...s, loading: true, error: '' } ) );
			}
			const period =
				opts.period ||
				extraQuery.period ||
				new URLSearchParams( window.location.search ).get( 'period' ) ||
				'';
			let path =
				`agentic/v1/admin-page?page=${ encodeURIComponent( page ) }` +
				( tab ? `&tab=${ encodeURIComponent( tab ) }` : '' );
			if ( period ) {
				path += `&period=${ encodeURIComponent( period ) }`;
			}
			apiFetch( { path } )
				.then( ( data ) =>
					setState( { loading: false, error: '', data } )
				)
				.catch( ( err ) =>
					setState( {
						loading: false,
						error:
							err.message ||
							__( 'Could not load page.', 'agent-builder' ),
						data: silent ? state.data : null,
					} )
				);
		},
		// state.data only used on silent failure fallback — omit from deps to keep reload stable.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ page, tab, extraQuery.period ]
	);

	/** Patch page data in place (no loading flash). */
	const patchData = useCallback( ( updater ) => {
		setState( ( s ) => {
			if ( ! s.data ) {
				return s;
			}
			const next =
				typeof updater === 'function' ? updater( s.data ) : updater;
			return { ...s, data: next };
		} );
	}, [] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	return [ state, reload, setState, patchData ];
}

function TabBar( { tabs, active, className = '' } ) {
	if ( ! tabs?.length ) {
		return null;
	}
	return (
		<nav
			className={ `agentic-react-tabs ${ className }`.trim() }
			aria-label={ __( 'Sections', 'agent-builder' ) }
		>
			{ tabs.map( ( t ) => (
				<a
					key={ t.id }
					href={ t.url }
					className={
						'agentic-react-tabs__tab' +
						( t.id === active ? ' is-active' : '' )
					}
				>
					{ t.label }
				</a>
			) ) }
		</nav>
	);
}

function SiteLocalToolsPanel( { siteLocal, reload } ) {
	const pro = !! siteLocal?.pro;
	const canManage = !! siteLocal?.can_manage;
	const handlers = siteLocal?.handlers || [];
	const agents = siteLocal?.agents || [];
	const [ err, setErr ] = useState( '' );
	const [ msg, setMsg ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ testOut, setTestOut ] = useState( '' );
	const [ form, setForm ] = useState( {
		name: '',
		label: '',
		description: '',
		handler: handlers[ 0 ]?.id || 'wp_list_posts',
		risk_level: 'low',
		category: 'custom',
		enabled: true,
		agent_slugs: [],
		allowed_options: 'blogname,blogdescription',
		allowed_hosts: '',
		test_args: '{}',
	} );

	const setField = ( k, v ) => setForm( ( f ) => ( { ...f, [ k ]: v } ) );

	const onHandlerChange = ( id ) => {
		const h = handlers.find( ( x ) => x.id === id );
		setForm( ( f ) => ( {
			...f,
			handler: id,
			risk_level: h?.risk || f.risk_level,
		} ) );
	};

	const save = () => {
		setBusy( true );
		setErr( '' );
		setMsg( '' );
		const payload = {
			name: form.name,
			label: form.label || form.name,
			description: form.description,
			handler: form.handler,
			risk_level: form.risk_level,
			category: form.category || 'custom',
			enabled: form.enabled,
			agent_slugs: form.agent_slugs,
			config: {
				allowed_options: form.allowed_options,
				allowed_hosts: form.allowed_hosts,
			},
		};
		apiFetch( {
			path: 'agentic/v1/site-local-tools',
			method: 'POST',
			data: payload,
		} )
			.then( () => {
				setMsg( __( 'Site-local tool saved.', 'agent-builder' ) );
				reload();
			} )
			.catch( ( e ) =>
				setErr(
					e.message ||
						__( 'Could not save tool (Pro required).', 'agent-builder' )
				)
			)
			.finally( () => setBusy( false ) );
	};

	const remove = ( name ) => {
		if (
			! window.confirm(
				__( 'Delete this site-local tool?', 'agent-builder' )
			)
		) {
			return;
		}
		setBusy( true );
		apiFetch( {
			path: `agentic/v1/site-local-tools/${ encodeURIComponent( name ) }`,
			method: 'DELETE',
		} )
			.then( () => reload() )
			.catch( ( e ) =>
				setErr( e.message || __( 'Delete failed.', 'agent-builder' ) )
			)
			.finally( () => setBusy( false ) );
	};

	const runTest = ( name ) => {
		let args = {};
		try {
			args = JSON.parse( form.test_args || '{}' );
		} catch ( e ) {
			setErr( __( 'Test arguments must be valid JSON.', 'agent-builder' ) );
			return;
		}
		setBusy( true );
		setTestOut( '' );
		apiFetch( {
			path: `agentic/v1/site-local-tools/${ encodeURIComponent(
				name
			) }/test`,
			method: 'POST',
			data: { arguments: args },
		} )
			.then( ( res ) =>
				setTestOut( JSON.stringify( res.result || res, null, 2 ) )
			)
			.catch( ( e ) =>
				setErr( e.message || __( 'Test failed.', 'agent-builder' ) )
			)
			.finally( () => setBusy( false ) );
	};

	const toggleAgent = ( id ) => {
		setForm( ( f ) => {
			const has = f.agent_slugs.includes( id );
			return {
				...f,
				agent_slugs: has
					? f.agent_slugs.filter( ( x ) => x !== id )
					: [ ...f.agent_slugs, id ],
			};
		} );
	};

	if ( ! pro ) {
		return (
			<div className="agentic-react-site-local-upsell">
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Site-local tool builder is a Pro feature. Free includes using shipped tools, risk/approvals, and developer Tool_Base packages.',
						'agent-builder'
					) }{ ' ' }
					{ siteLocal?.upgrade_url && (
						<ExternalLink href={ siteLocal.upgrade_url }>
							{ __( 'Upgrade to Pro', 'agent-builder' ) }
						</ExternalLink>
					) }
					{ ' · ' }
					{ siteLocal?.docs_url && (
						<ExternalLink href={ siteLocal.docs_url }>
							{ __( 'Docs: Free vs Pro', 'agent-builder' ) }
						</ExternalLink>
					) }
				</Notice>
			</div>
		);
	}

	return (
		<div className="agentic-react-site-local">
			{ err && (
				<Notice status="error" isDismissible={ false }>
					{ err }
				</Notice>
			) }
			{ msg && (
				<Notice status="success" isDismissible={ false }>
					{ msg }
				</Notice>
			) }

			<h3>{ __( 'Add site-local tool', 'agent-builder' ) }</h3>
			<p className="agentic-react-muted">
				{ __(
					'Declarative handlers only — no PHP. Empty agent list = available to all active agents.',
					'agent-builder'
				) }
			</p>

			<div className="agentic-react-form-grid">
				<label>
					<span>{ __( 'Name (snake_case)', 'agent-builder' ) }</span>
					<input
						type="text"
						value={ form.name }
						onChange={ ( e ) => setField( 'name', e.target.value ) }
						placeholder="my_list_drafts"
					/>
				</label>
				<label>
					<span>{ __( 'Label', 'agent-builder' ) }</span>
					<input
						type="text"
						value={ form.label }
						onChange={ ( e ) => setField( 'label', e.target.value ) }
					/>
				</label>
				<label className="agentic-react-form-full">
					<span>{ __( 'Description (for the LLM)', 'agent-builder' ) }</span>
					<textarea
						rows={ 3 }
						value={ form.description }
						onChange={ ( e ) =>
							setField( 'description', e.target.value )
						}
					/>
				</label>
				<label>
					<span>{ __( 'Handler', 'agent-builder' ) }</span>
					<select
						value={ form.handler }
						onChange={ ( e ) => onHandlerChange( e.target.value ) }
					>
						{ handlers.map( ( h ) => (
							<option key={ h.id } value={ h.id }>
								{ h.label } ({ h.risk })
							</option>
						) ) }
					</select>
				</label>
				<label>
					<span>{ __( 'Risk level', 'agent-builder' ) }</span>
					<select
						value={ form.risk_level }
						onChange={ ( e ) =>
							setField( 'risk_level', e.target.value )
						}
					>
						{ [ 'none', 'low', 'medium', 'high' ].map( ( r ) => (
							<option key={ r } value={ r }>
								{ r }
							</option>
						) ) }
					</select>
				</label>
				{ form.handler === 'wp_get_option' && (
					<label className="agentic-react-form-full">
						<span>
							{ __(
								'Allowed option keys (comma-separated)',
								'agent-builder'
							) }
						</span>
						<input
							type="text"
							value={ form.allowed_options }
							onChange={ ( e ) =>
								setField( 'allowed_options', e.target.value )
							}
						/>
					</label>
				) }
				{ form.handler === 'http_get' && (
					<label className="agentic-react-form-full">
						<span>
							{ __(
								'Extra allowed hosts (comma-separated; site host always allowed)',
								'agent-builder'
							) }
						</span>
						<input
							type="text"
							value={ form.allowed_hosts }
							onChange={ ( e ) =>
								setField( 'allowed_hosts', e.target.value )
							}
						/>
					</label>
				) }
				<div className="agentic-react-form-full">
					<span>{ __( 'Agents (optional)', 'agent-builder' ) }</span>
					<div className="agentic-react-chip-row">
						{ agents.map( ( a ) => (
							<label key={ a.id } className="agentic-react-chip">
								<input
									type="checkbox"
									checked={ form.agent_slugs.includes( a.id ) }
									onChange={ () => toggleAgent( a.id ) }
								/>
								{ a.name }
							</label>
						) ) }
					</div>
				</div>
			</div>

			<div className="agentic-react-tools-toolbar">
				<Button
					variant="primary"
					disabled={ busy || ! canManage }
					onClick={ save }
				>
					{ __( 'Save site-local tool', 'agent-builder' ) }
				</Button>
			</div>

			{ ( siteLocal.site_tools || [] ).length > 0 && (
				<>
					<h3>{ __( 'Your site-local tools', 'agent-builder' ) }</h3>
					<div className="agentic-react-table-wrap">
						<table className="agentic-react-table">
							<thead>
								<tr>
									<th>{ __( 'Name', 'agent-builder' ) }</th>
									<th>{ __( 'Handler', 'agent-builder' ) }</th>
									<th>{ __( 'Risk', 'agent-builder' ) }</th>
									<th>{ __( 'Actions', 'agent-builder' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ siteLocal.site_tools.map( ( t ) => (
									<tr key={ t.name }>
										<td>
											<strong>{ t.name }</strong>
											<div className="agentic-react-muted">
												{ t.description }
											</div>
										</td>
										<td>
											<code>{ t.handler }</code>
										</td>
										<td>{ t.risk_level }</td>
										<td>
											<Button
												variant="secondary"
												isSmall
												disabled={ busy }
												onClick={ () =>
													runTest( t.name )
												}
											>
												{ __( 'Test', 'agent-builder' ) }
											</Button>{ ' ' }
											<Button
												variant="secondary"
												isDestructive
												isSmall
												disabled={ busy }
												onClick={ () =>
													remove( t.name )
												}
											>
												{ __( 'Delete', 'agent-builder' ) }
											</Button>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
					<label className="agentic-react-form-full">
						<span>
							{ __(
								'Test arguments (JSON object)',
								'agent-builder'
							) }
						</span>
						<textarea
							rows={ 2 }
							value={ form.test_args }
							onChange={ ( e ) =>
								setField( 'test_args', e.target.value )
							}
						/>
					</label>
					{ testOut && (
						<pre className="agentic-react-test-out">{ testOut }</pre>
					) }
				</>
			) }
		</div>
	);
}

function ToolsBasicProfiles( { data, reload } ) {
	const [ busy, setBusy ] = useState( '' );
	const [ err, setErr ] = useState( '' );
	const [ ok, setOk ] = useState( '' );
	const profiles = data.profiles || [];
	const active = data.active_profile || '';
	const interfaceUrl =
		data.interface_url ||
		'admin.php?page=agentic-settings&tab=interface';

	const apply = ( profileId ) => {
		if ( busy ) {
			return;
		}
		setBusy( profileId );
		setErr( '' );
		setOk( '' );
		apiFetch( {
			path: 'agentic/v1/admin-page',
			method: 'POST',
			data: {
				action_name: 'apply_tools_profile',
				profile: profileId,
			},
		} )
			.then( ( res ) => {
				const r = res?.result || {};
				setOk(
					sprintf(
						/* translators: 1: enabled count, 2: disabled count */
						__(
							'Profile applied: %1$d tools on, %2$d tools off.',
							'agent-builder'
						),
						r.enabled ?? 0,
						r.disabled ?? 0
					)
				);
				reload( { silent: true } );
			} )
			.catch( ( e ) =>
				setErr(
					e.message ||
						__( 'Could not apply profile.', 'agent-builder' )
				)
			)
			.finally( () => setBusy( '' ) );
	};

	return (
		<div className="agentic-react-tools-basic">
			<p className="agentic-react-lead">
				{ data.description }
			</p>
			<p className="agentic-react-muted">
				{ __(
					'These profiles control which tools every assistant may use. Approvals still apply for riskier actions.',
					'agent-builder'
				) }{ ' ' }
				<a href={ interfaceUrl }>
					{ __(
						'Switch to Advanced in Interface settings',
						'agent-builder'
					) }
				</a>
				{ __(
					' if you want the full tool-by-tool list.',
					'agent-builder'
				) }
			</p>

			{ err && (
				<Notice status="error" isDismissible={ false }>
					{ err }
				</Notice>
			) }
			{ ok && (
				<Notice status="success" isDismissible>
					{ ok }
				</Notice>
			) }

			{ active === 'custom' && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Tools were customized outside a profile. Choose a card below to reset to a simple safety level.',
						'agent-builder'
					) }
				</Notice>
			) }

			<div className="agentic-react-profile-grid">
				{ profiles.map( ( p ) => (
					<button
						key={ p.id }
						type="button"
						className={
							'agentic-react-profile-card' +
							( p.active || active === p.id
								? ' is-active'
								: '' ) +
							( busy === p.id ? ' is-busy' : '' )
						}
						disabled={ !! busy }
						onClick={ () => apply( p.id ) }
					>
						<span className="agentic-react-profile-card__icon">
							{ p.icon || '•' }
						</span>
						<span className="agentic-react-profile-card__label">
							{ p.label }
						</span>
						<span className="agentic-react-profile-card__summary">
							{ p.summary }
						</span>
						<span className="agentic-react-profile-card__detail">
							{ p.detail }
						</span>
						<span className="agentic-react-profile-card__risk">
							<span
								className={
									'agentic-react-risk agentic-react-risk--' +
									( p.max_risk || 'low' )
								}
							>
								{ sprintf(
									/* translators: %s: risk level */
									__( 'Up to %s risk', 'agent-builder' ),
									p.max_risk || 'low'
								) }
							</span>
						</span>
						{ ( p.active || active === p.id ) && (
							<span className="agentic-react-profile-card__badge">
								{ __( 'Current', 'agent-builder' ) }
							</span>
						) }
					</button>
				) ) }
			</div>

			<p className="agentic-react-muted agentic-react-tools-basic__stats">
				{ sprintf(
					/* translators: 1: enabled tools, 2: disabled tools, 3: max risk */
					__(
						'Right now: %1$d tools on, %2$d off · highest enabled risk: %3$s',
						'agent-builder'
					),
					data.enabled_count ?? 0,
					data.disabled_count ?? 0,
					data.enabled_max_risk || 'none'
				) }
			</p>
		</div>
	);
}

function ToolsView( { data, reload, patchData } ) {
	const [ q, setQ ] = useState( '' );
	const [ riskFilter, setRiskFilter ] = useState( 'all' );
	const [ busy, setBusy ] = useState( '' );
	const [ err, setErr ] = useState( '' );
	const activeTab = data.tab || 'all';
	const isCustom = activeTab === 'custom';
	const isAdvanced = !! data.is_advanced;
	const interfaceUrl =
		data.interface_url ||
		'admin.php?page=agentic-settings&tab=interface';
	const categoryHref = ( slug ) => {
		const found = ( data.tabs || [] ).find( ( t ) => t.id === slug );
		return (
			found?.url ||
			`admin.php?page=agentic-tools&tab=${ encodeURIComponent( slug ) }`
		);
	};

	// Basic Interface mode: simple ability profiles only (not the tool table).
	if ( ! isAdvanced ) {
		return (
			<ToolsBasicProfiles data={ data } reload={ reload } />
		);
	}

	const riskCounts = useMemo( () => {
		const counts = {
			all: 0,
			none: 0,
			low: 0,
			medium: 0,
			high: 0,
			extreme: 0,
		};
		( data.rows || [] ).forEach( ( r ) => {
			const risk = ( r.risk_level || 'none' ).toLowerCase();
			counts.all += 1;
			if ( counts[ risk ] !== undefined ) {
				counts[ risk ] += 1;
			} else {
				counts.none += 1;
			}
		} );
		return counts;
	}, [ data.rows ] );

	const riskFilters = useMemo(
		() => [
			{ id: 'all', label: __( 'All risks', 'agent-builder' ) },
			{ id: 'none', label: __( 'None', 'agent-builder' ) },
			{ id: 'low', label: __( 'Low', 'agent-builder' ) },
			{ id: 'medium', label: __( 'Medium', 'agent-builder' ) },
			{ id: 'high', label: __( 'High', 'agent-builder' ) },
			{ id: 'extreme', label: __( 'Extreme', 'agent-builder' ) },
		],
		[]
	);

	const rows = useMemo( () => {
		let list = data.rows || [];
		if ( riskFilter !== 'all' ) {
			list = list.filter( ( r ) => {
				const risk = ( r.risk_level || 'none' ).toLowerCase();
				return risk === riskFilter;
			} );
		}
		const n = q.trim().toLowerCase();
		if ( ! n ) {
			return list;
		}
		return list.filter( ( r ) =>
			[
				r.title,
				r.subtitle,
				r.category,
				r.category_label,
				r.id,
				r.risk_level,
			]
				.join( ' ' )
				.toLowerCase()
				.includes( n )
		);
	}, [ data.rows, q, riskFilter ] );

	const setRowEnabled = ( name, enabled ) => {
		if ( typeof patchData === 'function' ) {
			patchData( ( d ) => ( {
				...d,
				rows: ( d.rows || [] ).map( ( r ) =>
					r.id === name ? { ...r, enabled } : r
				),
			} ) );
		}
	};

	const toggle = ( name, enabled ) => {
		setBusy( name );
		setErr( '' );
		// Optimistic: flip the switch without a full-page reload/spinner.
		setRowEnabled( name, enabled );
		apiFetch( {
			path: 'agentic/v1/admin-page',
			method: 'POST',
			data: {
				action_name: 'toggle_tool',
				name,
				enabled,
			},
		} )
			.then( () => {
				// Background refresh keeps counts/tabs in sync; silent = no jump.
				if ( typeof reload === 'function' ) {
					reload( { silent: true } );
				}
			} )
			.catch( ( e ) => {
				setRowEnabled( name, ! enabled );
				setErr(
					e.message || __( 'Toggle failed.', 'agent-builder' )
				);
			} )
			.finally( () => setBusy( '' ) );
	};

	return (
		<>
			<TabBar tabs={ data.tabs } active={ activeTab } className="agentic-react-tabs--tools" />
			{ err && (
				<Notice status="error" isDismissible={ false }>
					{ err }
				</Notice>
			) }

			{ isCustom && (
				<SiteLocalToolsPanel
					siteLocal={ data.site_local || {} }
					reload={ reload }
				/>
			) }

			{ ! isCustom && (
				<>
					<p className="agentic-react-muted" style={ { marginTop: 0 } }>
						<a href={ interfaceUrl }>
							{ __(
								'Interface settings',
								'agent-builder'
							) }
						</a>
						{ ' · ' }
						{ __(
							'You are in Advanced view (full tool list). Switch to Basic there for simple safety profiles.',
							'agent-builder'
						) }
					</p>
					<div className="agentic-react-tools-toolbar">
						<SearchControl
							value={ q }
							onChange={ setQ }
							placeholder={ __( 'Search tools…', 'agent-builder' ) }
							__nextHasNoMarginBottom
						/>
						<span className="agentic-react-muted">
							{ rows.length === 1
								? __( '1 tool', 'agent-builder' )
								: `${ rows.length } ${ __(
										'tools',
										'agent-builder'
								  ) }` }
						</span>
						<a
							className="components-button is-secondary"
							href="admin.php?page=agentic-tools&tab=custom"
						>
							{ __( 'Custom tools (Pro)', 'agent-builder' ) }
						</a>
					</div>
					<nav
						className="agentic-react-risk-filters"
						aria-label={ __( 'Filter by risk', 'agent-builder' ) }
					>
						{ riskFilters.map( ( f ) => {
							const count = riskCounts[ f.id ] ?? 0;
							// Hide empty risk levels except "all" and the active filter.
							if (
								f.id !== 'all' &&
								f.id !== riskFilter &&
								count === 0
							) {
								return null;
							}
							return (
								<button
									key={ f.id }
									type="button"
									className={
										'agentic-react-risk-filters__btn' +
										( f.id !== 'all'
											? ` agentic-react-risk--${ f.id }`
											: '' ) +
										( riskFilter === f.id
											? ' is-active'
											: '' )
									}
									onClick={ () => setRiskFilter( f.id ) }
									aria-pressed={ riskFilter === f.id }
								>
									{ f.label }
									<span className="agentic-react-risk-filters__count">
										{ count }
									</span>
								</button>
							);
						} ) }
					</nav>
					{ ! rows.length ? (
						<p className="agentic-react-muted">
							{ q || riskFilter !== 'all'
								? __(
										'No tools match this search or risk filter.',
										'agent-builder'
								  )
								: __(
										'No tools in this category.',
										'agent-builder'
								  ) }
						</p>
					) : (
						<div className="agentic-react-table-wrap">
							<table className="agentic-react-table">
								<thead>
									<tr>
										<th>
											{ __( 'Enabled', 'agent-builder' ) }
										</th>
										<th>{ __( 'Tool', 'agent-builder' ) }</th>
										{ activeTab === 'all' && (
											<th>
												{ __(
													'Category',
													'agent-builder'
												) }
											</th>
										) }
										<th>
											{ __( 'Risk', 'agent-builder' ) }
										</th>
										<th>
											{ __( 'Source', 'agent-builder' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ rows.map( ( r ) => (
										<tr
											key={ r.id }
											style={ {
												opacity: r.enabled ? 1 : 0.6,
											} }
										>
											<td style={ { width: 90 } }>
												<ToggleControl
													checked={ !! r.enabled }
													disabled={ busy === r.id }
													onChange={ ( v ) =>
														toggle( r.id, v )
													}
													__nextHasNoMarginBottom
												/>
											</td>
											<td>
												<strong>{ r.title }</strong>
												{ r.subtitle && (
													<div className="agentic-react-muted">
														{ r.subtitle.slice(
															0,
															140
														) }
														{ r.subtitle.length >
														140
															? '…'
															: '' }
													</div>
												) }
											</td>
											{ activeTab === 'all' && (
												<td>
													<a
														href={ categoryHref(
															r.category
														) }
													>
														{ r.category_label ||
															r.category }
													</a>
												</td>
											) }
											<td>
												<span
													className={
														'agentic-react-risk agentic-react-risk--' +
														( r.risk_level ||
															'none' )
													}
												>
													{ r.risk_level ||
														__(
															'none',
															'agent-builder'
														) }
												</span>
											</td>
											<td>{ r.source }</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</>
			) }
		</>
	);
}

function SkillsView( { data, reload } ) {
	const [ q, setQ ] = useState( '' );
	const [ err, setErr ] = useState( '' );
	const rows = useMemo( () => {
		const all = data.rows || [];
		const n = q.trim().toLowerCase();
		if ( ! n ) {
			return all;
		}
		return all.filter( ( r ) =>
			[ r.title, r.subtitle, r.agent ]
				.join( ' ' )
				.toLowerCase()
				.includes( n )
		);
	}, [ data.rows, q ] );

	const remove = ( id ) => {
		if (
			! window.confirm(
				__( 'Delete this skill? This cannot be undone.', 'agent-builder' )
			)
		) {
			return;
		}
		setErr( '' );
		apiFetch( {
			path: 'agentic/v1/admin-page',
			method: 'POST',
			data: { action_name: 'delete_skill', id },
		} )
			.then( () => reload() )
			.catch( ( e ) =>
				setErr( e.message || __( 'Delete failed.', 'agent-builder' ) )
			);
	};

	return (
		<>
			{ err && (
				<Notice status="error" isDismissible={ false }>
					{ err }
				</Notice>
			) }
			<div
				style={ {
					display: 'flex',
					gap: 8,
					flexWrap: 'wrap',
					marginBottom: 16,
				} }
			>
				{ ( data.actions || [] ).map( ( a ) => (
					<Button
						key={ a.url }
						variant={ a.primary ? 'primary' : 'secondary' }
						href={ a.url }
					>
						{ a.label }
					</Button>
				) ) }
			</div>
			<div style={ { marginBottom: 16 } }>
				<SearchControl
					value={ q }
					onChange={ setQ }
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="agentic-react-table-wrap">
				<table className="agentic-react-table">
					<thead>
						<tr>
							<th>{ __( 'Skill', 'agent-builder' ) }</th>
							<th>{ __( 'Agent', 'agent-builder' ) }</th>
							<th>{ __( 'Version', 'agent-builder' ) }</th>
							<th>{ __( 'Actions', 'agent-builder' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( r ) => (
							<tr key={ r.id }>
								<td>
									<strong>{ r.title }</strong>
									{ r.subtitle && (
										<div className="agentic-react-muted">
											{ r.subtitle }
										</div>
									) }
								</td>
								<td>
									<code>{ r.agent || '—' }</code>
								</td>
								<td>{ r.version || '—' }</td>
								<td>
									<a href={ r.edit_url }>
										{ __( 'Edit', 'agent-builder' ) }
									</a>
									{ ' · ' }
									<button
										type="button"
										className="button-link"
										onClick={ () =>
											remove( r.delete_id )
										}
									>
										{ __( 'Delete', 'agent-builder' ) }
									</button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</>
	);
}

function ApprovalsView( { data, reload } ) {
	const rows = data.rows || [];
	const prefs = data.prefs || {};
	const profiles = data.comfort_profiles || [];
	const interfaceUrl =
		data.interface_url ||
		'admin.php?page=agentic-settings&tab=interface';
	const [ busy, setBusy ] = useState( '' );
	const [ err, setErr ] = useState( '' );
	const [ ok, setOk ] = useState( '' );
	/** @type {[{id:string,phase:string,title:string,steps:Array,message:string,ok:boolean}|null, Function]} */
	const [ progress, setProgress ] = useState( null );
	const [ emailNotify, setEmailNotify ] = useState(
		!! prefs.email_notify
	);
	const [ emailTo, setEmailTo ] = useState( prefs.email_to || '' );
	const [ riskAck, setRiskAck ] = useState( !! prefs.risk_ack );
	const [ comfort, setComfort ] = useState(
		prefs.comfort || 'careful'
	);

	const decide = ( row, action ) => {
		const id = row.id;
		const title = row.title || __( 'Action', 'agent-builder' );
		setBusy( `d-${ id }` );
		setErr( '' );
		setOk( '' );

		if ( action === 'approve' ) {
			setProgress( {
				id,
				phase: 'approving',
				title,
				ok: true,
				message: '',
				steps: [
					{
						key: 'approve',
						label: __( 'Recording your approval…', 'agent-builder' ),
						state: 'active',
					},
					{
						key: 'run',
						label: __( 'Running the approved action…', 'agent-builder' ),
						state: 'pending',
					},
					{
						key: 'done',
						label: __( 'Confirming result…', 'agent-builder' ),
						state: 'pending',
					},
				],
			} );
		} else {
			setProgress( {
				id,
				phase: 'rejecting',
				title,
				ok: true,
				message: '',
				steps: [
					{
						key: 'reject',
						label: __( 'Rejecting this request…', 'agent-builder' ),
						state: 'active',
					},
				],
			} );
		}

		apiFetch( {
			path: 'agentic/v1/admin-page',
			method: 'POST',
			data: {
				action_name: 'approval_decide',
				id,
				decide: action,
			},
		} )
			.then( ( res ) => {
				// Flatten possible envelopes: {…fields}, {data:{…}}, {data:{data:{…}}}
				let payload = res || {};
				if ( payload.data && typeof payload.data === 'object' ) {
					payload = { ...payload, ...payload.data };
					if ( payload.data && typeof payload.data === 'object' ) {
						payload = { ...payload, ...payload.data };
					}
				}
				const exec = payload.execution || null;
				const baseMsg = payload.message || '';

				if ( action === 'reject' ) {
					setProgress( {
						id,
						phase: 'done',
						title,
						ok: true,
						message:
							baseMsg ||
							__(
								'Rejected — the action will not run.',
								'agent-builder'
							),
						steps: [
							{
								key: 'reject',
								label: __(
									'Request rejected',
									'agent-builder'
								),
								state: 'done',
							},
						],
					} );
					setOk(
						baseMsg ||
							__(
								'Rejected — the action will not run.',
								'agent-builder'
							)
					);
				} else {
					const ran = exec?.ran !== false;
					const success =
						exec == null
							? true
							: !! exec.success;
					const runLabel = ! ran
						? exec?.message ||
						  __(
								'Approved, but the action could not be started.',
								'agent-builder'
						  )
						: success
						? exec?.message ||
						  __(
								'Action completed successfully.',
								'agent-builder'
						  )
						: exec?.message ||
						  __(
								'Action ran but reported a problem.',
								'agent-builder'
						  );
					const detail = exec?.detail
						? String( exec.detail )
						: '';

					setProgress( {
						id,
						phase: 'done',
						title,
						ok: success && ran,
						message: [ baseMsg, runLabel, detail ]
							.filter( Boolean )
							.join( ' ' ),
						steps: [
							{
								key: 'approve',
								label: __(
									'Approval recorded',
									'agent-builder'
								),
								state: 'done',
							},
							{
								key: 'run',
								label: runLabel,
								state: ran
									? success
										? 'done'
										: 'error'
									: 'error',
							},
							{
								key: 'done',
								label: success
									? __(
											'Done — assistant task finished',
											'agent-builder'
									  )
									: __(
											'Finished with errors — check detail below',
											'agent-builder'
									  ),
								state: success ? 'done' : 'error',
							},
						],
					} );
					setOk(
						success
							? baseMsg ||
									runLabel ||
									__(
										'Approved and completed.',
										'agent-builder'
									)
							: runLabel
					);
				}
				reload( { silent: true } );
			} )
			.catch( ( e ) => {
				const msg =
					e.message ||
					__( 'Could not update that item.', 'agent-builder' );
				setErr( msg );
				setProgress( {
					id,
					phase: 'done',
					title,
					ok: false,
					message: msg,
					steps: [
						{
							key: 'fail',
							label: msg,
							state: 'error',
						},
					],
				} );
			} )
			.finally( () => setBusy( '' ) );
	};

	const savePrefs = ( nextComfort ) => {
		const c = nextComfort || comfort;
		const profile = profiles.find( ( p ) => p.id === c );
		if ( profile?.needs_ack && ! riskAck ) {
			setErr(
				__(
					'Check the box to accept the increased risk before choosing “Trust more”.',
					'agent-builder'
				)
			);
			return;
		}
		setBusy( 'prefs' );
		setErr( '' );
		setOk( '' );
		apiFetch( {
			path: 'agentic/v1/admin-page',
			method: 'POST',
			data: {
				action_name: 'save_approval_prefs',
				email_notify: emailNotify,
				email_to: emailTo,
				comfort: c,
				risk_ack: riskAck,
			},
		} )
			.then( () => {
				setComfort( c );
				setOk( __( 'Preferences saved.', 'agent-builder' ) );
				reload( { silent: true } );
			} )
			.catch( ( e ) =>
				setErr(
					e.message ||
						__( 'Could not save preferences.', 'agent-builder' )
				)
			)
			.finally( () => setBusy( '' ) );
	};

	return (
		<>
			<p className="agentic-react-lead">{ data.description }</p>
			<p className="agentic-react-muted" style={ { marginTop: 0 } }>
				<a href={ interfaceUrl }>
					{ __( 'Interface settings', 'agent-builder' ) }
				</a>
				{ ' · ' }
				{ data.is_advanced
					? __(
							'Advanced view shows technical detail. Basic mode keeps this page simpler.',
							'agent-builder'
					  )
					: __(
							'Simple view for non-technical admins. Switch to Advanced for more detail.',
							'agent-builder'
					  ) }
			</p>

			{ err && (
				<Notice status="error" isDismissible={ false }>
					{ err }
				</Notice>
			) }
			{ ok && ! progress && (
				<Notice status="success" isDismissible>
					{ ok }
				</Notice>
			) }

			{ progress && (
				<div
					className={
						'agentic-react-approval-progress' +
						( progress.phase === 'done'
							? progress.ok
								? ' is-success'
								: ' is-error'
							: ' is-running' )
					}
					role="status"
					aria-live="polite"
				>
					<div className="agentic-react-approval-progress__head">
						{ progress.phase !== 'done' && (
							<Spinner />
						) }
						<strong>
							{ progress.phase === 'done'
								? progress.ok
									? __( 'Complete', 'agent-builder' )
									: __( 'Needs attention', 'agent-builder' )
								: __( 'Working…', 'agent-builder' ) }
							{ progress.title
								? ` — ${ progress.title }`
								: '' }
						</strong>
						{ progress.phase === 'done' && (
							<button
								type="button"
								className="button-link"
								onClick={ () => setProgress( null ) }
							>
								{ __( 'Dismiss', 'agent-builder' ) }
							</button>
						) }
					</div>
					<ol className="agentic-react-approval-progress__steps">
						{ ( progress.steps || [] ).map( ( s ) => (
							<li
								key={ s.key }
								className={
									'agentic-react-approval-progress__step is-' +
									( s.state || 'pending' )
								}
							>
								<span className="agentic-react-approval-progress__dot" />
								<span>{ s.label }</span>
							</li>
						) ) }
					</ol>
					{ progress.message && progress.phase === 'done' && (
						<p className="agentic-react-approval-progress__msg">
							{ progress.message }
						</p>
					) }
				</div>
			) }

			{ /* —— Preferences —— */ }
			<div className="agentic-react-approvals-prefs">
				<h3>{ __( 'Preferences', 'agent-builder' ) }</h3>
				<p className="agentic-react-muted">
					{ __(
						'Choose how closely you want to watch assistants, and whether we should email you when something is waiting.',
						'agent-builder'
					) }
				</p>

				<div className="agentic-react-profile-grid agentic-react-profile-grid--approvals">
					{ profiles.map( ( p ) => (
						<button
							key={ p.id }
							type="button"
							className={
								'agentic-react-profile-card' +
								( comfort === p.id || p.active
									? ' is-active'
									: '' )
							}
							disabled={ busy === 'prefs' }
							onClick={ () => {
								setComfort( p.id );
								if ( ! p.needs_ack ) {
									// Auto-save safer profiles immediately.
									setTimeout( () => savePrefs( p.id ), 0 );
								}
							} }
						>
							<span className="agentic-react-profile-card__icon">
								{ p.icon }
							</span>
							<span className="agentic-react-profile-card__label">
								{ p.label }
							</span>
							<span className="agentic-react-profile-card__summary">
								{ p.summary }
							</span>
							<span className="agentic-react-profile-card__detail">
								{ p.detail }
							</span>
							<span className="agentic-react-muted">
								{ p.risk_note }
							</span>
							{ ( comfort === p.id || p.active ) && (
								<span className="agentic-react-profile-card__badge">
									{ __( 'Current', 'agent-builder' ) }
								</span>
							) }
						</button>
					) ) }
				</div>

				{ comfort === 'hands_off' && (
					<label className="agentic-react-ack">
						<input
							type="checkbox"
							checked={ riskAck }
							onChange={ ( e ) =>
								setRiskAck( e.target.checked )
							}
						/>
						<span>
							{ __(
								'I understand assistants may change my site with less waiting, and I accept that increased risk.',
								'agent-builder'
							) }
						</span>
					</label>
				) }

				{ comfort === 'hands_off' && (
					<p>
						<Button
							variant="primary"
							disabled={ busy === 'prefs' || ! riskAck }
							onClick={ () => savePrefs( 'hands_off' ) }
						>
							{ __(
								'Save “Trust more” preference',
								'agent-builder'
							) }
						</Button>
					</p>
				) }

				<div className="agentic-react-email-prefs">
					<label className="agentic-react-ack">
						<input
							type="checkbox"
							checked={ emailNotify }
							onChange={ ( e ) =>
								setEmailNotify( e.target.checked )
							}
						/>
						<span>
							{ __(
								'Email me when an action is waiting for approval',
								'agent-builder'
							) }
						</span>
					</label>
					{ emailNotify && (
						<label className="agentic-react-form-full">
							<span>
								{ __( 'Send alerts to', 'agent-builder' ) }
							</span>
							<input
								type="email"
								value={ emailTo }
								onChange={ ( e ) =>
									setEmailTo( e.target.value )
								}
								placeholder="you@example.com"
							/>
						</label>
					) }
					<p>
						<Button
							variant="secondary"
							disabled={ busy === 'prefs' }
							onClick={ () => savePrefs( comfort ) }
						>
							{ __( 'Save email preferences', 'agent-builder' ) }
						</Button>
					</p>
				</div>
			</div>

			{ /* —— Queue —— */ }
			<h3>
				{ __( 'Waiting for you', 'agent-builder' ) }
				{ data.pending_count
					? ` (${ data.pending_count })`
					: '' }
			</h3>

			{ ! rows.length ? (
				<div className="agentic-react-approvals-empty">
					<p>
						<strong>
							{ __( 'You’re all caught up', 'agent-builder' ) }
						</strong>
					</p>
					<p className="agentic-react-muted">
						{ __(
							'Nothing is waiting. When an assistant needs permission for a bigger change, it will show up here',
							'agent-builder'
						) }
						{ emailNotify
							? __(
									' — and we’ll email you.',
									'agent-builder'
							  )
							: '.' }
					</p>
				</div>
			) : (
				<div className="agentic-react-approval-list">
					{ rows.map( ( r ) => (
						<div
							key={ r.id }
							className="agentic-react-approval-card"
						>
							<div className="agentic-react-approval-card__main">
								<strong>{ r.title }</strong>
								<span
									className={
										'agentic-react-risk agentic-react-risk--' +
										( r.risk_level || 'high' )
									}
								>
									{ r.risk_level || 'high' }
								</span>
								<div className="agentic-react-muted">
									{ r.subtitle
										? `${ r.subtitle } · `
										: '' }
									{ r.created_at }
								</div>
								{ r.summary && (
									<p className="agentic-react-approval-card__summary">
										{ String( r.summary ).slice( 0, 200 ) }
										{ String( r.summary ).length > 200
											? '…'
											: '' }
									</p>
								) }
							</div>
							<div className="agentic-react-approval-card__actions">
								<Button
									variant="primary"
									disabled={ !! busy }
									onClick={ () =>
										decide( r, 'approve' )
									}
								>
									{ busy === `d-${ r.id }`
										? __( 'Working…', 'agent-builder' )
										: __( 'Approve', 'agent-builder' ) }
								</Button>
								<Button
									variant="secondary"
									isDestructive
									disabled={ !! busy }
									onClick={ () =>
										decide( r, 'reject' )
									}
								>
									{ __( 'Reject', 'agent-builder' ) }
								</Button>
							</div>
						</div>
					) ) }
				</div>
			) }

			{ data.is_advanced && (
				<p className="agentic-react-muted" style={ { marginTop: 16 } }>
					{ __(
						'Need batch approve or backup restore tools?',
						'agent-builder'
					) }{ ' ' }
					<a href={ data.classic_url || data.tabs?.[ 0 ]?.url }>
						{ __( 'Open classic queue / backups', 'agent-builder' ) }
					</a>
				</p>
			) }
		</>
	);
}

function LogsView( { data, reload } ) {
	const [ q, setQ ] = useState( '' );
	const [ kind, setKind ] = useState( 'all' );
	const period = data.period || 'week';
	const stats = data.stats || {};
	const interfaceUrl =
		data.interface_url ||
		'admin.php?page=agentic-settings&tab=interface';

	const kindCounts = useMemo( () => {
		const c = { all: 0, tool: 0, approval: 0, chat: 0, settings: 0, other: 0, security: 0 };
		( data.rows || [] ).forEach( ( r ) => {
			const k = r.kind || 'other';
			c.all += 1;
			if ( c[ k ] !== undefined ) {
				c[ k ] += 1;
			} else {
				c.other += 1;
			}
		} );
		return c;
	}, [ data.rows ] );

	const rows = useMemo( () => {
		let list = data.rows || [];
		if ( kind !== 'all' ) {
			list = list.filter( ( r ) => ( r.kind || 'other' ) === kind );
		}
		const n = q.trim().toLowerCase();
		if ( ! n ) {
			return list;
		}
		return list.filter( ( r ) =>
			[ r.title, r.subtitle, r.detail, r.raw_action, r.kind ]
				.join( ' ' )
				.toLowerCase()
				.includes( n )
		);
	}, [ data.rows, kind, q ] );

	const setPeriod = ( p ) => {
		const url = new URL( window.location.href );
		url.searchParams.set( 'period', p );
		window.history.replaceState( {}, '', url.toString() );
		reload( { period: p } );
	};

	return (
		<>
			<p className="agentic-react-lead">{ data.description }</p>
			<p className="agentic-react-muted" style={ { marginTop: 0 } }>
				<a href={ interfaceUrl }>
					{ __( 'Interface settings', 'agent-builder' ) }
				</a>
				{ ' · ' }
				{ __(
					'This is a friendly activity feed. Technical names stay in Advanced detail when useful.',
					'agent-builder'
				) }
			</p>

			{ /* Summary metrics */ }
			<div className="agentic-react-activity-stats">
				<div className="agentic-react-activity-stat">
					<span className="agentic-react-activity-stat__val">
						{ stats.total ?? rows.length }
					</span>
					<span className="agentic-react-activity-stat__lbl">
						{ __( 'Events', 'agent-builder' ) }
					</span>
				</div>
				{ data.tab === 'audit' && (
					<>
						<div className="agentic-react-activity-stat">
							<span className="agentic-react-activity-stat__val">
								{ stats.tools ?? 0 }
							</span>
							<span className="agentic-react-activity-stat__lbl">
								{ __( 'Tools used', 'agent-builder' ) }
							</span>
						</div>
						<div className="agentic-react-activity-stat">
							<span className="agentic-react-activity-stat__val">
								{ stats.approvals ?? 0 }
							</span>
							<span className="agentic-react-activity-stat__lbl">
								{ __( 'Approvals', 'agent-builder' ) }
							</span>
						</div>
						{ Number( stats.tokens ) > 0 && (
							<div className="agentic-react-activity-stat">
								<span className="agentic-react-activity-stat__val">
									{ Number( stats.tokens ).toLocaleString() }
								</span>
								<span className="agentic-react-activity-stat__lbl">
									{ __( 'Tokens', 'agent-builder' ) }
								</span>
							</div>
						) }
					</>
				) }
			</div>

			<TabBar tabs={ data.tabs } active={ data.tab } />

			{ /* Period */ }
			<nav
				className="agentic-react-risk-filters"
				aria-label={ __( 'Time period', 'agent-builder' ) }
			>
				{ ( data.period_options || [] ).map( ( p ) => (
					<button
						key={ p.id }
						type="button"
						className={
							'agentic-react-risk-filters__btn' +
							( period === p.id ? ' is-active' : '' )
						}
						onClick={ () => setPeriod( p.id ) }
						aria-pressed={ period === p.id }
					>
						{ p.label }
					</button>
				) ) }
			</nav>

			{ data.tab === 'audit' && (
				<nav
					className="agentic-react-risk-filters"
					aria-label={ __( 'Activity type', 'agent-builder' ) }
				>
					{ ( data.kind_filters || [] ).map( ( f ) => {
						const count = kindCounts[ f.id ] ?? 0;
						if ( f.id !== 'all' && f.id !== kind && count === 0 ) {
							return null;
						}
						return (
							<button
								key={ f.id }
								type="button"
								className={
									'agentic-react-risk-filters__btn' +
									( kind === f.id ? ' is-active' : '' )
								}
								onClick={ () => setKind( f.id ) }
								aria-pressed={ kind === f.id }
							>
								{ f.label }
								<span className="agentic-react-risk-filters__count">
									{ count }
								</span>
							</button>
						);
					} ) }
				</nav>
			) }

			<div className="agentic-react-tools-toolbar">
				<SearchControl
					value={ q }
					onChange={ setQ }
					placeholder={ __( 'Search activity…', 'agent-builder' ) }
					__nextHasNoMarginBottom
				/>
				<span className="agentic-react-muted">
					{ rows.length === 1
						? __( '1 event', 'agent-builder' )
						: `${ rows.length } ${ __( 'events', 'agent-builder' ) }` }
				</span>
			</div>

			{ ! rows.length ? (
				<div className="agentic-react-approvals-empty">
					<p>
						<strong>
							{ __( 'Nothing here yet', 'agent-builder' ) }
						</strong>
					</p>
					<p className="agentic-react-muted">
						{ q || kind !== 'all'
							? __(
									'No events match this filter. Try another period or clear search.',
									'agent-builder'
							  )
							: __(
									'When assistants chat or use tools, their activity will show up here.',
									'agent-builder'
							  ) }
					</p>
				</div>
			) : (
				<ul className="agentic-react-activity-timeline">
					{ rows.map( ( r ) => (
						<li
							key={ r.id || r.when + r.title }
							className={
								'agentic-react-activity-item kind-' +
								( r.kind || 'other' )
							}
						>
							<span
								className="agentic-react-activity-item__icon"
								aria-hidden="true"
							>
								{ r.icon || '•' }
							</span>
							<div className="agentic-react-activity-item__body">
								<div className="agentic-react-activity-item__title">
									<strong>{ r.title }</strong>
									{ r.kind && (
										<span
											className={
												'agentic-react-activity-kind agentic-react-activity-kind--' +
												r.kind
											}
										>
											{ r.kind }
										</span>
									) }
								</div>
								<div className="agentic-react-muted">
									{ r.subtitle ? `${ r.subtitle } · ` : '' }
									{ r.when_human || r.when }
								</div>
								{ r.detail && (
									<p className="agentic-react-activity-item__detail">
										{ r.detail }
									</p>
								) }
								{ data.is_advanced && r.raw_action && (
									<code className="agentic-react-activity-item__raw">
										{ r.raw_action }
									</code>
								) }
							</div>
						</li>
					) ) }
				</ul>
			) }
		</>
	);
}

function DeploymentView( { data } ) {
	return (
		<>
			<p className="agentic-react-lead">{ data.description }</p>
			<div
				style={ {
					display: 'grid',
					gap: 12,
					gridTemplateColumns:
						'repeat(auto-fill, minmax(220px, 1fr))',
				} }
			>
				{ ( data.links || [] ).map( ( link ) => (
					<a
						key={ link.url }
						href={ link.url }
						className="agentic-react-panel components-card"
						style={ {
							display: 'block',
							padding: 16,
							textDecoration: 'none',
							color: 'inherit',
							border: '1px solid #e0e0e0',
							borderRadius: 8,
						} }
					>
						<strong style={ { color: '#2271b1' } }>
							{ link.label }
						</strong>
						<div className="agentic-react-muted">{ link.hint }</div>
					</a>
				) ) }
			</div>
			{ data.legacy_note && (
				<p className="agentic-react-muted" style={ { marginTop: 16 } }>
					{ data.legacy_note }
				</p>
			) }
		</>
	);
}

function UpgradeView( { data } ) {
	return (
		<>
			<p className="agentic-react-lead">{ data.description }</p>
			<ul style={ { marginTop: 0 } }>
				{ ( data.features || [] ).map( ( f ) => (
					<li key={ f }>{ f }</li>
				) ) }
			</ul>
			{ data.is_pro ? (
				<p>
					<span className="agentic-react-badge">
						{ __( 'Pro active', 'agent-builder' ) }
					</span>
				</p>
			) : (
				<p>
					<Button variant="primary" href={ data.pricing_url }>
						{ __( 'View pricing', 'agent-builder' ) }
					</Button>
				</p>
			) }
		</>
	);
}

function TrainView( { data } ) {
	const concepts = data.concepts || [];
	return (
		<>
			<TabBar tabs={ data.tabs } active={ data.tab } />
			<p className="agentic-react-lead">{ data.description }</p>
			<p>
				<Button variant="primary" href={ data.manage_url }>
					{ __( 'Open full Knowledge editor', 'agent-builder' ) }
				</Button>
			</p>
			<div className="agentic-react-table-wrap">
				<table className="agentic-react-table">
					<thead>
						<tr>
							<th>{ __( 'Concept', 'agent-builder' ) }</th>
							<th>{ __( 'Type', 'agent-builder' ) }</th>
							<th>{ __( 'Status', 'agent-builder' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ concepts.map( ( c ) => (
							<tr key={ c.id }>
								<td>
									<strong>{ c.title }</strong>
									{ c.example && (
										<span className="agentic-react-muted">
											{ ' ' }
											(example)
										</span>
									) }
								</td>
								<td>
									<code>{ c.type || '—' }</code>
								</td>
								<td>{ c.status || '—' }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</>
	);
}

function AdminPagesApp() {
	const cfg = bootConfig();
	const page = cfg.page || 'tools';
	const tab = cfg.tab || '';
	const [ state, reload, , patchData ] = usePageData( page, tab );

	const footer = cfg.footer || {};

	if ( state.loading ) {
		return (
			<div className="agentic-admin">
				<p>
					<Spinner /> { __( 'Loading…', 'agent-builder' ) }
				</p>
				<AdminPageFooter footer={ footer } />
			</div>
		);
	}

	if ( state.error || ! state.data ) {
		return (
			<div className="agentic-admin">
				<Notice status="error" isDismissible={ false }>
					{ state.error || __( 'Unavailable.', 'agent-builder' ) }
				</Notice>
				<AdminPageFooter footer={ footer } />
			</div>
		);
	}

	const data = state.data;
	// Prefer page-specific docs when payload includes them.
	const pageFooter = {
		...footer,
		...( data.docs_url
			? { doc_url: data.docs_url }
			: {} ),
		...( data.footer_policy
			? { policy: data.footer_policy }
			: {} ),
	};
	let body = null;
	switch ( data.page ) {
		case 'tools':
			body = (
				<ToolsView
					data={ data }
					reload={ reload }
					patchData={ patchData }
				/>
			);
			break;
		case 'skills':
			body = <SkillsView data={ data } reload={ reload } />;
			break;
		case 'approvals':
			body = <ApprovalsView data={ data } reload={ reload } />;
			break;
		case 'logs':
			body = <LogsView data={ data } reload={ reload } />;
			break;
		case 'deployment':
			body = <DeploymentView data={ data } />;
			break;
		case 'upgrade-pro':
			body = <UpgradeView data={ data } />;
			break;
		case 'train-data':
			body = <TrainView data={ data } />;
			break;
		default:
			body = (
				<p>{ __( 'Unknown page.', 'agent-builder' ) }</p>
			);
	}

	const panelTitle =
		data.panel_title || data.title || __( 'Tools', 'agent-builder' );

	// Outer .wrap is provided by PHP so the shared admin footer can attach.
	return (
		<div className="agentic-admin">
			<AdminPage
				title={ data.title }
				description={ data.description }
			>
				<Panel title={ panelTitle }>{ body }</Panel>
			</AdminPage>
			<AdminPageFooter footer={ pageFooter } />
		</div>
	);
}

document.addEventListener( 'DOMContentLoaded', () => {
	const el = document.getElementById( 'agentic-admin-pages-root' );
	if ( el ) {
		createRoot( el ).render( <AdminPagesApp /> );
	}
} );
