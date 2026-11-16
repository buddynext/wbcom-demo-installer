/**
 * Editor preview for the "Demo tour" block: the server renders it, so the page shows the real cards.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	wp.blocks.registerBlockType( 'wbcom-demo/tour', {
		apiVersion: 3,
		title: wp.i18n.__( 'Demo tour', 'wbcom-demo-installer' ),
		icon: 'groups',
		category: 'widgets',
		supports: { html: false },
		edit: function () {
			return el( 'div', wp.blockEditor ? wp.blockEditor.useBlockProps() : {}, el( wp.serverSideRender, { block: 'wbcom-demo/tour' } ) );
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
