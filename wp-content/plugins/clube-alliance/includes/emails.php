<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Emails {

	private static function remetente() {
		return apply_filters( 'cav_email_remetente', get_bloginfo( 'name' ) );
	}

	private static function headers() {
		return [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . self::remetente() . ' <' . get_option( 'admin_email' ) . '>',
		];
	}

	/** Cabeçalhos públicos, para os e-mails do WordPress que passam pelo mesmo visual. */
	public static function cabecalhos() {
		return self::headers();
	}

	/**
	 * Logo para o cabeçalho do e-mail: o logo do site (Aparência → Personalizar → Identidade do site), se for
	 * PNG, JPG ou GIF (Gmail e Outlook não mostram SVG). Sem logo, o e-mail usa o nome em texto, como o site.
	 */
	private static function logo() {
		$url = '';
		$id  = (int) get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$arq = get_attached_file( $id );
			if ( $arq && preg_match( '/\.(png|jpe?g|gif|webp)$/i', $arq ) ) {
				$url = (string) wp_get_attachment_image_url( $id, 'medium' );
			}
		}
		return (string) apply_filters( 'cav_email_logo', $url );
	}

	/** Casca do e-mail: faixa escura com a marca, cartão branco, rodapé. Só tabelas e estilo em linha, para funcionar em qualquer cliente. */
	public static function molde( $titulo, $corpo, $rodape = '' ) {
		if ( '' === $rodape ) {
			$rodape = 'Você recebeu este e-mail porque solicitou acesso ao clube de vantagens. Em caso de dúvida, responda esta mensagem.';
		}

		$fonte = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;";
		$logo  = self::logo();

		if ( $logo ) {
			$marca = '<img src="' . esc_url( $logo ) . '" alt="Alliance Mogi das Cruzes" height="48" style="display:block;border:0;height:48px;width:auto;max-width:240px">';
		} else {
			$marca = '<div style="' . $fonte . 'font-size:22px;font-weight:700;letter-spacing:6px;text-transform:uppercase;color:#ffffff;line-height:1">Alliance</div>'
				. '<div style="' . $fonte . 'font-size:10px;font-style:italic;letter-spacing:4px;text-transform:uppercase;color:#ffc629;margin-top:8px;line-height:1">Clube de Vantagens</div>';
		}

		$home = esc_url( home_url( '/' ) );

		return '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"></head>'
			. '<body style="margin:0;padding:0;background:#ececec">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ececec"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:580px">'
			// faixa escura com a marca
			. '<tr><td style="background:#141414;padding:26px 32px;border-bottom:4px solid #ffc629"><a href="' . $home . '" style="text-decoration:none;display:inline-block">' . $marca . '</a></td></tr>'
			// cartão
			. '<tr><td style="background:#ffffff;padding:34px 32px 30px;' . $fonte . 'font-size:15px;line-height:1.65;color:#222222">'
			. '<h1 style="' . $fonte . 'font-size:24px;line-height:1.25;font-weight:700;color:#141414;margin:0 0 20px">' . esc_html( $titulo ) . '</h1>'
			. $corpo
			. '</td></tr>'
			// rodapé
			. '<tr><td style="background:#141414;padding:22px 32px;' . $fonte . 'font-size:12px;line-height:1.6;color:#9a9a9a">'
			. esc_html( $rodape )
			. '<br><br><span style="color:#ffc629;letter-spacing:2px;text-transform:uppercase;font-size:11px">Alliance Mogi das Cruzes</span>'
			. '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	private static function botao( $url, $texto ) {
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0"><tr><td style="background:#ffc629;border-radius:2px">'
			. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:15px 30px;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#141414;text-decoration:none">'
			. esc_html( $texto ) . '</a></td></tr></table>'
			. '<p style="margin:0 0 6px;font-size:12px;color:#8a8a8a">Se o botão não abrir, copie este endereço no navegador:<br><a href="' . esc_url( $url ) . '" style="color:#8a8a8a;word-break:break-all">' . esc_html( $url ) . '</a></p>';
	}

	/** Os e-mails que o próprio WordPress manda (redefinir senha, senha alterada) usam o mesmo visual. */
	public static function init() {
		add_filter( 'retrieve_password_notification_email', [ __CLASS__, 'wp_redefinir_senha' ], 10, 4 );
		add_filter( 'password_change_email', [ __CLASS__, 'wp_senha_alterada' ], 10, 3 );
	}

	public static function wp_redefinir_senha( $email, $chave, $login, $user ) {
		$url = network_site_url( 'wp-login.php?login=' . rawurlencode( $login ) . '&key=' . $chave . '&action=rp', 'login' );

		$corpo  = '<p>Olá, ' . esc_html( CAV_Membro::primeiro_nome( $user ) ) . '.</p>';
		$corpo .= '<p>Recebemos um pedido para criar uma nova senha no clube de vantagens da Alliance.</p>';
		$corpo .= '<p><strong>Seu login:</strong> ' . esc_html( $login ) . '</p>';
		$corpo .= self::botao( $url, 'Criar nova senha' );
		$corpo .= '<p>O link vale por poucas horas. Se não foi você que pediu, é só ignorar este e-mail: nada muda na sua conta.</p>';

		$email['subject'] = 'Criar nova senha no clube da Alliance';
		$email['message'] = self::molde( 'Criar nova senha', $corpo, 'Você recebeu este e-mail porque alguém pediu a troca de senha no clube de vantagens da Alliance Mogi das Cruzes.' );
		$email['headers'] = self::headers();

		return $email;
	}

	public static function wp_senha_alterada( $mail, $user, $dados ) {
		$corpo  = '<p>Olá, ' . esc_html( CAV_Membro::primeiro_nome( $user ) ) . '.</p>';
		$corpo .= '<p>A senha da sua conta no clube de vantagens acabou de ser alterada.</p>';
		$corpo .= '<p>Se foi você, não precisa fazer nada. <strong>Se não foi, fale com a recepção da Alliance agora.</strong></p>';

		$mail['subject'] = 'Sua senha do clube foi alterada';
		$mail['message'] = self::molde( 'Senha alterada', $corpo, 'Aviso de segurança do clube de vantagens da Alliance Mogi das Cruzes.' );
		$mail['headers'] = self::headers();

		return $mail;
	}

	/** Link de definição de senha, válido conforme o padrão do WordPress. */
	public static function link_senha( WP_User $user ) {
		$chave = get_password_reset_key( $user );

		if ( is_wp_error( $chave ) ) {
			return wp_login_url();
		}

		return network_site_url(
			'wp-login.php?action=rp&key=' . $chave . '&login=' . rawurlencode( $user->user_login ),
			'login'
		);
	}

	/** Link de pagamento do pedido, já com o nome do aluno na mensagem de WhatsApp. Vazio se não houver link. */
	public static function texto_pagamento( WP_User $user ) {
		$tipo  = CAV_Solicitacoes::tipo( $user->ID );
		$link  = CAV_Config::link_pagamento( $tipo );
		$valor = CAV_Config::formatar( CAV_Config::valor_para( $tipo ) );

		if ( '' === $link ) {
			return '';
		}

		return sprintf(
			'Olá, %1$s! Recebemos o seu pedido para o Clube de Vantagens da Alliance. O clube custa %2$s por %3$s, em até 12x no cartão de crédito. Para pagar é só abrir o link: %4$s Assim que o pagamento for confirmado, o seu acesso é liberado.',
			CAV_Membro::primeiro_nome( $user ),
			$valor,
			CAV_Config::prazo_formatado(),
			$link
		);
	}

	/** E-mail logo após o pedido. Com o link de pagamento, se ele estiver configurado. */
	public static function pedido_recebido( $user ) {
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$tipo  = CAV_Solicitacoes::tipo( $user->ID );
		$link  = CAV_Config::link_pagamento( $tipo );
		$valor = CAV_Config::formatar( CAV_Config::valor_para( $tipo ) );

		$corpo  = '<p>Olá, ' . esc_html( CAV_Membro::primeiro_nome( $user ) ) . '.</p>';
		$corpo .= '<p>Recebemos o seu pedido para entrar no clube de vantagens.</p>';
		$corpo .= '<p>O clube custa <strong>' . esc_html( $valor ) . '</strong> por ' . esc_html( CAV_Config::prazo_formatado() ) . ', em até 12x no cartão de crédito. O acesso é liberado depois que a recepção confirmar o pagamento.</p>';

		if ( $link ) {
			$corpo .= '<p>Para pagar agora, use o botão abaixo:</p>';
			$corpo .= self::botao( $link, 'Pagar o clube' );
			$corpo .= '<p>Se preferir, também dá para pagar na recepção da Alliance.</p>';
		} else {
			$corpo .= '<p>O pagamento é feito na recepção da Alliance.</p>';
		}

		return wp_mail(
			$user->user_email,
			'Recebemos o seu pedido para o clube',
			self::molde( 'Pedido recebido', $corpo ),
			self::headers()
		);
	}

	public static function membro_aprovado( WP_User $user, $validade ) {
		$corpo  = '<p>Seu acesso ao clube de vantagens foi liberado pela recepção.</p>';
		$corpo .= '<p>A partir de agora, é só informar seu CPF no caixa dos estabelecimentos parceiros. Não precisa apresentar carteirinha nem cupom.</p>';

		if ( $validade ) {
			$corpo .= '<p><strong>Válido até ' . esc_html( mysql2date( 'd/m/Y', $validade . ' 00:00:00' ) ) . '.</strong></p>';
		}

		$corpo .= '<p>Defina sua senha para acessar sua área e ver os parceiros:</p>';
		$corpo .= self::botao( self::link_senha( $user ), 'Definir minha senha' );

		return wp_mail(
			$user->user_email,
			'Seu acesso ao clube foi liberado',
			self::molde( 'Acesso liberado', $corpo ),
			self::headers()
		);
	}

	public static function membro_recusado( $email, $nome, $motivo = '' ) {
		$corpo  = '<p>Olá, ' . esc_html( $nome ) . '.</p>';
		$corpo .= '<p>Não conseguimos liberar seu acesso ao clube de vantagens.</p>';

		if ( $motivo ) {
			$corpo .= '<p><strong>Motivo:</strong> ' . esc_html( $motivo ) . '</p>';
		}

		$corpo .= '<p>Se você acha que houve engano, fale com a recepção da academia e resolvemos na hora.</p>';

		return wp_mail(
			$email,
			'Sobre seu pedido de acesso ao clube',
			self::molde( 'Pedido não aprovado', $corpo ),
			self::headers()
		);
	}

	public static function parceiro_aprovado( WP_User $user, $nome_estab, $url_terminal = '' ) {
		$corpo  = '<p>A candidatura de <strong>' . esc_html( $nome_estab ) . '</strong> foi aprovada. Seu estabelecimento já faz parte do clube.</p>';
		$corpo .= '<p>Primeiro passo: criar a sua senha. O link abaixo vale por 7 dias.</p>';
		$corpo .= self::botao( self::link_senha( $user ), 'Definir minha senha' );

		if ( $url_terminal ) {
			$corpo .= '<p>Depois disso, seu terminal fica neste endereço — vale salvar na tela inicial do celular do balcão:</p>';
			$corpo .= '<p><a href="' . esc_url( $url_terminal ) . '">' . esc_html( $url_terminal ) . '</a></p>';
		}

		$corpo .= '<p><strong>Seu login é:</strong> ' . esc_html( $user->user_email ) . '</p>';
		$corpo .= '<p>Na área do parceiro você consulta o CPF do cliente, registra o uso do benefício, acompanha os números e edita os dados do estabelecimento.</p>';

		return wp_mail(
			$user->user_email,
			'Sua candidatura foi aprovada — dados de acesso',
			self::molde( 'Bem-vindo ao clube', $corpo ),
			self::headers()
		);
	}

	/** Aluno que a academia cadastrou pela planilha: ele só precisa criar a senha. */
	public static function aluno_importado( WP_User $user, $validade ) {
		$corpo  = '<p>Olá, ' . esc_html( CAV_Membro::primeiro_nome( $user ) ) . '.</p>';
		$corpo .= '<p>A Alliance liberou o seu acesso ao clube de vantagens. No caixa dos parceiros é só falar o seu CPF, sem carteirinha nem cupom.</p>';
		$corpo .= '<p>Para entrar na sua área, crie a sua senha. O link abaixo vale por 7 dias:</p>';
		$corpo .= self::botao( self::link_senha( $user ), 'Criar minha senha' );
		$corpo .= '<p><strong>Seu login é:</strong> ' . esc_html( $user->user_email ) . '</p>';
		$corpo .= '<p>Se o link vencer, abra a página de entrar e use "Esqueci minha senha".</p>';

		return wp_mail(
			$user->user_email,
			'Seu acesso ao clube de vantagens da Alliance',
			self::molde( 'Seu acesso ao clube', $corpo, 'Você recebeu este e-mail porque a Alliance Mogi das Cruzes cadastrou você no clube de vantagens. Em caso de dúvida, responda esta mensagem.' ),
			self::headers()
		);
	}

	/** Parceiro que a academia cadastrou pela planilha. */
	public static function parceiro_importado( WP_User $user, $nome_estab, $url_terminal = '' ) {
		$corpo  = '<p>Olá, ' . esc_html( $user->display_name ) . '.</p>';
		$corpo .= '<p><strong>' . esc_html( $nome_estab ) . '</strong> já está cadastrado como parceiro no clube de vantagens da Alliance.</p>';
		$corpo .= '<p>Para entrar, crie a sua senha. O link abaixo vale por 7 dias:</p>';
		$corpo .= self::botao( self::link_senha( $user ), 'Criar minha senha' );

		if ( $url_terminal ) {
			$corpo .= '<p>O terminal do caixa fica neste endereço. Vale salvar na tela inicial do celular do balcão:</p>';
			$corpo .= '<p><a href="' . esc_url( $url_terminal ) . '">' . esc_html( $url_terminal ) . '</a></p>';
		}

		$corpo .= '<p><strong>Seu login é:</strong> ' . esc_html( $user->user_email ) . '</p>';
		$corpo .= '<p>Na sua área você consulta o CPF do aluno, registra o uso do desconto, cria e muda as promoções e edita os dados do seu negócio. Se o link vencer, use "Esqueci minha senha" na página de entrar.</p>';

		return wp_mail(
			$user->user_email,
			'Seu negócio no clube de vantagens da Alliance',
			self::molde( 'Bem-vindo ao clube', $corpo, 'Você recebeu este e-mail porque a Alliance Mogi das Cruzes cadastrou você no clube de vantagens. Em caso de dúvida, responda esta mensagem.' ),
			self::headers()
		);
	}

	public static function parceiro_recusado( $email, $nome_estab, $motivo = '' ) {
		$corpo  = '<p>Agradecemos o interesse de <strong>' . esc_html( $nome_estab ) . '</strong> em fazer parte do clube.</p>';
		$corpo .= '<p>Por ora não vamos seguir com a parceria.</p>';

		if ( $motivo ) {
			$corpo .= '<p><strong>Motivo:</strong> ' . esc_html( $motivo ) . '</p>';
		}

		$corpo .= '<p>A lista de parceiros é revista periodicamente. Se quiser, pode se candidatar de novo mais para frente.</p>';

		return wp_mail(
			$email,
			'Sobre sua candidatura ao clube',
			self::molde( 'Candidatura não aprovada', $corpo ),
			self::headers()
		);
	}

	public static function sorteio_ganhador( $sorteio_id, $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		$premio = get_post_meta( $sorteio_id, CAV_Conteudo::META_PREMIO, true ) ?: get_the_title( $sorteio_id );
		$url    = class_exists( 'CAV_Area' ) ? CAV_Area::url( 'sorteios-do-clube' ) : '';

		$corpo  = '<p>Olá, ' . esc_html( CAV_Membro::primeiro_nome( $user ) ) . '.</p>';
		$corpo .= '<p>Você foi sorteado no <strong>' . esc_html( get_the_title( $sorteio_id ) ) . '</strong>. Parabéns!</p>';
		$corpo .= '<p><strong>Prêmio:</strong> ' . esc_html( $premio ) . '</p>';
		$corpo .= '<p>Para retirar, fale com a recepção da academia. Leve um documento com foto.</p>';

		if ( $url ) {
			$corpo .= self::botao( $url, 'Ver o resultado' );
		}

		return wp_mail(
			$user->user_email,
			'Você ganhou o sorteio do clube',
			self::molde( 'Você ganhou!', $corpo ),
			self::headers()
		);
	}
}
