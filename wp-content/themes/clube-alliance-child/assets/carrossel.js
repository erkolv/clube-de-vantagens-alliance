/*
 * Carrossel de vídeos montado no Elementor (ver carrossel.css).
 * Os vídeos tocam sem som quando aparecem na tela; tocar em um liga o som dele.
 */
( function () {
	'use strict';

	var calmo = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var noEditor = document.body && document.body.classList.contains( 'elementor-editor-active' );

	var SETA_ESQ = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
	var SETA_DIR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
	var SOM = '<svg class="off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M22 9l-6 6M16 9l6 6"/></svg>' +
		'<svg class="on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 010 7M19 5a10 10 0 010 14"/></svg>';

	function botao( classe, rotulo, html ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.className = classe;
		b.setAttribute( 'aria-label', rotulo );
		b.innerHTML = html;
		return b;
	}

	function montar( raiz ) {
		if ( raiz.dataset.cavPronto ) {
			return;
		}

		var trilho = raiz.querySelector( '.cav-carrossel__trilho' );
		if ( ! trilho ) {
			return;
		}

		var itens = [].slice.call( trilho.children ).filter( function ( el ) {
			return el.classList.contains( 'elementor-widget-video' ) && el.querySelector( 'video' );
		} );
		if ( ! itens.length ) {
			return;
		}

		raiz.dataset.cavPronto = '1';
		trilho.setAttribute( 'tabindex', '0' );

		// Controles: vão no contêiner do topo (ou no começo do carrossel).
		var topo = raiz.querySelector( '.cav-carrossel__topo' );
		var ctrl = document.createElement( 'div' );
		ctrl.className = 'cav-carrossel__ctrl';

		var cont = document.createElement( 'span' );
		cont.className = 'cav-carrossel__cont';
		cont.setAttribute( 'aria-live', 'polite' );
		var total = String( itens.length ).padStart( 2, '0' );
		cont.innerHTML = '<b>01</b> / ' + total;

		var ant = botao( 'cav-carrossel__seta', 'Vídeos anteriores', SETA_ESQ );
		var prox = botao( 'cav-carrossel__seta', 'Próximos vídeos', SETA_DIR );
		ctrl.appendChild( cont );
		ctrl.appendChild( ant );
		ctrl.appendChild( prox );
		( topo || raiz ).appendChild( ctrl );

		var barra = document.createElement( 'div' );
		barra.className = 'cav-carrossel__barra';
		barra.innerHTML = '<i></i>';
		trilho.parentNode.insertBefore( barra, trilho.nextSibling );
		var barraI = barra.firstChild;

		var gap = parseFloat( getComputedStyle( trilho ).columnGap ) || 16;
		raiz.style.setProperty( '--cav-gap', gap + 'px' );

		function passo() {
			return itens[ 1 ] ? itens[ 1 ].offsetLeft - itens[ 0 ].offsetLeft : trilho.clientWidth;
		}

		function atualiza() {
			var max = trilho.scrollWidth - trilho.clientWidth;
			var i = Math.min( itens.length - 1, Math.round( trilho.scrollLeft / passo() ) );
			cont.firstChild.textContent = String( i + 1 ).padStart( 2, '0' );
			barraI.style.width = ( max > 0 ? ( trilho.scrollLeft + trilho.clientWidth ) / trilho.scrollWidth * 100 : 100 ) + '%';
			ant.disabled = trilho.scrollLeft <= 2;
			prox.disabled = trilho.scrollLeft >= max - 2;
		}

		[ ant, prox ].forEach( function ( b, n ) {
			b.addEventListener( 'click', function () {
				trilho.scrollBy( { left: passo() * ( n ? 1 : -1 ), behavior: calmo ? 'auto' : 'smooth' } );
			} );
		} );
		trilho.addEventListener( 'scroll', function () {
			window.requestAnimationFrame( atualiza );
		}, { passive: true } );
		window.addEventListener( 'resize', atualiza );

		function somDe( item, ligar ) {
			var v = item.querySelector( 'video' );
			v.muted = ! ligar;
			item.classList.toggle( 'com-som', ligar );
			item.querySelector( '.cav-carrossel__som' ).setAttribute( 'aria-label', ligar ? 'Tirar o som' : 'Ouvir com som' );
			if ( ligar ) {
				v.currentTime = 0;
				v.play().catch( function () {} );
			}
		}

		itens.forEach( function ( item ) {
			var v = item.querySelector( 'video' );
			v.muted = true;
			v.loop = true;
			v.playsInline = true;
			v.setAttribute( 'playsinline', '' );
			v.removeAttribute( 'controls' );
			v.removeAttribute( 'autoplay' );
			if ( ! noEditor ) {
				v.preload = 'none';
			}

			var som = botao( 'cav-carrossel__som', 'Ouvir com som', SOM );
			item.appendChild( som );

			if ( noEditor ) {
				return; // no editor, clicar no vídeo serve para selecionar o widget
			}

			function alterna( e ) {
				e.preventDefault();
				e.stopPropagation();
				var liga = ! item.classList.contains( 'com-som' );
				itens.forEach( function ( o ) {
					if ( o !== item ) {
						somDe( o, false );
					}
				} );
				somDe( item, liga );
			}
			som.addEventListener( 'click', alterna );
			item.addEventListener( 'click', alterna );
		} );

		if ( ! noEditor && 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( es ) {
				es.forEach( function ( e ) {
					var v = e.target.querySelector( 'video' );
					if ( e.isIntersecting && e.intersectionRatio > 0.6 ) {
						if ( ! calmo || e.target.classList.contains( 'com-som' ) ) {
							v.preload = 'auto';
							v.play().catch( function () {} );
						}
					} else {
						v.pause();
						if ( e.target.classList.contains( 'com-som' ) ) {
							somDe( e.target, false );
						}
					}
				} );
			}, { threshold: [ 0, 0.6, 1 ] } );
			itens.forEach( function ( i ) {
				io.observe( i );
			} );
		}

		atualiza();
	}

	function iniciar() {
		[].forEach.call( document.querySelectorAll( '.cav-carrossel' ), montar );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', iniciar );
	} else {
		iniciar();
	}

	// Dentro do editor do Elementor, os elementos são redesenhados a cada mudança.
	window.addEventListener( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/container', function ( $el ) {
				var el = $el[ 0 ];
				var raiz = el.classList.contains( 'cav-carrossel' ) ? el : el.closest( '.cav-carrossel' );
				if ( raiz ) {
					raiz.querySelectorAll( '.cav-carrossel__ctrl, .cav-carrossel__barra, .cav-carrossel__som' ).forEach( function ( n ) {
						n.remove();
					} );
					delete raiz.dataset.cavPronto;
					window.setTimeout( function () {
						montar( raiz );
					}, 50 );
				}
			} );
		}
	} );
}() );
