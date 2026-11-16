/**
 * Wbcom Demo Installer: tabs of the Demo tour block (Try the demo page).
 */
( function () {
	document.querySelectorAll( '.wdi-tour [role=tablist]' ).forEach( function ( list ) {
		var tabs = list.querySelectorAll( '[role=tab]' );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				tabs.forEach( function ( t ) {
					var on = t === tab;
					t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					document.getElementById( t.getAttribute( 'aria-controls' ) ).hidden = ! on;
				} );
			} );
		} );
	} );
}() );
