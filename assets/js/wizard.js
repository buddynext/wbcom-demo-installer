/**
 * Wbcom Demo Installer wizard.
 *
 * Set up: site check, starting point, products, home page, review, build, ready.
 * Finish: choose, confirm, run, report. Every write goes through /wbcom-demo/v1 and the same
 * step engine WP-CLI uses, so closing the tab never loses progress.
 */
( function () {
	'use strict';

	const { __, _n, sprintf } = wp.i18n;
	const api = ( path, data ) => wp.apiFetch( { path: '/wbcom-demo/v1' + path, method: data ? 'POST' : 'GET', data } );
	const cfg = window.wbcomDemo || {};
	const app = document.getElementById( 'wdi-app' );

	const esc = ( s ) => String( s ?? '' ).replace( /[&<>"']/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ] ) );
	// Arrows point the way you read, so they get wdi-i--dir and flip in the RTL stylesheet.
	const ic = ( id, cls = '' ) => `<svg class="wdi-i ${ 'left' === id || 'right' === id ? 'wdi-i--dir ' : '' }${ cls }" aria-hidden="true" focusable="false"><use href="#i-${ id }"></use></svg>`;
	const fmt = ( n ) => Number( n ).toLocaleString();

	const S = {
		site: null,
		status: null,
		view: 'check',
		recipe: '',
		include: new Set(),
		look: 'demo',
		mode: 'keep',
		custom: {},
		preview: null,
		ack: false,
		error: '',
		running: false,
		pick: null, // Catalog ids ticked to install; filled from the site check.
		describe: '', // The owner's sentence for the AI.
		ideas: null, // Ideas the AI suggested.
		brief: null, // The idea the owner picked; the build writes its text from it.
		thinking: false,
		installing: {}, // id => 'busy' | 'done' | error message.
	};

	const SETUP = [ __( 'Site check', 'wbcom-demo-installer' ), __( 'Starting point', 'wbcom-demo-installer' ), __( 'Products', 'wbcom-demo-installer' ), __( 'Home page', 'wbcom-demo-installer' ), __( 'Review', 'wbcom-demo-installer' ), __( 'Build', 'wbcom-demo-installer' ) ];
	const FINISH = [ __( 'Choose', 'wbcom-demo-installer' ), __( 'Confirm', 'wbcom-demo-installer' ), __( 'Finish', 'wbcom-demo-installer' ) ];

	function stepper( flow, i ) {
		return `<ol class="wdi-stepper">${ flow.map( ( s, k ) => `<li class="${ k < i ? 'is-done' : k === i ? 'is-now' : '' }"><span class="wdi-stepper__dot">${ k < i ? ic( 'check' ) : k + 1 }</span>${ esc( s ) }</li>` ).join( '' ) }</ol>
			<p class="wdi-stepper-mini">${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( 'Step %1$d of %2$d: %3$s', 'wbcom-demo-installer' ), i + 1, flow.length, flow[ i ] ) ) }</p>`;
	}

	function brand( sub ) {
		return `<header class="wdi-brand"><span class="wdi-brand__logo">${ ic( 'spark', 'wdi-i--lg' ) }</span><div><span class="wdi-brand__title">${ esc( __( 'Wbcom Demo Installer', 'wbcom-demo-installer' ) ) }</span><span class="wdi-brand__sub">${ esc( sub ) }</span></div><a class="wdi-link wdi-brand__exit" href="${ esc( cfg.dashboard ) }">${ esc( __( 'Exit to dashboard', 'wbcom-demo-installer' ) ) }</a></header>`;
	}

	function card( title, lede, body, foot ) {
		return `<section class="wdi-card"><div class="wdi-card__head"><h1>${ esc( title ) }</h1>${ lede ? `<p>${ esc( lede ) }</p>` : '' }</div><div class="wdi-card__body">${ body }</div>${ foot || '' }</section>`;
	}

	function foot( back, next, opts = {} ) {
		return `<div class="wdi-card__foot">${ opts.skip ? `<a class="wdi-link wdi-small" href="${ esc( cfg.dashboard ) }">${ esc( opts.skip ) }</a>` : '' }<span class="wdi-spacer"></span>${ back ? `<button type="button" class="wdi-btn" data-go="${ back }">${ ic( 'left' ) }${ esc( __( 'Back', 'wbcom-demo-installer' ) ) }</button>` : '' }${ next ? `<button type="button" class="wdi-btn ${ opts.danger ? 'wdi-btn--danger' : 'wdi-btn--primary' }" data-go="${ next }" ${ opts.disabled ? 'disabled' : '' }>${ esc( opts.label || __( 'Continue', 'wbcom-demo-installer' ) ) }${ opts.danger ? '' : ic( 'right' ) }</button>` : '' }</div>`;
	}

	const thumb = ( shape ) => `<div class="wdi-thumb" aria-hidden="true"><div class="wdi-thumb__bar"></div><div class="wdi-thumb__body"><div class="wdi-thumb__col"><div class="wdi-thumb__blk ${ shape[ 0 ] }"></div><div class="wdi-thumb__blk"></div></div><div class="wdi-thumb__col"><div class="wdi-thumb__blk ${ shape[ 1 ] }"></div><div class="wdi-thumb__blk ${ shape[ 2 ] }"></div></div></div></div>`;
	const SHAPES = { community: [ 'is-acc', '', '' ], learning: [ '', 'is-acc', 'is-acc' ], events: [ 'is-acc', 'is-acc', '' ], jobs: [ '', '', 'is-acc' ], directory: [ 'is-acc', '', 'is-acc' ], services: [ '', 'is-acc', '' ], everything: [ 'is-acc', 'is-acc', 'is-acc' ] };

	const products = () => ( S.site ? S.site.products : [] );
	const product = ( id ) => products().find( ( p ) => p.id === id );

	function totals() {
		const t = {};
		t[ __( 'sample people', 'wbcom-demo-installer' ) ] = S.site.people;
		products().filter( ( p ) => S.include.has( p.id ) ).forEach( ( p ) => {
			Object.values( p.plan ).forEach( ( [ n, , label ] ) => {
				if ( n > 0 ) {
					t[ label ] = ( t[ label ] || 0 ) + n;
				}
			} );
		} );
		return t;
	}

	/* ---------- Set up ---------- */

	const SPARK = '<svg class="wdi-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3v3M12 18v3M3 12h3M18 12h3M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"></path><circle cx="12" cy="12" r="3"></circle></svg>';

	// AI through WordPress core connectors: describe the site and get three ideas, or a pointer to Connectors.
	function aiPanel() {
		const ai = S.site.ai || {};
		if ( ! ai.available ) {
			return ai.url && ai.manage ? `<div class="wdi-ai wdi-ai--connect"><span class="wdi-ai__ic">${ SPARK }</span><div><b>${ esc( __( 'Describe your site in your own words', 'wbcom-demo-installer' ) ) }</b><span class="wdi-muted">${ esc( __( 'Connect Anthropic, Google or OpenAI in Settings > Connectors with your own key. Then write one sentence about your site and get three ready ideas to build from.', 'wbcom-demo-installer' ) ) }</span><a class="wdi-link" href="${ esc( ai.url ) }">${ esc( __( 'Open Settings > Connectors', 'wbcom-demo-installer' ) ) }</a></div></div>` : '';
		}
		const tries = [ __( 'A yoga studio with weekly classes, an online beginners course and a members forum', 'wbcom-demo-installer' ), __( 'A directory of local farmers and food stalls with reviews and weekend market days', 'wbcom-demo-installer' ), __( 'A customer community for our app with support forums, tutorials and a public roadmap', 'wbcom-demo-installer' ) ];
		return `<div class="wdi-ai">
			<div class="wdi-ai__head"><b>${ esc( __( 'Describe your site', 'wbcom-demo-installer' ) ) }</b></div>
			<label for="wdi-describe" class="wdi-muted wdi-small">${ esc( __( 'Who is it for and what do people do there? One or two sentences is enough.', 'wbcom-demo-installer' ) ) }</label>
			<textarea id="wdi-describe" class="wdi-ai__text" rows="3" maxlength="600">${ esc( S.describe ) }</textarea>
			<div class="wdi-ai__tries"><span class="wdi-muted wdi-small">${ esc( __( 'Try:', 'wbcom-demo-installer' ) ) }</span>${ tries.map( ( t, i ) => `<button type="button" class="wdi-chip" data-try="${ i }">${ esc( t ) }</button>` ).join( '' ) }</div>
			<div class="wdi-ai__foot"><span class="wdi-muted wdi-small">${ esc( sprintf( /* translators: %s: AI connector names, e.g. "Anthropic". */ __( 'Your description, your site language and the list of Wbcom products are sent through your AI connector (%s). Nothing is built yet.', 'wbcom-demo-installer' ), ( ai.connectors || [] ).join( ', ' ) ) ) }</span><button type="button" class="wdi-btn wdi-btn--primary" data-go="ideas" ${ S.thinking ? 'disabled' : '' }>${ S.thinking ? `<span class="wdi-spin" aria-hidden="true"></span>${ esc( __( 'Thinking of ideas…', 'wbcom-demo-installer' ) ) }` : SPARK + esc( __( 'Suggest 3 site ideas', 'wbcom-demo-installer' ) ) }</button></div>
		</div>`;
	}

	function viewIdeas() {
		const names = Object.fromEntries( ( S.site.catalog || [] ).map( ( c ) => [ c.id, c.label ] ) );
		const recipes = S.site.all_recipes || S.site.recipes;
		const label = ( id ) => names[ id ] || ( S.site.products.find( ( p ) => p.id === id ) || {} ).label || id;
		const cards = ( S.ideas || [] ).map( ( idea, i ) => `<article class="wdi-idea">
			<div class="wdi-idea__head"><div><h2>${ esc( idea.name ) }</h2><p class="wdi-muted">${ esc( idea.tagline ) }</p></div><span class="wdi-badge wdi-badge--accent">${ esc( ( recipes[ idea.recipe ] || {} ).name || idea.recipe ) }</span></div>
			<div class="wdi-idea__prods">${ idea.products.map( ( id ) => `<span class="wdi-idea__p">${ esc( label( id ) ) }${ idea.missing.includes( id ) ? ` <i>${ esc( __( 'will install', 'wbcom-demo-installer' ) ) }</i>` : '' }</span>` ).join( '' ) }</div>
			<div class="wdi-idea__cols"><div><h3>${ esc( __( 'Your site will have', 'wbcom-demo-installer' ) ) }</h3><ul>${ idea.highlights.map( ( h ) => `<li>${ esc( h ) }</li>` ).join( '' ) }</ul></div><div><h3>${ esc( __( 'Why these products', 'wbcom-demo-installer' ) ) }</h3><ul>${ Object.entries( idea.why ).map( ( [ id, w ] ) => `<li><b>${ esc( label( id ) ) }:</b> ${ esc( w ) }</li>` ).join( '' ) }</ul></div></div>
			<div class="wdi-idea__foot"><span class="wdi-muted wdi-small">${ esc( idea.audience ) }</span><span class="wdi-spacer"></span><button type="button" class="wdi-btn ${ 0 === i ? 'wdi-btn--primary' : '' }" data-idea="${ i }">${ esc( __( 'Use this idea', 'wbcom-demo-installer' ) ) }</button></div>
		</article>` ).join( '' );
		return brand( __( 'Set up your site', 'wbcom-demo-installer' ) ) + stepper( SETUP, 0 ) + card(
			__( 'Three sites you could launch', 'wbcom-demo-installer' ),
			sprintf( /* translators: %s: the owner's description. */ __( 'For "%s"', 'wbcom-demo-installer' ), S.describe ),
			`<div class="wdi-ideas">${ cards }</div>`,
			`<div class="wdi-card__foot"><button type="button" class="wdi-link wdi-small" data-go="check">${ esc( __( 'Change the description', 'wbcom-demo-installer' ) ) }</button><button type="button" class="wdi-link wdi-small" data-go="ideas">${ esc( __( 'Suggest 3 more', 'wbcom-demo-installer' ) ) }</button><span class="wdi-spacer"></span><button type="button" class="wdi-btn" data-go="start">${ esc( __( 'Choose a starting point myself', 'wbcom-demo-installer' ) ) }</button></div>`
		);
	}

	// The picked idea fills the wizard: starting point, products, name and tagline; missing products are ticked to install.
	function applyIdea( idea ) {
		S.brief = Object.assign( { description: S.describe }, idea );
		S.name = idea.name;
		S.tagline = idea.tagline;
		const active = products().map( ( p ) => p.id );
		S.include = new Set( idea.products.filter( ( id ) => active.includes( id ) ) );
		S.recipe = S.site.recipes[ idea.recipe ] ? idea.recipe : S.recipe;
		const missing = idea.products.filter( ( id ) => ! active.includes( id ) );
		if ( missing.length ) {
			S.pick = new Set( missing );
			go( 'check' );
			return;
		}
		go( 'start' );
	}

	// Wbcom products the wizard can install and activate (free only; Pro needs a licence and stays manual).
	function installPanel() {
		const cat = S.site.catalog || [];
		if ( ! cat.length ) {
			return '';
		}
		if ( ! S.pick ) {
			// The community set is ticked by default when part of it is missing; extras are the owner's choice.
			S.pick = new Set( cat.filter( ( c ) => c.core && 'active' !== c.state && c.can ).map( ( c ) => c.id ) );
		}
		const busy = Object.values( S.installing ).includes( 'busy' );
		const label = { active: __( 'Active', 'wbcom-demo-installer' ), inactive: __( 'Installed, not active', 'wbcom-demo-installer' ), not_installed: __( 'Not installed', 'wbcom-demo-installer' ) };
		const pro = { active: __( 'Pro add-on active', 'wbcom-demo-installer' ), inactive: __( 'Pro add-on installed, not active', 'wbcom-demo-installer' ), not_installed: __( 'Pro add-on needs a licence: install it from your account', 'wbcom-demo-installer' ) };
		const row = ( c ) => {
			const st = S.installing[ c.id ];
			const badge = 'busy' === st
				? `<span class="wdi-badge"><span class="wdi-spin" aria-hidden="true"></span>${ esc( 'inactive' === c.state ? __( 'Activating', 'wbcom-demo-installer' ) : __( 'Installing', 'wbcom-demo-installer' ) ) }</span>`
				: `<span class="wdi-badge ${ 'active' === c.state ? 'wdi-badge--success' : 'inactive' === c.state ? 'wdi-badge--warning' : '' }">${ 'active' === c.state ? ic( 'check' ) : '' }${ esc( label[ c.state ] ) }</span>`;
			const box = 'active' === c.state
				? `<span class="wdi-checks__ic">${ ic( 'check' ) }</span>`
				: `<span class="wdi-checks__ic"><input type="checkbox" data-pick="${ esc( c.id ) }" ${ S.pick.has( c.id ) ? 'checked' : '' } ${ c.can && ! busy ? '' : 'disabled' } aria-label="${ esc( sprintf( /* translators: %s: product name. */ __( 'Install %s', 'wbcom-demo-installer' ), c.label ) ) }"></span>`;
			const note = [ 'theme' === c.type ? __( 'Free theme. Switches your theme; Finish demo does not switch it back.', 'wbcom-demo-installer' ) : '', c.pro ? pro[ c.pro ] : '', ! c.can && 'active' !== c.state ? __( 'Your account or this site does not allow installing here.', 'wbcom-demo-installer' ) : '', st && ! [ 'busy', 'done' ].includes( st ) ? st : '' ].filter( Boolean );
			return `<li>${ box }<div>${ esc( c.label ) }${ note.map( ( n ) => `<small${ n === st ? ' class="wdi-install__error" role="alert"' : '' }>${ esc( n ) }</small>` ).join( '' ) }</div>${ badge }</li>`;
		};
		const group = ( title, rows ) => rows.length ? `<h2 class="wdi-install__title">${ esc( title ) }</h2><ul class="wdi-checks">${ rows.map( row ).join( '' ) }</ul>` : '';
		const todo = cat.filter( ( c ) => S.pick.has( c.id ) && 'active' !== c.state );
		return `<div class="wdi-install">
			${ group( __( 'Community set, used by every starting point', 'wbcom-demo-installer' ), cat.filter( ( c ) => c.core ) ) }
			${ group( __( 'Add more products', 'wbcom-demo-installer' ), cat.filter( ( c ) => ! c.core && 'plugin' === c.type ) ) }
			${ group( __( 'Theme', 'wbcom-demo-installer' ), cat.filter( ( c ) => 'theme' === c.type ) ) }
			<div class="wdi-install__foot"><span class="wdi-muted wdi-small">${ esc( __( 'Free versions from wbcomdesigns.com and WordPress.org, the same releases you get as updates.', 'wbcom-demo-installer' ) ) }</span><span class="wdi-spacer"></span><button type="button" class="wdi-btn" data-go="install" ${ todo.length && ! busy ? '' : 'disabled' }>${ esc( todo.length ? sprintf( /* translators: %d: number of products. */ _n( 'Install and activate %d product', 'Install and activate %d products', todo.length, 'wbcom-demo-installer' ), todo.length ) : __( 'Install selected', 'wbcom-demo-installer' ) ) }</button></div>
		</div>`;
	}

	// One product per request: each activation runs in its own request and boots on the next one.
	async function installPicked() {
		const todo = ( S.site.catalog || [] ).filter( ( c ) => S.pick.has( c.id ) && 'active' !== c.state && c.can );
		// The community set goes first so products that build on BuddyNext find it active.
		todo.sort( ( a, b ) => ( b.core ? 1 : 0 ) - ( a.core ? 1 : 0 ) );
		todo.forEach( ( c ) => ( S.installing[ c.id ] = 'busy' ) );
		render();
		for ( const c of todo ) {
			try {
				await api( '/install', { id: c.id } );
				S.installing[ c.id ] = 'done';
				S.pick.delete( c.id );
			} catch ( e ) {
				S.installing[ c.id ] = ( e && e.message ) || __( 'It could not be installed.', 'wbcom-demo-installer' );
			}
			// Re-read the site after each one, so the list and the starting points show what is active now.
			try {
				const res = await api( '/site' );
				S.site = res.site;
			} catch ( e ) {}
			render();
		}
		S.recipe = '';
		route();
		if ( S.brief ) {
			applyIdea( S.brief ); // The idea's products are active now: fill the wizard from it again.
		}
	}

	// How the picked idea shapes the step the owner is on: everything stays preselected and changeable.
	function ideaNote( step ) {
		const b = S.brief;
		if ( ! b ) {
			return '';
		}
		const recipes = S.site.all_recipes || S.site.recipes;
		const text = {
			start: sprintf( /* translators: 1: starting point, 2: audience. */ __( 'Preselected: %1$s. %2$s', 'wbcom-demo-installer' ), ( recipes[ b.recipe ] || {} ).name || b.recipe, b.audience ),
			products: __( 'Preselected: the products this idea uses, with the reason for each. Switch any of them off or on.', 'wbcom-demo-installer' ),
			look: sprintf( /* translators: 1: site name, 2: tagline. */ __( 'The header shows "%1$s" and the tagline "%2$s" in place of the sample logo; the menu links every product you chose.', 'wbcom-demo-installer' ), S.name ?? b.name, S.tagline ?? b.tagline ),
		}[ step ] || '';
		return `<div class="wdi-ideanote">${ SPARK }<div><b>${ esc( sprintf( /* translators: %s: site name. */ __( 'From your idea: %s', 'wbcom-demo-installer' ), b.name ) ) }</b> ${ esc( text ) } <button type="button" class="wdi-link wdi-small" data-go="forget-idea">${ esc( __( 'Use no idea', 'wbcom-demo-installer' ) ) }</button></div></div>`;
	}

	// Review: what the idea becomes when Build runs.
	function ideaPlan() {
		const b = S.brief;
		if ( ! b ) {
			return '';
		}
		return `<div class="wdi-ideaplan"><div class="wdi-ideaplan__head">${ SPARK }<div><b>${ esc( S.name ?? b.name ) }</b><span class="wdi-muted">${ esc( S.tagline ?? b.tagline ) }</span></div></div>
			${ b.highlights.length ? `<h3>${ esc( __( 'Your site will have', 'wbcom-demo-installer' ) ) }</h3><ul>${ b.highlights.map( ( h ) => `<li>${ esc( h ) }</li>` ).join( '' ) }</ul>` : '' }
			<h3>${ esc( __( 'When you choose Build', 'wbcom-demo-installer' ) ) }</h3>
			<ol><li>${ esc( sprintf( /* translators: %s: AI connector names. */ __( 'Your AI connector (%s) writes the text for this site, product by product: people\'s bios, spaces, posts, discussions, courses, events, pages and blog posts. Each product shows on the progress screen as it is written.', 'wbcom-demo-installer' ), ( S.site.ai.connectors || [] ).join( ', ' ) ) ) }</li><li>${ esc( __( 'The demo is built with that text and photos from the installer\'s library, in the counts below.', 'wbcom-demo-installer' ) ) }</li><li>${ esc( __( 'A product whose text does not pass our check uses the sample text, and the build carries on. Finish demo removes everything, as with any demo.', 'wbcom-demo-installer' ) ) }</li></ol>
		</div>`;
	}

	function ideaBanner() {
		if ( ! S.brief ) {
			return '';
		}
		const active = products().map( ( p ) => p.id );
		const missing = S.brief.products.filter( ( id ) => ! active.includes( id ) );
		return `<div class="wdi-callout wdi-callout--success">${ ic( 'check' ) }<div><b>${ esc( S.brief.name ) }</b>: ${ esc( missing.length ? sprintf( /* translators: %d: number of products. */ _n( 'this idea needs %d more product. It is ticked below: install it, then continue.', 'this idea needs %d more products. They are ticked below: install them, then continue.', missing.length, 'wbcom-demo-installer' ), missing.length ) : __( 'everything this idea needs is active.', 'wbcom-demo-installer' ) ) } ${ missing.length ? '' : `<button type="button" class="wdi-btn wdi-btn--primary" data-go="start">${ esc( __( 'Continue with this idea', 'wbcom-demo-installer' ) ) }${ ic( 'right' ) }</button>` } <button type="button" class="wdi-link" data-go="forget-idea">${ esc( __( 'Use no idea', 'wbcom-demo-installer' ) ) }</button></div></div>`;
	}

	function viewCheck() {
		const s = S.site;
		const supported = products().filter( ( p ) => p.supported );
		const rows = [
			`<li><span class="wdi-checks__ic">${ ic( 'palette' ) }</span><div>${ esc( s.theme.name + ' ' + s.theme.version ) }<small>${ esc( __( 'Theme. The home page, menu and logo can be set up for you.', 'wbcom-demo-installer' ) ) }</small></div><span class="wdi-badge wdi-badge--success">${ ic( 'check' ) }${ esc( __( 'Ready', 'wbcom-demo-installer' ) ) }</span></li>`,
			`<li><span class="wdi-checks__ic">${ ic( 'plug' ) }</span><div>${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ _n( '%d Wbcom product ready', '%d Wbcom products ready', supported.length, 'wbcom-demo-installer' ), supported.length ) ) }<small>${ esc( supported.map( ( p ) => p.label ).join( ', ' ) ) }</small></div><span class="wdi-badge wdi-badge--success">${ ic( 'check' ) }${ esc( __( 'Supported', 'wbcom-demo-installer' ) ) }</span></li>`,
			...products().filter( ( p ) => ! p.supported ).map( ( p ) => `<li><span class="wdi-checks__ic">${ ic( 'alert' ) }</span><div>${ esc( p.label + ' ' + p.version ) }<small>${ esc( __( 'Update this product to include it in the demo.', 'wbcom-demo-installer' ) ) }</small></div><span class="wdi-badge wdi-badge--warning">${ esc( __( 'Not supported yet', 'wbcom-demo-installer' ) ) }</span></li>` ),
			`<li><span class="wdi-checks__ic">${ ic( 'users' ) }</span><div>${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ _n( '%1$s user, %2$s posts and pages', '%1$s users, %2$s posts and pages', s.users, 'wbcom-demo-installer' ), fmt( s.users ), fmt( s.posts ) ) ) }<small>${ esc( __( 'Yours. They are not changed or removed.', 'wbcom-demo-installer' ) ) }</small></div><span class="wdi-badge">${ esc( __( 'Untouched', 'wbcom-demo-installer' ) ) }</span></li>`,
		];
		const other = s.leftovers.length ? `<div class="wdi-callout wdi-callout--warning">${ ic( 'alert' ) }<div>${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( 'Demo content from %s is still on this site. Remove it from that product first so the two demos do not mix.', 'wbcom-demo-installer' ), s.leftovers.join( ', ' ) ) ) }</div></div>` : '';
		const live = s.live && s.live.length ? `<div class="wdi-callout wdi-callout--warning">${ ic( 'alert' ) }<div><b>${ esc( sprintf( /* translators: %s: what the site already has, e.g. "3 other members, 12 blog posts". */ __( 'This site already has %s.', 'wbcom-demo-installer' ), s.live.join( ', ' ) ) ) }</b> ${ esc( __( 'The demo is made for a fresh site you use to show or try the products. On a site people use, sample people appear next to your real members until you finish the demo. Use a staging copy or a fresh install.', 'wbcom-demo-installer' ) ) }</div></div>` : '';
		const mem = s.memory && s.memory.low ? `<div class="wdi-callout wdi-callout--warning">${ ic( 'alert' ) }<div><b>${ esc( sprintf( /* translators: %d: memory limit in MB. */ __( 'PHP memory is limited to %d MB.', 'wbcom-demo-installer' ), Math.round( s.memory.limit / 1048576 ) ) ) }</b> ${ esc( __( 'With all products active, WordPress needs about 165 MB before the demo adds anything, so a build step can stop. Ask your host to raise memory_limit to 256 MB, or add define( \'WP_MEMORY_LIMIT\', \'256M\' ); to wp-config.php.', 'wbcom-demo-installer' ) ) }</div></div>` : '';
		const none = ! Object.keys( s.recipes ).length ? `<div class="wdi-callout wdi-callout--warning">${ ic( 'alert' ) }<div>${ esc( __( 'No supported Wbcom product is active yet. Install the community set below to start.', 'wbcom-demo-installer' ) ) }</div></div>` : '';
		return brand( __( 'Set up your site', 'wbcom-demo-installer' ) ) + stepper( SETUP, 0 ) + card(
			__( "Let's see what you have", 'wbcom-demo-installer' ),
			__( 'We only read your site on this step. Nothing changes until you click Build.', 'wbcom-demo-installer' ),
			`<ul class="wdi-checks">${ rows.join( '' ) }</ul>${ mem }${ live }${ other }${ none }${ ideaBanner() }${ S.brief ? '' : aiPanel() }${ installPanel() }`,
			foot( null, 'start', { skip: __( 'Skip, I will add my own content', 'wbcom-demo-installer' ), disabled: ! Object.keys( s.recipes ).length } )
		);
	}

	function viewStart() {
		const recipes = S.site.recipes;
		return brand( __( 'Set up your site', 'wbcom-demo-installer' ) ) + stepper( SETUP, 1 ) + card(
			__( 'What kind of site are you building?', 'wbcom-demo-installer' ),
			__( 'Pick the closest one. You can change every product on the next step.', 'wbcom-demo-installer' ),
			`${ ideaNote( 'start' ) }<div class="wdi-recipes" role="radiogroup" aria-label="${ esc( __( 'Starting point', 'wbcom-demo-installer' ) ) }">${ Object.entries( recipes ).map( ( [ id, r ] ) => `<button type="button" class="wdi-opt" role="radio" aria-checked="${ S.recipe === id }" data-recipe="${ esc( id ) }"><span class="wdi-opt__tick">${ ic( 'check' ) }</span>${ thumb( SHAPES[ id ] || SHAPES.everything ) }<b>${ esc( r.name ) }</b><span class="wdi-muted">${ esc( r.desc ) }</span><span class="wdi-opt__meta">${ 'everything' === id ? `<span class="wdi-badge wdi-badge--accent">${ esc( __( 'Best for evaluating', 'wbcom-demo-installer' ) ) }</span>` : '' }<span class="wdi-badge">${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ _n( '%d product', '%d products', r.use.length, 'wbcom-demo-installer' ), r.use.length ) ) }</span></span></button>` ).join( '' ) }</div>
			${ Object.keys( recipes ).length < 7 ? `<details class="wdi-details"><summary>${ esc( __( 'More starting points', 'wbcom-demo-installer' ) ) }</summary><p>${ esc( __( 'Starting points appear for each product you activate: Learning (Learnomy), Events (Eventonomy), Job board (WP Career Board), Directory (WB Listora) and Services marketplace (WP Sell Services).', 'wbcom-demo-installer' ) ) }</p></details>` : '' }`,
			foot( 'check', 'products', { disabled: ! S.recipe } )
		);
	}

	function viewProducts() {
		const t = totals();
		const rows = products().filter( ( p ) => p.supported ).map( ( p ) => {
			const on = S.include.has( p.id );
			const dep = 'buddynext' === p.id && on && product( 'mediaverse' ) && ! S.include.has( 'mediaverse' );
			return `<div class="wdi-prod ${ on ? '' : 'is-off' }"><span class="wdi-prod__pic">${ ic( p.icon || 'plug', 'wdi-i--lg' ) }</span><div><div class="wdi-prod__title">${ esc( p.label ) }${ p.pro ? `<span class="wdi-badge wdi-badge--info">${ esc( p.pro ) }</span>` : '' }</div><div class="wdi-prod__adds">${ esc( p.summary ) }</div>${ S.brief && S.brief.why && S.brief.why[ p.id ] ? `<div class="wdi-prod__why">${ SPARK }${ esc( S.brief.why[ p.id ] ) }</div>` : '' }${ dep ? `<div class="wdi-prod__dep">${ ic( 'alert' ) }${ esc( __( 'Without WPMediaVerse: no photo posts, albums or messages', 'wbcom-demo-installer' ) ) }</div>` : '' }</div><label class="wdi-switch"><input type="checkbox" data-prod="${ esc( p.id ) }" ${ on ? 'checked' : '' } aria-label="${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( 'Include %s', 'wbcom-demo-installer' ), p.label ) ) }"><span></span></label></div>`;
		} ).join( '' );
		return brand( __( 'Set up your site', 'wbcom-demo-installer' ) ) + stepper( SETUP, 2 ) + card(
			__( 'Choose what to include', 'wbcom-demo-installer' ),
			sprintf( /* translators: placeholders are counts, product names or theme names. */ __( 'The same %d sample people appear across every product, so one person can post in the feed, teach a course and run an event.', 'wbcom-demo-installer' ), S.site.people ),
			`${ ideaNote( 'products' ) }<div class="wdi-products">${ rows }</div><div class="wdi-sumbar" aria-live="polite"><b>${ esc( __( 'Will create', 'wbcom-demo-installer' ) ) }</b>${ Object.entries( t ).map( ( [ k, n ] ) => `<span class="wdi-chip">${ fmt( n ) } ${ esc( k ) }</span>` ).join( '' ) }</div>`,
			foot( 'start', 'look', { disabled: ! S.include.size } )
		);
	}

	function viewLook() {
		return brand( __( 'Set up your site', 'wbcom-demo-installer' ) ) + stepper( SETUP, 3 ) + card(
			__( 'Home page and menu', 'wbcom-demo-installer' ),
			sprintf( /* translators: placeholders are counts, product names or theme names. */ __( '%s can open on your community from the first visit.', 'wbcom-demo-installer' ), S.site.theme.name ),
			`${ ideaNote( 'look' ) }<div class="wdi-choices" role="radiogroup">
				<label class="wdi-choice"><input type="radio" name="look" value="demo" ${ 'demo' === S.look ? 'checked' : '' }><span class="wdi-choice__ic">${ ic( 'home' ) }</span><span><b>${ esc( __( 'Use the community home page, menu and logo', 'wbcom-demo-installer' ) ) }</b><span class="wdi-muted">${ esc( __( 'The home page opens on your community and the menu links every product you chose.', 'wbcom-demo-installer' ) ) }</span> <span class="wdi-badge wdi-badge--accent">${ esc( __( 'Recommended for a new site', 'wbcom-demo-installer' ) ) }</span></span></label>
				<label class="wdi-choice"><input type="radio" name="look" value="keep" ${ 'keep' === S.look ? 'checked' : '' }><span class="wdi-choice__ic">${ ic( 'palette' ) }</span><span><b>${ esc( __( 'Keep my home page, menu and logo', 'wbcom-demo-installer' ) ) }</b><span class="wdi-muted">${ esc( __( 'Sample content is added. Your home page, menu and theme settings stay exactly as they are.', 'wbcom-demo-installer' ) ) }</span></span></label>
			</div>
			<div class="wdi-callout wdi-callout--info">${ ic( 'undo' ) }<div>${ esc( __( 'We save your current home page, menu and logo first, so you can put them back when you finish the demo. A menu you already have is never edited: the demo adds its own Demo menu.', 'wbcom-demo-installer' ) ) }</div></div>`,
			foot( 'products', 'review' )
		);
	}

	function viewReview() {
		const t = totals();
		const n = Object.values( t ).reduce( ( a, b ) => a + b, 0 );
		const rows = [ `<tr><td>${ esc( __( 'Shared', 'wbcom-demo-installer' ) ) }</td><td class="wdi-num">${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( '%d sample people', 'wbcom-demo-installer' ), S.site.people ) ) }${ 'demo' === S.look ? esc( ', ' + __( 'home page, menu and logo', 'wbcom-demo-installer' ) ) : '' }</td></tr>` ]
			.concat( products().filter( ( p ) => S.include.has( p.id ) ).map( ( p ) => `<tr><td>${ esc( p.label ) }</td><td class="wdi-num">${ esc( Object.values( p.plan ).filter( ( r ) => r[ 0 ] > 0 ).map( ( r ) => fmt( r[ 0 ] ) + ' ' + r[ 2 ] ).join( ', ' ) || p.summary ) }</td></tr>` ) );
		return brand( __( 'Set up your site', 'wbcom-demo-installer' ) ) + stepper( SETUP, 4 ) + card(
			__( 'Ready to build', 'wbcom-demo-installer' ),
			__( 'This is what will be created.', 'wbcom-demo-installer' ),
			`<div class="wdi-table-wrap"><table class="wdi-table"><thead><tr><th>${ esc( __( 'Product', 'wbcom-demo-installer' ) ) }</th><th>${ esc( __( 'Creates', 'wbcom-demo-installer' ) ) }</th></tr></thead><tbody>${ rows.join( '' ) }</tbody></table></div>
			${ ideaPlan() }
			<div class="wdi-fields">
				<label class="wdi-field"><span>${ esc( __( 'Site name', 'wbcom-demo-installer' ) ) }</span><input type="text" id="wdi-name" maxlength="80" value="${ esc( S.name ?? S.site.identity.name ) }"></label>
				<label class="wdi-field"><span>${ esc( __( 'Tagline', 'wbcom-demo-installer' ) ) }</span><input type="text" id="wdi-tagline" maxlength="140" value="${ esc( S.tagline ?? S.site.identity.tagline ) }"></label>
				<p class="wdi-muted wdi-small">${ esc( __( 'A new site name shows in the header in place of the sample logo. Remove everything puts your current name and tagline back.', 'wbcom-demo-installer' ) ) }</p>
			</div>
			<div class="wdi-assure"><div>${ ic( 'mail' ) }<span><b>${ esc( __( 'Nothing is emailed.', 'wbcom-demo-installer' ) ) }</b> ${ esc( __( 'Sample people never get mail, now or later.', 'wbcom-demo-installer' ) ) }</span></div><div>${ ic( 'shield' ) }<span><b>${ esc( __( 'Your own users and content', 'wbcom-demo-installer' ) ) }</b> ${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ _n( '(%1$s user, %2$s posts and pages) are not changed.', '(%1$s users, %2$s posts and pages) are not changed.', S.site.users, 'wbcom-demo-installer' ), fmt( S.site.users ), fmt( S.site.posts ) ) ) }</span></div><div>${ ic( 'clock' ) }<span><b>${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ _n( 'About %d minute.', 'About %d minutes.', Math.max( 1, Math.round( n / 300 ) ), 'wbcom-demo-installer' ), Math.max( 1, Math.round( n / 300 ) ) ) ) }</b> ${ esc( __( 'Dates are spread over the last two months.', 'wbcom-demo-installer' ) ) }</span></div></div>`,
			( S.site.live && S.site.live.length ? `<label class="wdi-consent"><input type="checkbox" id="wdi-consent" ${ S.consent ? 'checked' : '' }> <span>${ esc( sprintf( /* translators: %s: what the site already has. */ __( 'This site already has %s. I understand the demo is made for a fresh site, and I want sample content added here.', 'wbcom-demo-installer' ), S.site.live.join( ', ' ) ) ) }</span></label>` : '' ) +
			foot( 'look', 'build', { label: __( 'Build my demo', 'wbcom-demo-installer' ), disabled: S.site.live && S.site.live.length && ! S.consent } )
		);
	}

	function progressCard( title, flow, idx, label ) {
		const st = S.status || {};
		const done = st.done || 0;
		const total = Math.max( 1, st.total || 1 );
		const pct = Math.round( ( done / total ) * 100 );
		return brand( title ) + stepper( flow, idx ) + card(
			label,
			__( 'You can leave this page. It keeps going and picks up where it stopped when you come back.', 'wbcom-demo-installer' ),
			`<div class="wdi-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${ pct }"><i style="width:${ pct }%"></i></div>
			<ul class="wdi-log"><li>${ st.current ? `<span class="wdi-spin" aria-hidden="true"></span>${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( 'Working on %s', 'wbcom-demo-installer' ), st.current ) ) }` : esc( __( 'Finishing up…', 'wbcom-demo-installer' ) ) }</li><li class="wdi-muted wdi-num">${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( '%1$d of %2$d steps', 'wbcom-demo-installer' ), done, total ) ) }</li></ul>`
		);
	}

	function ledgerTiles() {
		const groups = {};
		( S.status.ledger || [] ).forEach( ( r ) => {
			( groups[ r.product ] = groups[ r.product ] || [] ).push( r );
		} );
		return Object.entries( groups ).map( ( [ prod, rows ] ) => `<li><b>${ esc( prod ) }:</b> <span class="wdi-num">${ esc( rows.map( ( r ) => fmt( r.count ) + ' ' + r.label ).join( ', ' ) ) }</span></li>` ).join( '' );
	}

	// What a finish left: waiting for an inactive product, or failed. Offered again on the Ready and Report screens.
	function waitingCallout( r ) {
		if ( ! r || ! r.left ) {
			return '';
		}
		const who = ( r.waiting_for || [] ).join( ', ' );
		return `<div class="wdi-callout wdi-callout--warning">${ ic( 'clock' ) }<div>${ esc( who
			? sprintf( /* translators: 1: number of items, 2: product names. */ _n( '%1$s item is waiting for %2$s. Activate it, then continue removing.', '%1$s items are waiting for %2$s. Activate them, then continue removing.', r.left, 'wbcom-demo-installer' ), fmt( r.left ), who )
			: sprintf( /* translators: %s: number of items. */ _n( '%s item could not be removed.', '%s items could not be removed.', r.left, 'wbcom-demo-installer' ), fmt( r.left ) ) ) } <button type="button" class="wdi-btn" data-go="resume">${ esc( __( 'Continue removing', 'wbcom-demo-installer' ) ) }</button></div></div>`;
	}

	function viewReady() {
		const st = S.status;
		const sum = ( kind ) => ( st.ledger || [] ).filter( ( r ) => ! kind || kind === r.kind ).reduce( ( a, r ) => a + r.count, 0 );
		const total = sum();
		const errors = st.errors || [];
		const warn = errors.concat( st.warnings || [] );
		const problems = warn.length ? `<details class="wdi-details" ${ errors.length ? 'open' : '' }><summary>${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ _n( '%d item could not be created', '%d items could not be created', warn.length, 'wbcom-demo-installer' ), warn.length ) ) }</summary><ul>${ warn.map( ( w ) => `<li>${ esc( w ) }</li>` ).join( '' ) }</ul></details>` : '';
		if ( ! sum( 'sample' ) ) {
			// Only setup is left (settings, pages, menus, categories): still removable until the owner keeps it.
			return brand( __( 'Your demo', 'wbcom-demo-installer' ) ) + card(
				__( 'The sample content is gone', 'wbcom-demo-installer' ),
				sprintf( /* translators: %s: number of items. */ _n( '%s setup item from the demo is still tracked: a setting, page, menu or category. Remove it too, or keep it as yours.', '%s setup items from the demo are still tracked: settings, pages, menus and categories. Remove them too, or keep them as yours.', total, 'wbcom-demo-installer' ), fmt( total ) ),
				`${ waitingCallout( st.report ) }<details class="wdi-details" open><summary>${ esc( __( 'What is still tracked', 'wbcom-demo-installer' ) ) }</summary><ul>${ ledgerTiles() }</ul></details>`,
				`<div class="wdi-card__foot"><button type="button" class="wdi-btn" data-go="finish">${ esc( __( 'Remove them too', 'wbcom-demo-installer' ) ) }</button><span class="wdi-spacer"></span><button type="button" class="wdi-btn wdi-btn--primary" data-go="adopt">${ esc( __( 'Keep them as mine', 'wbcom-demo-installer' ) ) }</button></div>`
			);
		}
		return brand( __( 'Your demo', 'wbcom-demo-installer' ) ) + card(
			errors.length ? __( 'Your demo is built, with problems', 'wbcom-demo-installer' ) : __( 'Your site is ready', 'wbcom-demo-installer' ),
			errors.length ? __( 'Some products could not finish their part. The list below says which and why.', 'wbcom-demo-installer' ) : __( 'Take a look around, then make it yours.', 'wbcom-demo-installer' ),
			`${ waitingCallout( st.report ) }${ errors.length ? problems : '' }<div class="wdi-status">${ ic( 'spark' ) }<span><b>${ esc( __( 'Demo active', 'wbcom-demo-installer' ) ) }</b> · <span class="wdi-num">${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( '%s items', 'wbcom-demo-installer' ), fmt( total ) ) ) }</span></span><span class="wdi-spacer"></span><button type="button" class="wdi-btn" data-go="finish">${ esc( __( 'Finish demo', 'wbcom-demo-installer' ) ) }</button></div>
			<div class="wdi-tiles">
				<div class="wdi-tile"><h2>${ ic( 'eye' ) }${ esc( __( 'See it', 'wbcom-demo-installer' ) ) }</h2><ul><li><a class="wdi-link" href="${ esc( cfg.home ) }" target="_blank" rel="noopener">${ esc( __( 'Home page', 'wbcom-demo-installer' ) ) }</a></li>${ ( S.site.links || [] ).map( ( l ) => `<li><a class="wdi-link" href="${ esc( l.url ) }" target="_blank" rel="noopener">${ esc( l.title ) }</a></li>` ).join( '' ) }</ul></div>
				<div class="wdi-tile"><h2>${ ic( 'palette' ) }${ esc( __( 'Make it yours', 'wbcom-demo-installer' ) ) }</h2><ul><li><a class="wdi-link" href="${ esc( cfg.customize ) }">${ esc( __( 'Logo, colours and site name', 'wbcom-demo-installer' ) ) }</a></li><li><a class="wdi-link" href="${ esc( cfg.plugins ) }">${ esc( __( 'Review product settings', 'wbcom-demo-installer' ) ) }</a></li></ul></div>
				<div class="wdi-tile"><h2>${ ic( 'users' ) }${ esc( __( 'When you are ready', 'wbcom-demo-installer' ) ) }</h2><span class="wdi-muted">${ esc( __( 'Finish the demo: remove the sample people and content and keep everything you set up.', 'wbcom-demo-installer' ) ) }</span></div>
			</div>
			<details class="wdi-details"><summary>${ esc( __( 'What the demo added', 'wbcom-demo-installer' ) ) }</summary><ul>${ ledgerTiles() }</ul></details>
			${ errors.length ? '' : problems }`,
			`<div class="wdi-card__foot"><span class="wdi-spacer"></span><a class="wdi-btn wdi-btn--primary" href="${ esc( cfg.home ) }">${ esc( __( 'Visit your site', 'wbcom-demo-installer' ) ) }${ ic( 'right' ) }</a></div>`
		);
	}

	function viewNoData() {
		return brand( __( 'Demo data', 'wbcom-demo-installer' ) ) + card(
			__( 'No demo data on this site', 'wbcom-demo-installer' ),
			__( 'The installer is not tracking any sample content or setup here. Your own users and content are never listed.', 'wbcom-demo-installer' ),
			`<p class="wdi-muted">${ esc( __( 'When you build a demo, this screen lets you remove all of it or choose what to remove, at any time.', 'wbcom-demo-installer' ) ) }</p>`,
			`<div class="wdi-card__foot"><a class="wdi-link wdi-small" href="${ esc( cfg.dashboard ) }">${ esc( __( 'Go to dashboard', 'wbcom-demo-installer' ) ) }</a><span class="wdi-spacer"></span><button type="button" class="wdi-btn wdi-btn--primary" data-go="check">${ esc( __( 'Set up a demo', 'wbcom-demo-installer' ) ) }${ ic( 'right' ) }</button></div>`
		);
	}

	/* ---------- Finish ---------- */

	function viewFinish() {
		const p = S.preview;
		const list = ( rows ) => rows.length ? rows.map( ( r ) => `<li><span>${ esc( r.product + ': ' + r.label ) }</span><b class="wdi-num">${ fmt( r.count ) }</b></li>` ).join( '' ) : `<li class="wdi-muted">${ esc( __( 'Nothing', 'wbcom-demo-installer' ) ) }</li>`;
		const all = p ? p.remove.concat( p.keep ).filter( ( r ) => 'people:user' !== r.key ) : [];
		const custom = 'custom' === S.mode && p ? `<div class="wdi-table-wrap"><table class="wdi-table"><thead><tr><th>${ esc( __( 'Product', 'wbcom-demo-installer' ) ) }</th><th>${ esc( __( 'Remove', 'wbcom-demo-installer' ) ) }</th></tr></thead><tbody>${ Object.entries( all.reduce( ( g, r ) => ( ( g[ r.product ] = g[ r.product ] || [] ).push( r ), g ), {} ) ).map( ( [ prod, rows ] ) => `<tr><td>${ esc( prod ) }</td><td>${ rows.map( ( r ) => `<label class="wdi-ck"><input type="checkbox" data-custom="${ esc( r.key ) }" ${ S.custom[ r.key ] ? 'checked' : '' }> <span class="wdi-num">${ fmt( r.count ) }</span> ${ esc( r.label ) } ${ 'setup' === r.kind ? `<span class="wdi-badge">${ esc( __( 'setup', 'wbcom-demo-installer' ) ) }</span>` : '' }</label>` ).join( '' ) }</td></tr>` ).join( '' ) }</tbody></table></div>` : '';
		return brand( __( 'Finish demo', 'wbcom-demo-installer' ) ) + stepper( FINISH, 0 ) + card(
			__( 'What should stay?', 'wbcom-demo-installer' ),
			__( 'Your own users and content are never touched.', 'wbcom-demo-installer' ),
			`<div class="wdi-choices" role="radiogroup">
				<label class="wdi-choice"><input type="radio" name="mode" value="keep" ${ 'keep' === S.mode ? 'checked' : '' }><span class="wdi-choice__ic">${ ic( 'shield' ) }</span><span><b>${ esc( __( 'Keep my setup, remove sample content', 'wbcom-demo-installer' ) ) }</b><span class="wdi-muted">${ esc( __( 'Removes the sample people and everything they made. Keeps settings, pages, menus, spaces, categories and plans.', 'wbcom-demo-installer' ) ) }</span> <span class="wdi-badge wdi-badge--accent">${ esc( __( 'Recommended', 'wbcom-demo-installer' ) ) }</span></span></label>
				<label class="wdi-choice"><input type="radio" name="mode" value="custom" ${ 'custom' === S.mode ? 'checked' : '' }><span class="wdi-choice__ic">${ ic( 'check' ) }</span><span><b>${ esc( __( 'Let me choose', 'wbcom-demo-installer' ) ) }</b><span class="wdi-muted">${ esc( __( 'Pick per product what goes and what stays.', 'wbcom-demo-installer' ) ) }</span></span></label>
				<label class="wdi-choice"><input type="radio" name="mode" value="all" ${ 'all' === S.mode ? 'checked' : '' }><span class="wdi-choice__ic">${ ic( 'undo' ) }</span><span><b>${ esc( __( 'Remove everything the demo added', 'wbcom-demo-installer' ) ) }</b><span class="wdi-muted">${ esc( __( 'Also removes sample spaces, categories and plans, and puts back your previous home page, menu and logo.', 'wbcom-demo-installer' ) ) }</span></span></label>
			</div>${ custom }
			${ p && ! p.people ? `<div class="wdi-callout wdi-callout--info">${ ic( 'users' ) }<div>${ esc( __( 'Some sample content stays, so the sample people who made it stay too.', 'wbcom-demo-installer' ) ) }</div></div>` : '' }
			${ p ? `<div class="wdi-split"><div class="wdi-split__col"><h2>${ ic( 'trash' ) }${ esc( __( 'Will remove', 'wbcom-demo-installer' ) ) }</h2><ul>${ list( p.remove ) }</ul></div><div class="wdi-split__col"><h2>${ ic( 'shield' ) }${ esc( __( 'Will keep', 'wbcom-demo-installer' ) ) }</h2><ul>${ list( p.keep ) }</ul></div></div>` : `<p class="wdi-muted">${ esc( __( 'Counting…', 'wbcom-demo-installer' ) ) }</p>` }`,
			`<div class="wdi-card__foot"><a class="wdi-link wdi-small" href="${ esc( location.pathname + '?page=wbcom-demo' ) }">${ esc( __( 'Cancel', 'wbcom-demo-installer' ) ) }</a><span class="wdi-spacer"></span><button type="button" class="wdi-btn wdi-btn--primary" data-go="confirm" ${ p ? '' : 'disabled' }>${ esc( __( 'Continue', 'wbcom-demo-installer' ) ) }${ ic( 'right' ) }</button></div>`
		);
	}

	function viewConfirm() {
		const p = S.preview;
		const n = p.remove.reduce( ( a, r ) => a + r.count, 0 );
		const waiting = p.remove.filter( ( r ) => ! r.available );
		return brand( __( 'Finish demo', 'wbcom-demo-installer' ) ) + stepper( FINISH, 1 ) + card(
			sprintf( /* translators: placeholders are counts, product names or theme names. */ _n( 'Remove %s sample item?', 'Remove %s sample items?', n, 'wbcom-demo-installer' ), fmt( n ) ),
			__( 'This cannot be undone.', 'wbcom-demo-installer' ),
			`<div class="wdi-table-wrap"><table class="wdi-table"><tbody>${ p.remove.map( ( r ) => `<tr><td>${ esc( r.product ) }</td><td>${ esc( r.label ) }</td><td class="wdi-num">${ fmt( r.count ) }</td></tr>` ).join( '' ) }</tbody></table></div>
			${ p.restore ? `<div class="wdi-callout wdi-callout--info">${ ic( 'undo' ) }<div>${ esc( __( 'Your previous home page, menu and logo will be put back. If you changed them after the build, your changes are kept.', 'wbcom-demo-installer' ) ) }</div></div>` : '' }
			${ waiting.length ? `<div class="wdi-callout wdi-callout--warning">${ ic( 'alert' ) }<div>${ esc( sprintf( /* translators: placeholders are counts, product names or theme names. */ __( '%s is not active, so its sample content waits until you activate it again. Everything else is removed now.', 'wbcom-demo-installer' ), [ ...new Set( waiting.map( ( r ) => r.product ) ) ].join( ', ' ) ) ) }</div></div>` : '' }
			<label class="wdi-ack"><input type="checkbox" id="wdi-ack" ${ S.ack ? 'checked' : '' }><span>${ esc( __( 'I understand the sample content is deleted permanently.', 'wbcom-demo-installer' ) ) }</span></label>`,
			foot( 'finish', 'run-finish', { danger: true, disabled: ! S.ack, label: __( 'Finish demo', 'wbcom-demo-installer' ) } )
		);
	}

	function viewReport() {
		const r = ( S.status && S.status.report ) || {};
		const kept = ( r.kept_sample || 0 ) + ( r.kept_setup || 0 );
		const clean = ! r.left && ! r.failed && ! ( r.issues || [] ).length;
		const empty = clean && ! kept;
		const ok = ( n ) => n ? `<span class="wdi-badge wdi-badge--warning">${ fmt( n ) }</span>` : `<span class="wdi-badge wdi-badge--success">${ ic( 'check' ) }0</span>`;
		const keptText = [ r.kept_sample ? sprintf( /* translators: %s: number. */ _n( '%s sample item', '%s sample items', r.kept_sample, 'wbcom-demo-installer' ), fmt( r.kept_sample ) ) : '', r.kept_setup ? sprintf( /* translators: %s: number. */ _n( '%s setup item', '%s setup items', r.kept_setup, 'wbcom-demo-installer' ), fmt( r.kept_setup ) ) : '' ].filter( Boolean ).join( ', ' );
		return brand( __( 'Finish demo', 'wbcom-demo-installer' ) ) + stepper( FINISH, 2 ) + card(
			empty ? __( 'Your site is ready for real members', 'wbcom-demo-installer' ) : ( clean ? __( 'Done: everything you chose was removed', 'wbcom-demo-installer' ) : __( 'Almost done', 'wbcom-demo-installer' ) ),
			empty ? __( 'We checked: nothing from the demo was left behind.', 'wbcom-demo-installer' ) : __( 'Here is what is still on the site from the demo.', 'wbcom-demo-installer' ),
			`<ul class="wdi-checks">
				<li><span class="wdi-checks__ic">${ ic( 'trash' ) }</span><div>${ esc( __( 'Items removed', 'wbcom-demo-installer' ) ) }</div><span class="wdi-badge wdi-badge--success wdi-num">${ fmt( r.removed || 0 ) }</span></li>
				${ kept ? `<li><span class="wdi-checks__ic">${ ic( 'shield' ) }</span><div>${ esc( __( 'Kept by your choice', 'wbcom-demo-installer' ) ) }<small>${ esc( keptText ) }</small></div><span class="wdi-badge wdi-badge--info wdi-num">${ fmt( kept ) }</span></li>` : '' }
				<li><span class="wdi-checks__ic">${ ic( 'users' ) }</span><div>${ esc( __( 'Sample people left', 'wbcom-demo-installer' ) ) }${ r.people_left && kept ? `<small>${ esc( __( 'They stay while content they made is kept.', 'wbcom-demo-installer' ) ) }</small>` : '' }</div>${ kept && r.people_left ? `<span class="wdi-badge wdi-badge--info wdi-num">${ fmt( r.people_left ) }</span>` : ok( r.people_left || 0 ) }</li>
				<li><span class="wdi-checks__ic">${ ic( 'alert' ) }</span><div>${ esc( __( 'Chosen items not removed yet', 'wbcom-demo-installer' ) ) }</div>${ ok( r.left || 0 ) }</li>
			</ul>
			${ waitingCallout( r ) }
			${ ( r.kept_options || [] ).length ? `<div class="wdi-callout wdi-callout--info">${ ic( 'palette' ) }<div>${ esc( __( 'You changed your home page, menu or logo after the build, so we kept your version.', 'wbcom-demo-installer' ) ) }</div></div>` : '' }
			${ ( r.issues || [] ).map( ( i ) => `<div class="wdi-callout wdi-callout--warning">${ ic( 'alert' ) }<div>${ esc( i ) }</div></div>` ).join( '' ) }
			${ ( r.leftovers || [] ).length ? `<div class="wdi-callout wdi-callout--info">${ ic( 'plug' ) }<div>${ ( r.leftovers || [] ).map( esc ).join( ' ' ) } ${ esc( __( 'These are records the products keep on purpose; they are not sample content and are not shown on the site.', 'wbcom-demo-installer' ) ) }</div></div>` : '' }
			<div class="wdi-tiles"><div class="wdi-tile"><h2>${ ic( 'users' ) }${ esc( __( 'Next', 'wbcom-demo-installer' ) ) }</h2><ul><li><a class="wdi-link" href="${ esc( cfg.users ) }">${ esc( __( 'Invite your first members', 'wbcom-demo-installer' ) ) }</a></li><li><a class="wdi-link" href="${ esc( cfg.home ) }">${ esc( __( 'Visit your site', 'wbcom-demo-installer' ) ) }</a></li></ul></div>${ empty
				? `<div class="wdi-tile"><h2>${ ic( 'trash' ) }${ esc( __( 'Installer no longer needed', 'wbcom-demo-installer' ) ) }</h2><span class="wdi-muted">${ esc( __( 'You can deactivate and delete it. Nothing you kept depends on it.', 'wbcom-demo-installer' ) ) }</span></div>`
				: `<div class="wdi-tile"><h2>${ ic( 'shield' ) }${ esc( __( 'Keep the installer for now', 'wbcom-demo-installer' ) ) }</h2><span class="wdi-muted">${ esc( __( 'It still tracks what the demo left, so you can remove it later from Appearance > Set up your site. Once only setup is left you can keep it as yours.', 'wbcom-demo-installer' ) ) }</span></div>` }</div>`,
			`<div class="wdi-card__foot">${ kept || r.left ? `<button type="button" class="wdi-btn" data-go="finish">${ esc( __( 'Remove more', 'wbcom-demo-installer' ) ) }</button>` : '' }<span class="wdi-spacer"></span><a class="wdi-btn wdi-btn--primary" href="${ esc( cfg.dashboard ) }">${ esc( __( 'Go to dashboard', 'wbcom-demo-installer' ) ) }</a></div>`
		);
	}

	/* ---------- Render + flow ---------- */

	function render() {
		const views = {
			check: viewCheck,
			start: viewStart,
			products: viewProducts,
			look: viewLook,
			review: viewReview,
			building: () => progressCard( __( 'Set up your site', 'wbcom-demo-installer' ), SETUP, 5, __( 'Building your demo', 'wbcom-demo-installer' ) ),
			ready: viewReady,
			finish: viewFinish,
			confirm: viewConfirm,
			finishing: () => progressCard( __( 'Finish demo', 'wbcom-demo-installer' ), FINISH, 2, __( 'Removing sample content', 'wbcom-demo-installer' ) ),
			report: viewReport,
			nodata: viewNoData,
			ideas: viewIdeas,
		};
		const focusKey = document.activeElement && ( document.activeElement.dataset.prod || document.activeElement.dataset.custom || ( document.activeElement.name && document.activeElement.name + ':' + document.activeElement.value ) );
		app.innerHTML = ( S.error ? `<div class="wdi-callout wdi-callout--danger wdi-error" role="alert">${ ic( 'alert' ) }<div>${ esc( S.error ) }${ S.stalled ? ` <button type="button" class="wdi-btn" data-go="retry">${ esc( __( 'Try again', 'wbcom-demo-installer' ) ) }</button>` : '' }</div></div>` : '' ) + ( views[ S.view ] || viewCheck )();
		if ( focusKey ) {
			const el = app.querySelector( `[data-prod="${ focusKey }"], [data-custom="${ focusKey }"]` ) || ( focusKey.includes( ':' ) && app.querySelector( `input[name="${ focusKey.split( ':' )[ 0 ] }"][value="${ focusKey.split( ':' )[ 1 ] }"]` ) );
			if ( el ) {
				el.focus();
			}
		}
	}

	function go( view ) {
		S.error = '';
		S.view = view;
		render();
		const h = app.querySelector( 'h1' );
		if ( h ) {
			h.setAttribute( 'tabindex', '-1' );
			h.focus( { preventScroll: false } );
		}
	}

	const wait = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

	// A failed request usually means the server stopped inside a step. Progress is saved and the engine skips a
	// step that keeps stopping the server, so the wizard retries a few times, then offers Try again.
	function stalled( e ) {
		S.stalled = true;
		S.error = __( 'The server stopped answering. Your progress is saved: click Try again to continue where it stopped.', 'wbcom-demo-installer' ) + ( e && e.message ? ' (' + e.message + ')' : '' );
		render();
	}

	async function loop( until ) {
		if ( S.running ) {
			return;
		}
		S.running = true;
		S.stalled = false;
		let fails = 0;
		try {
			for ( let guard = 0; guard < 5000; guard++ ) {
				try {
					S.status = await api( '/step', {} );
					fails = 0;
					S.error = '';
				} catch ( e ) {
					if ( ++fails > 3 ) {
						stalled( e );
						return;
					}
					await wait( 2000 * fails );
					continue;
				}
				render();
				if ( ! until.includes( S.status.status ) ) {
					break;
				}
				if ( S.status.busy ) {
					await wait( 1500 );
				}
			}
		} finally {
			S.running = false;
		}
		try {
			await refresh();
		} catch ( e ) {
			stalled( e );
		}
	}

	async function refresh() {
		const res = await api( '/site' );
		S.site = res.site;
		S.status = res.status;
		route();
	}

	function route() {
		const st = S.status.status;
		if ( 'building' === st ) {
			S.view = 'building';
			render();
			loop( [ 'building' ] );
		} else if ( 'finishing' === st ) {
			S.view = 'finishing';
			render();
			loop( [ 'finishing' ] );
		} else if ( 'built' === st ) {
			if ( 'finishing' === S.view && S.status.report && S.status.report.finished_at ) {
				go( 'report' );
			} else if ( [ 'finish', 'data' ].includes( cfg.view ) || [ 'finish', 'confirm' ].includes( S.view ) ) {
				cfg.view = '';
				openFinish();
			} else {
				go( 'ready' );
			}
		} else if ( 'finishing' === S.view || 'report' === S.view ) {
			go( 'report' );
		} else {
			if ( ! S.recipe ) {
				S.recipe = S.site.recipes.community ? 'community' : Object.keys( S.site.recipes )[ 0 ] || '';
				S.include = new Set( S.recipe ? S.site.recipes[ S.recipe ].use : [] );
			}
			if ( 'data' === cfg.view ) {
				cfg.view = '';
				go( 'nodata' ); // Tools > Demo data with nothing tracked.
				return;
			}
			go( [ 'start', 'products', 'look', 'review' ].includes( S.view ) ? S.view : 'check' );
		}
	}

	async function openFinish() {
		S.preview = null;
		S.ack = false;
		if ( ! ( S.status.ledger || [] ).some( ( r ) => 'sample' === r.kind ) ) {
			S.mode = 'all'; // Only setup is left: "keep setup" would remove nothing.
		}
		go( 'finish' );
		await loadPreview();
	}

	async function loadPreview() {
		try {
			S.preview = await api( '/finish/preview', { mode: S.mode, custom: S.custom } );
			if ( 'custom' === S.mode && ! Object.keys( S.custom ).length ) {
				S.preview.remove.forEach( ( r ) => ( S.custom[ r.key ] = true ) );
				S.preview.keep.forEach( ( r ) => ( S.custom[ r.key ] = false ) );
			}
		} catch ( e ) {
			S.error = e.message;
		}
		render();
	}

	app.addEventListener( 'input', ( e ) => {
		if ( 'wdi-describe' === e.target.id ) {
			S.describe = e.target.value;
		} else if ( 'wdi-name' === e.target.id ) {
			S.name = e.target.value;
		} else if ( 'wdi-tagline' === e.target.id ) {
			S.tagline = e.target.value;
		}
	} );

	app.addEventListener( 'change', ( e ) => {
		if ( 'wdi-consent' === e.target.id ) {
			S.consent = e.target.checked;
			const build = app.querySelector( '[data-go="build"]' );
			if ( build ) {
				build.disabled = ! S.consent;
			}
		}
	} );

	app.addEventListener( 'click', async ( e ) => {
		const recipe = e.target.closest( '[data-recipe]' );
		if ( recipe ) {
			S.recipe = recipe.dataset.recipe;
			S.include = new Set( S.site.recipes[ S.recipe ].use );
			render();
			app.querySelector( `[data-recipe="${ S.recipe }"]` ).focus();
			return;
		}
		const idea = e.target.closest( '[data-idea]' );
		if ( idea ) {
			applyIdea( S.ideas[ Number( idea.dataset.idea ) ] );
			return;
		}
		const tryChip = e.target.closest( '[data-try]' );
		if ( tryChip ) {
			S.describe = tryChip.textContent;
			render();
			app.querySelector( '#wdi-describe' )?.focus();
			return;
		}
		const btn = e.target.closest( '[data-go]' );
		if ( ! btn || btn.disabled ) {
			return;
		}
		const to = btn.dataset.go;
		if ( 'build' === to ) {
			btn.disabled = true;
			try {
				const id = S.site.identity;
				S.status = await api( '/build', {
					recipe: S.recipe,
					include: [ ...S.include ],
					look: S.look,
					consent: !! S.consent,
					// Only what the owner changed; the current name and tagline stay untouched.
					name: undefined !== S.name && S.name.trim() !== id.name ? S.name.trim() : '',
					tagline: undefined !== S.tagline && S.tagline.trim() !== id.tagline ? S.tagline.trim() : '',
					brief: S.brief || {},
				} );
				S.view = 'building';
				render();
				loop( [ 'building' ] );
			} catch ( err ) {
				S.error = err.message;
				render();
			}
		} else if ( 'ideas' === to ) {
			S.describe = ( app.querySelector( '#wdi-describe' ) || {} ).value ?? S.describe;
			S.thinking = true;
			S.error = '';
			render();
			try {
				const res = await api( '/ideas', { description: S.describe } );
				S.ideas = res.ideas;
				S.thinking = false;
				go( 'ideas' );
			} catch ( err ) {
				S.thinking = false;
				S.error = err.message;
				S.view = 'check';
				render();
			}
		} else if ( 'forget-idea' === to ) {
			S.brief = null;
			S.name = undefined;
			S.tagline = undefined;
			render();
		} else if ( 'install' === to ) {
			installPicked();
		} else if ( 'retry' === to ) {
			S.stalled = false;
			S.error = '';
			refresh().catch( stalled );
		} else if ( 'resume' === to || 'adopt' === to ) {
			btn.disabled = true;
			try {
				S.status = await api( 'resume' === to ? '/finish/resume' : '/adopt', {} );
				if ( 'resume' === to ) {
					S.view = 'finishing';
					render();
					loop( [ 'finishing' ] );
				} else {
					S.view = 'check';
					await refresh();
				}
			} catch ( err ) {
				S.error = err.message;
				render();
			}
		} else if ( 'finish' === to ) {
			openFinish();
		} else if ( 'run-finish' === to ) {
			btn.disabled = true;
			try {
				S.status = await api( '/finish', { mode: S.mode, custom: S.custom } );
				S.view = 'finishing';
				render();
				loop( [ 'finishing' ] );
			} catch ( err ) {
				S.error = err.message;
				render();
			}
		} else {
			go( to );
		}
	} );

	app.addEventListener( 'change', ( e ) => {
		const t = e.target;
		if ( t.dataset.pick ) {
			t.checked ? S.pick.add( t.dataset.pick ) : S.pick.delete( t.dataset.pick );
			render();
			app.querySelector( `[data-pick="${ t.dataset.pick }"]` )?.focus();
		} else if ( t.dataset.prod ) {
			t.checked ? S.include.add( t.dataset.prod ) : S.include.delete( t.dataset.prod );
			render();
		} else if ( 'look' === t.name ) {
			S.look = t.value;
		} else if ( 'mode' === t.name ) {
			S.mode = t.value;
			S.preview = null;
			render();
			loadPreview();
		} else if ( t.dataset.custom ) {
			S.custom[ t.dataset.custom ] = t.checked;
			loadPreview();
		} else if ( 'wdi-ack' === t.id ) {
			S.ack = t.checked;
			render();
		}
	} );

	refresh().catch( ( e ) => {
		app.innerHTML = `<div class="wdi-callout wdi-callout--danger wdi-error" role="alert">${ ic( 'alert' ) }<div>${ esc( ( e && e.message ) || __( 'The wizard could not load. Reload the page.', 'wbcom-demo-installer' ) ) }</div></div>`;
	} );
}() );
