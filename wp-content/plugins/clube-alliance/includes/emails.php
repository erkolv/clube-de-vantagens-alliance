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
	 * PNG, JPG ou GIF (Gmail e Outlook não mostram SVG). Sem logo, o e-mail usa o nome em texto, como o site. Embaixo do logo vai sempre "Clube de Vantagens" em dourado.
	 */
	private static function logo() {
		// O logo da Alliance em PNG (assets/logo-email.png). Se alguém definir outro em Aparência → Personalizar, ele vale no lugar.
		$url = CAV_URL . 'assets/logo-email.png';
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
			$marca = '<img src="' . esc_url( $logo ) . '" alt="Alliance Jiu Jitsu Mogi das Cruzes" width="220" style="display:block;border:0;width:220px;max-width:100%;height:auto">'
				. '<div style="' . $fonte . 'font-size:10px;font-style:italic;letter-spacing:4px;text-transform:uppercase;color:#ffc629;margin-top:10px;line-height:1;width:220px;max-width:100%;text-align:center;padding-left:4px;box-sizing:border-box">Clube de Vantagens</div>';
		} else {
			$marca = '<div style="' . $fonte . 'font-size:22px;font-weight:700;letter-spacing:6px;text-transform:uppercase;color:#ffffff;line-height:1">Alliance</div>'
				. '<div style="' . $fonte . 'font-size:10px;font-style:italic;letter-spacing:4px;text-transform:uppercase;color:#ffc629;margin-top:8px;line-height:1">Clube de Vantagens</div>';
		}

		$home = esc_url( home_url( '/' ) );

		return '<!doctype html><!--cav-email--><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><style>.cav-corpo a{color:#141414}</style></head>'
			. '<body style="margin:0;padding:0;background:#ececec">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ececec"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:580px">'
			// faixa escura com a marca
			. '<tr><td style="background:#141414;padding:26px 32px;border-bottom:4px solid #ffc629"><a href="' . $home . '" style="text-decoration:none;display:inline-block">' . $marca . '</a></td></tr>'
			// cartão
			. '<tr><td class="cav-corpo" style="background:#ffffff;padding:34px 32px 30px;' . $fonte . 'font-size:15px;line-height:1.65;color:#222222">'
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

	public static function botao( $url, $texto ) {
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0"><tr><td style="background:#ffc629;border-radius:2px">'
			. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:15px 30px;font-family:\'Helvetica Neue\',Helvetica,Arial,sans-serif;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#141414;text-decoration:none">'
			. esc_html( $texto ) . '</a></td></tr></table>'
			. '<p style="margin:0 0 6px;font-size:12px;color:#8a8a8a">Se o botão não abrir, copie este endereço no navegador:<br><a href="' . esc_url( $url ) . '" style="color:#8a8a8a;word-break:break-all">' . esc_html( $url ) . '</a></p>';
	}

	/** Os e-mails que o próprio WordPress manda (redefinir senha, senha alterada) usam o mesmo visual. */
	public static function init() {
		add_filter( 'retrieve_password_notification_email', [ __CLASS__, 'wp_redefinir_senha' ], 10, 4 );
		add_filter( 'password_change_email', [ __CLASS__, 'wp_senha_alterada' ], 10, 3 );
		// Rede de segurança: qualquer outro e-mail que sair do site (WordPress, plugins, teste) ganha o mesmo visual.
		add_filter( 'wp_mail', [ __CLASS__, 'embrulhar' ], 50 );
	}

	/** Coloca no modelo da Alliance o e-mail que ainda não está nele. Não mexe em e-mail que já veio com página HTML completa. */
	public static function embrulhar( $args ) {
		$msg = isset( $args['message'] ) ? (string) $args['message'] : '';
		if ( '' === $msg || false !== strpos( $msg, '<!--cav-email-->' ) ) {
			return $args;
		}

		$cab = $args['headers'] ?? [];
		if ( is_string( $cab ) ) {
			$cab = preg_split( '/\r\n|\r|\n/', $cab );
		}
		$cab  = array_values( array_filter( (array) $cab, 'is_string' ) );
		$html = false;
		foreach ( $cab as $linha ) {
			if ( 0 === stripos( trim( $linha ), 'content-type:' ) && false !== stripos( $linha, 'text/html' ) ) {
				$html = true;
			}
		}

		if ( $html ) {
			if ( false !== stripos( $msg, '<html' ) ) {
				return $args; // página completa de outro plugin: não mexe
			}
			$corpo = $msg;
		} else {
			// Texto puro: vira parágrafos, com os endereços clicáveis. O WordPress escreve links entre < >.
			$limpo  = preg_replace( '/<(https?:\/\/[^>\s]+)>/i', '$1', $msg );
			$blocos = preg_split( '/\n\s*\n/', trim( str_replace( "\r", '', $limpo ) ) );
			$corpo  = '';
			foreach ( $blocos as $b ) {
				$corpo .= '<p>' . nl2br( make_clickable( esc_html( $b ) ) ) . '</p>';
			}
			$cab[] = 'Content-Type: text/html; charset=UTF-8';
		}

		$titulo = trim( preg_replace( '/^\[[^\]]*\]\s*/u', '', wp_strip_all_tags( (string) ( $args['subject'] ?? '' ) ) ) );

		$args['message'] = self::molde( '' !== $titulo ? $titulo : get_bloginfo( 'name' ), $corpo, 'Mensagem automática do clube de vantagens da Alliance Mogi das Cruzes.' );
		$args['headers'] = $cab;

		return $args;
	}

	public static function wp_redefinir_senha( $email, $chave, $login, $user ) {
		$url = network_site_url( 'wp-login.php?login=' . rawurlencode( $login ) . '&key=' . $chave . '&action=rp', 'login' );

		$m = CAV_Modelos::montar( 'wp_redefinir_senha', [
			'nome'  => $user instanceof WP_User ? CAV_Membro::primeiro_nome( $user ) : '',
			'login' => $login,
		], $url );

		$email['subject'] = $m['assunto'];
		$email['message'] = $m['html'];
		$email['headers'] = self::headers();

		return $email;
	}

	public static function wp_senha_alterada( $mail, $user, $dados ) {
		// O WordPress entrega o usuário como lista de dados, não como objeto.
		if ( is_array( $user ) && ! empty( $user['ID'] ) ) {
			$user = get_userdata( (int) $user['ID'] );
		}

		$m = CAV_Modelos::montar( 'wp_senha_alterada', [
			'nome' => $user instanceof WP_User ? CAV_Membro::primeiro_nome( $user ) : '',
		] );

		$mail['subject'] = $m['assunto'];
		$mail['message'] = $m['html'];
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

		$tipo = CAV_Solicitacoes::tipo( $user->ID );
		$link = CAV_Config::link_pagamento( $tipo );

		$m = CAV_Modelos::montar( 'pedido_recebido', [
			'nome'  => CAV_Membro::primeiro_nome( $user ),
			'valor' => CAV_Config::formatar( CAV_Config::valor_para( $tipo ) ),
			'prazo' => CAV_Config::prazo_formatado(),
		], (string) $link );

		return wp_mail( $user->user_email, $m['assunto'], $m['html'], self::headers() );
	}

	public static function membro_aprovado( WP_User $user, $validade ) {
		$m = CAV_Modelos::montar( 'membro_aprovado', [
			'nome'     => CAV_Membro::primeiro_nome( $user ),
			'validade' => $validade ? mysql2date( 'd/m/Y', $validade . ' 00:00:00' ) : '',
			'login'    => $user->user_email,
		], self::link_senha( $user ) );

		return wp_mail( $user->user_email, $m['assunto'], $m['html'], self::headers() );
	}

	public static function membro_recusado( $email, $nome, $motivo = '' ) {
		$m = CAV_Modelos::montar( 'membro_recusado', [ 'nome' => $nome, 'motivo' => $motivo ] );

		return wp_mail( $email, $m['assunto'], $m['html'], self::headers() );
	}

	/** Passo a passo para o parceiro que acabou de ser cadastrado. Só HTML simples, para qualquer e-mail abrir igual. */
	public static function passo_a_passo_parceiro( $url_terminal = '' ) {
		$entrar = home_url( '/entrar/' );
		$negocio = CAV_AreaParceiro::url( 'meu-negocio' ) ?: home_url( '/' );
		$promos  = CAV_AreaParceiro::url( 'minhas-promocoes' ) ?: home_url( '/' );

		$passos = [
			[ 'Crie a sua senha', 'Clique no botão acima e escolha uma senha. O link vale por 7 dias; se vencer, use "Esqueci minha senha" na página de entrar.' ],
			[ 'Entre no site', 'Abra <a href="' . esc_url( $entrar ) . '" style="color:#141414">' . esc_html( $entrar ) . '</a> e entre com o seu e-mail e a senha que você acabou de criar.' ],
			[ 'Preencha os dados do seu negócio', 'No menu, abra <a href="' . esc_url( $negocio ) . '" style="color:#141414"><strong>Meu negócio</strong></a>. Coloque a foto e o logo, uma frase de resumo, a descrição, o WhatsApp, o Instagram, o endereço e o horário, e clique em <strong>Salvar dados do negócio</strong>. É essa página que os alunos veem antes de ir até você. O nome e a categoria quem muda é a Alliance.' ],
			[ 'Confira as suas promoções', 'Em <a href="' . esc_url( $promos ) . '" style="color:#141414"><strong>Promoções</strong></a> você cria e edita o que oferece aos alunos: o nome, a regra que o seu caixa vai ler (dias, limites, o que está incluído), quantas vezes cada aluno pode usar e as datas. Só aparece para os alunos a promoção marcada como <strong>Promoção no ar</strong>.' ],
			[ 'Use o Terminal no caixa', 'Quando o aluno chegar, abra o <strong>Terminal</strong>, digite o CPF dele e veja na hora se ele faz parte do clube e qual desconto vale. Depois toque em <strong>Registrar uso</strong>. Não precisa de carteirinha nem cupom.' ],
		];

		$html = '<p style="margin:26px 0 10px"><strong style="font-size:16px">Como começar, em 5 passos</strong></p>';
		foreach ( $passos as $i => $p ) {
			$html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 14px;width:100%"><tr>'
				. '<td valign="top" width="34" style="padding-top:2px"><div style="width:26px;height:26px;line-height:26px;text-align:center;background:#ffc629;color:#141414;font-weight:700;font-size:13px;border-radius:13px">' . ( $i + 1 ) . '</div></td>'
				. '<td valign="top" style="font-size:14px;line-height:1.55"><strong>' . esc_html( $p[0] ) . '</strong><br>' . $p[1] . '</td>'
				. '</tr></table>';
		}
		if ( $url_terminal ) {
			$html .= '<p style="margin-top:20px">O terminal do caixa fica neste endereço. Vale salvar na tela inicial do celular do balcão:<br><a href="' . esc_url( $url_terminal ) . '" style="color:#141414">' . esc_html( $url_terminal ) . '</a></p>';
		}
		$html .= '<p style="font-size:13px;color:#6a6a6a">Ficou com dúvida em algum passo? É só responder este e-mail.</p>';

		return $html;
	}

	public static function parceiro_aprovado( WP_User $user, $nome_estab, $url_terminal = '' ) {
		$m = CAV_Modelos::montar( 'parceiro_aprovado', [
			'estabelecimento' => $nome_estab,
			'login'           => $user->user_email,
		], self::link_senha( $user ), [ 'passo_a_passo' => self::passo_a_passo_parceiro( $url_terminal ) ] );

		return wp_mail( $user->user_email, $m['assunto'], $m['html'], self::headers() );
	}

	/** Aluno que a academia cadastrou pela planilha: ele só precisa criar a senha. */
	public static function aluno_importado( WP_User $user, $validade ) {
		$m = CAV_Modelos::montar( 'aluno_importado', [
			'nome'  => CAV_Membro::primeiro_nome( $user ),
			'login' => $user->user_email,
		], self::link_senha( $user ) );

		return wp_mail( $user->user_email, $m['assunto'], $m['html'], self::headers() );
	}

	/** Parceiro que a academia cadastrou pela planilha. */
	public static function parceiro_importado( WP_User $user, $nome_estab, $url_terminal = '' ) {
		$m = CAV_Modelos::montar( 'parceiro_importado', [
			'nome'            => $user->display_name,
			'estabelecimento' => $nome_estab,
			'login'           => $user->user_email,
		], self::link_senha( $user ), [ 'passo_a_passo' => self::passo_a_passo_parceiro( $url_terminal ) ] );

		return wp_mail( $user->user_email, $m['assunto'], $m['html'], self::headers() );
	}

	/** Logo depois que o estabelecimento envia a candidatura: confirma que chegou e o que acontece a seguir. */
	public static function candidatura_recebida( $email, $nome_estab, $responsavel, $beneficio ) {
		$m = CAV_Modelos::montar( 'candidatura_recebida', [
			'responsavel'     => $responsavel,
			'estabelecimento' => $nome_estab,
			'proposta'        => $beneficio,
		] );

		return wp_mail( $email, $m['assunto'], $m['html'], self::headers() );
	}

	public static function parceiro_recusado( $email, $nome_estab, $motivo = '' ) {
		$m = CAV_Modelos::montar( 'parceiro_recusado', [ 'estabelecimento' => $nome_estab, 'motivo' => $motivo ] );

		return wp_mail( $email, $m['assunto'], $m['html'], self::headers() );
	}

	public static function sorteio_ganhador( $sorteio_id, $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		$url = class_exists( 'CAV_Area' ) ? CAV_Area::url( 'sorteios-do-clube' ) : '';

		$m = CAV_Modelos::montar( 'sorteio_ganhador', [
			'nome'    => CAV_Membro::primeiro_nome( $user ),
			'sorteio' => get_the_title( $sorteio_id ),
			'premio'  => get_post_meta( $sorteio_id, CAV_Conteudo::META_PREMIO, true ) ?: get_the_title( $sorteio_id ),
		], (string) $url );

		return wp_mail( $user->user_email, $m['assunto'], $m['html'], self::headers() );
	}
}
