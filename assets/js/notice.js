/* Dismiss the Wbcom Demo Installer dashboard notice for this admin. */
( function () {
	'use strict';
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-wbcom-demo-dismiss]' );
		if ( ! btn || ! window.wbcomDemoNotice ) {
			return;
		}
		var notice = btn.closest( '[data-wbcom-demo-notice]' );
		var body = new FormData();
		body.append( 'action', 'wbcom_demo_dismiss' );
		body.append( '_ajax_nonce', window.wbcomDemoNotice.nonce );
		fetch( window.wbcomDemoNotice.ajax, { method: 'POST', body: body, credentials: 'same-origin' } );
		if ( notice ) {
			notice.remove();
		}
	} );
}() );
