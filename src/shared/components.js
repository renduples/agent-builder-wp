/**
 * Shared WordPress-components building blocks for Agent Builder admin.
 */
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	Button,
	Spinner,
	Notice,
	Flex,
	FlexItem,
	Tooltip,
} from '@wordpress/components';

/**
 * Page shell with optional title.
 */
export function AdminPage( { title, description, children, actions } ) {
	return (
		<div className="agentic-react-admin">
			{ ( title || actions ) && (
				<div className="agentic-react-admin__page-head">
					<div>
						{ title && (
							<h1 className="agentic-react-admin__title">
								{ title }
							</h1>
						) }
						{ description && (
							<p className="agentic-react-admin__desc">
								{ description }
							</p>
						) }
					</div>
					{ actions && (
						<div className="agentic-react-admin__actions">
							{ actions }
						</div>
					) }
				</div>
			) }
			{ children }
		</div>
	);
}

/**
 * White card panel matching Interface settings look.
 */
export function Panel( {
	title,
	children,
	footer,
	className = '',
	isLoading = false,
} ) {
	return (
		<Card className={ `agentic-react-panel ${ className }`.trim() }>
			{ title && (
				<CardHeader>
					<h2 className="agentic-react-panel__title">{ title }</h2>
				</CardHeader>
			) }
			<CardBody>
				{ isLoading ? (
					<p>
						<Spinner />{ ' ' }
						{ __( 'Loading…', 'agent-builder' ) }
					</p>
				) : (
					children
				) }
			</CardBody>
			{ footer && <CardFooter>{ footer }</CardFooter> }
		</Card>
	);
}

export function SaveBar( { onSave, saving, label } ) {
	return (
		<Flex justify="flex-start">
			<FlexItem>
				<Button
					variant="primary"
					onClick={ () => onSave() }
					isBusy={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'agent-builder' )
						: label || __( 'Save changes', 'agent-builder' ) }
				</Button>
			</FlexItem>
		</Flex>
	);
}

export function StatusNotice( { error, saved, onDismissSaved } ) {
	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ saved && ! error && (
				<Notice
					status="success"
					isDismissible
					onRemove={ onDismissSaved }
				>
					{ __( 'Settings saved.', 'agent-builder' ) }
				</Notice>
			) }
		</>
	);
}

export function Section( { title, children } ) {
	return (
		<div className="agentic-react-section">
			{ title && (
				<h3 className="agentic-react-section__title">{ title }</h3>
			) }
			{ children }
		</div>
	);
}

/**
 * Small inline "?" affordance showing a one-line plain-language explainer on
 * hover/focus. Renders nothing interactive when `text` is empty, so call
 * sites can pass it conditionally without littering conditionals.
 *
 * Content guideline for anyone adding a new call site: one short sentence,
 * plain language, answers "why does this exist / what happens if I change
 * it" rather than restating the label, kept under ~120 characters. Skip
 * InfoTip entirely on a control that already has its own `help` text —
 * it's for the many controls that currently have none.
 */
export function InfoTip( { text, children } ) {
	if ( ! text ) {
		return children || null;
	}
	return (
		<Tooltip text={ text }>
			{ children || (
				<button
					type="button"
					className="agentic-react-infotip"
					aria-label={ __( 'More info', 'agent-builder' ) }
				>
					?
				</button>
			) }
		</Tooltip>
	);
}
