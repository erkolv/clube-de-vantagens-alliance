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

	/** Casca simples, legível em qualquer cliente de e-mail. */
	private static function molde( $titulo, $corpo ) {
		$marca = get_bloginfo( 'name' );

		return '<div style="font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#141414;max-width:560px;margin:0 auto;padding:24px">'
			. '<p style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#8A8A8A;margin:0 0 18px">' . esc_html( $marca ) . '</p>'
			. '<h1 style="font-size:22px;line-height:1.25;margin:0 0 18px">' . esc_html( $titulo ) . '</h1>'
			. $corpo
			. '<p style="margin-top:32px;padding-top:18px;border-top:1px solid #E4E4E4;font-size:13px;color:#8A8A8A">'
			. 'Você recebeu este e-mail porque solicitou acesso ao clube de vantagens. Em caso de dúvida, responda esta mensagem.'
			. '</p></div>';
	}

	private static function botao( $url, $texto ) {
		return '<p style="margin:24px 0"><a href="' . esc_url( $url ) . '" '
			. 'style="display:inline-block;background:#141414;color:#fff;text-decoration:none;'
			. 'padding:14px 24px;font-size:13px;letter-spacing:.08em;text-transform:uppercase">'
			. esc_html( $texto ) . '</a></p>';
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
		$corpo .= '<p>Primeiro passo: definir sua senha. O link abaixo vale por 24 horas.</p>';
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
