( function () {
	'use strict';

	var botoes = document.querySelectorAll( '.cav-participar' );
	if ( ! botoes.length ) { return; }

	botoes.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			b.disabled = true;
			b.textContent = 'Confirmando…';

			fetch( CAVS.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CAVS.nonce },
				body: JSON.stringify( { sorteio_id: parseInt( b.dataset.sorteio, 10 ) } )
			} )
			.then( function ( r ) {
				return r.json().then( function ( j ) {
					if ( ! r.ok ) { throw new Error( j.message || 'Não foi possível confirmar.' ); }
					return j;
				} );
			} )
			.then( function () {
				var ok = document.createElement( 'span' );
				ok.className = 'cav-item-status is-ok';
				ok.textContent = 'Você está participando';
				b.replaceWith( ok );
			} )
			.catch( function ( e ) {
				b.disabled = false;
				b.textContent = 'Participar';
				var msg = b.parentNode.querySelector( '.cav-item-erro' );
				if ( ! msg ) {
					msg = document.createElement( 'span' );
					msg.className = 'cav-item-erro';
					b.parentNode.appendChild( msg );
				}
				msg.textContent = e.message;
			} );
		} );
	} );
} )();
