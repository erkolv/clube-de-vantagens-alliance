/* Botão "Entrar com Google". Quem confere a conta é o servidor; aqui só se entrega o token a ele. */
(function () {
	var cfg = window.cavLogin;
	if (!cfg || !cfg.clientId) { return; }

	function enviar(resposta) {
		if (!resposta || !resposta.credential) { return; }
		var f = document.createElement('form');
		f.method = 'post';
		f.action = cfg.url;
		[
			['action', 'cav_google'],
			['cav_nonce', cfg.nonce],
			['credential', resposta.credential],
			['redirect_to', cfg.redirect || '']
		].forEach(function (par) {
			var i = document.createElement('input');
			i.type = 'hidden';
			i.name = par[0];
			i.value = par[1];
			f.appendChild(i);
		});
		document.body.appendChild(f);
		f.submit();
	}

	function iniciar() {
		if (!window.google || !google.accounts || !google.accounts.id) { return false; }
		var alvo = document.getElementById('cav-google-botao');
		if (!alvo) { return true; }
		google.accounts.id.initialize({ client_id: cfg.clientId, callback: enviar, ux_mode: 'popup' });
		google.accounts.id.renderButton(alvo, {
			theme: 'filled_black',
			size: 'large',
			text: 'signin_with',
			shape: 'rectangular',
			logo_alignment: 'left',
			locale: 'pt-BR',
			width: Math.min(360, alvo.offsetWidth || 320)
		});
		return true;
	}

	window.addEventListener('load', function () {
		var tentativas = 0;
		(function tentar() {
			if (iniciar()) { return; }
			tentativas += 1;
			if (tentativas < 40) {
				setTimeout(tentar, 150);
			} else {
				// O script do Google não carregou (bloqueador, sem internet): some com o bloco, o login normal segue.
				var bloco = document.querySelector('.cav-login__google');
				if (bloco) { bloco.hidden = true; }
			}
		})();
	});
})();
