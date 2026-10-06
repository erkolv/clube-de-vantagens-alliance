( function () {
	'use strict';

	var raiz = document.querySelector( '.cav-terminal' );
	if ( ! raiz ) { return; }

	var input      = raiz.querySelector( '#cav-cpf' );
	var btn        = raiz.querySelector( '#cav-consultar' );
	var btnNovo    = raiz.querySelector( '#cav-novo' );
	var erro       = raiz.querySelector( '#cav-erro' );
	var passoCpf   = raiz.querySelector( '[data-step="cpf"]' );
	var passoRes   = raiz.querySelector( '[data-step="resultado"]' );
	var boxRes     = raiz.querySelector( '#cav-resultado' );
	var boxBen     = raiz.querySelector( '#cav-beneficios' );
	var parceiroId = raiz.getAttribute( 'data-parceiro' );

	var token = null;

	function mascarar( v ) {
		v = v.replace( /\D/g, '' ).slice( 0, 11 );
		if ( v.length > 9 )      { return v.replace( /(\d{3})(\d{3})(\d{3})(\d+)/, '$1.$2.$3-$4' ); }
		else if ( v.length > 6 ) { return v.replace( /(\d{3})(\d{3})(\d+)/, '$1.$2.$3' ); }
		else if ( v.length > 3 ) { return v.replace( /(\d{3})(\d+)/, '$1.$2' ); }
		return v;
	}

	function mostrarErro( msg ) {
		erro.textContent = msg;
		erro.hidden = false;
	}

	function limparErro() {
		erro.hidden = true;
		erro.textContent = '';
	}

	function post( url, dados ) {
		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CAV.nonce
			},
			body: JSON.stringify( dados )
		} ).then( function ( r ) {
			return r.json().then( function ( j ) {
				if ( ! r.ok ) { throw new Error( j.message || 'Não foi possível concluir. Tente de novo.' ); }
				return j;
			} );
		} );
	}

	function irPara( passo ) {
		passoCpf.hidden = ( passo !== 'cpf' );
		passoRes.hidden = ( passo !== 'resultado' );
	}

	function renderResultado( d ) {
		var classe = d.elegivel ? 'is-ok' : 'is-nao';
		var nome   = d.nome ? '<p class="cav-res-nome">' + d.nome + '</p>' : '';
		var det    = d.detalhe ? '<p class="cav-res-detalhe">' + d.detalhe + '</p>' : '';
		boxRes.className = 'cav-resultado ' + classe;
		boxRes.innerHTML = '<p class="cav-res-titulo">' + d.titulo + '</p>' + nome + det;
	}

	function renderBeneficios( lista ) {
		boxBen.innerHTML = '';

		if ( ! lista || ! lista.length ) {
			boxBen.innerHTML = '<p class="cav-vazio">Este estabelecimento ainda não tem benefício ativo cadastrado.</p>';
			return;
		}

		lista.forEach( function ( b ) {
			var el = document.createElement( 'button' );
			el.type = 'button';
			el.className = 'cav-beneficio' + ( b.disponivel ? '' : ' is-bloqueado' ) + ( b.temporario ? ' is-temporario' : '' );
			el.disabled = ! b.disponivel;
			el.innerHTML =
				( b.temporario ? '<span class="cav-ben-tag">' + ( b.vigencia || 'Promoção' ) + '</span>' : '' ) +
				'<span class="cav-ben-titulo">' + b.titulo + '</span>' +
				( b.regra ? '<span class="cav-ben-regra">' + b.regra + '</span>' : '' ) +
				( b.disponivel ? '<span class="cav-ben-acao">Registrar uso</span>'
				               : '<span class="cav-ben-bloqueio">' + ( b.motivo || 'Indisponível' ) + '</span>' );

			el.addEventListener( 'click', function () { registrar( b.id, el ); } );
			boxBen.appendChild( el );
		} );
	}

	function registrar( beneficioId, el ) {
		el.disabled = true;
		el.classList.add( 'is-enviando' );

		post( CAV.uso, { token: token, beneficio_id: beneficioId } )
			.then( function ( d ) {
				el.classList.remove( 'is-enviando' );
				el.classList.add( 'is-feito' );
				el.querySelector( '.cav-ben-acao' ).textContent = 'Registrado às ' + d.hora;
				// Um token vale um registro: encerra a sessão desta consulta.
				token = null;
				boxBen.querySelectorAll( '.cav-beneficio' ).forEach( function ( outro ) {
					if ( outro !== el ) { outro.disabled = true; }
				} );
			} )
			.catch( function ( e ) {
				el.classList.remove( 'is-enviando' );
				el.disabled = false;
				alert( e.message );
			} );
	}

	function consultar() {
		var cpf = input.value.replace( /\D/g, '' );
		limparErro();

		if ( cpf.length !== 11 ) {
			mostrarErro( 'Digite os 11 números do CPF.' );
			return;
		}

		btn.disabled = true;
		btn.textContent = 'Verificando…';

		var dados = { cpf: cpf };
		if ( parceiroId ) { dados.parceiro_id = parceiroId; }

		post( CAV.consulta, dados )
			.then( function ( d ) {
				token = d.token || null;
				renderResultado( d );
				renderBeneficios( d.elegivel ? d.beneficios : [] );
				if ( ! d.elegivel ) { boxBen.innerHTML = ''; }
				irPara( 'resultado' );
			} )
			.catch( function ( e ) {
				mostrarErro( e.message );
			} )
			.finally( function () {
				btn.disabled = false;
				btn.textContent = 'Verificar';
			} );
	}

	input.addEventListener( 'input', function () {
		input.value = mascarar( input.value );
		limparErro();
	} );

	input.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' ) { e.preventDefault(); consultar(); }
	} );

	btn.addEventListener( 'click', consultar );

	btnNovo.addEventListener( 'click', function () {
		input.value = '';
		token = null;
		boxBen.innerHTML = '';
		limparErro();
		irPara( 'cpf' );
		input.focus();
	} );
} )();
