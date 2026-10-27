<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Modelos de e-mail: o texto de cada e-mail que o clube manda, editável no painel
 * (Clube → Modelos de e-mail). O visual (faixa com logo, botão, rodapé) é um só, em CAV_Emails::molde.
 *
 * O que a pessoa edita é só texto, com marcadores como {nome} e {valor}. Nada de HTML:
 * tudo passa por esc_html, então não dá para quebrar o e-mail nem injetar código.
 * O que não for editado usa o texto original, que fica aqui em definicoes().
 */
class CAV_Modelos {

	const OPT = 'cav_email_modelos';

	/** Campos de texto que um modelo pode ter, na ordem em que aparecem na tela. */
	const CAMPOS = [ 'assunto', 'titulo', 'corpo', 'com_link', 'sem_link', 'botao', 'rodape' ];

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 26 );
		add_action( 'admin_post_cav_modelo_salvar', [ __CLASS__, 'salvar' ] );
	}

	/* ---------------------------- Os modelos ---------------------------- */

	private static function rodape_padrao() {
		return 'Você recebeu este e-mail porque solicitou acesso ao clube de vantagens. Em caso de dúvida, responda esta mensagem.';
	}

	/**
	 * Textos originais. Chave = nome interno do e-mail.
	 * 'marcadores' lista o que dá para usar entre chaves; 'botao' e 'passo_a_passo' são blocos, não texto.
	 */
	public static function definicoes() {
		$cadastrado_pela_academia = 'Você recebeu este e-mail porque a Alliance Mogi das Cruzes cadastrou você no clube de vantagens. Em caso de dúvida, responda esta mensagem.';

		return [
			'pedido_recebido' => [
				'nome'       => 'Pedido recebido',
				'quando'     => 'Logo depois que a pessoa preenche "Quero fazer parte".',
				'marcadores' => [
					'nome'  => 'Primeiro nome da pessoa',
					'valor' => 'Valor do clube para ela (R$ 300,00 ou R$ 360,00)',
					'prazo' => 'Prazo do clube (12 meses)',
					'botao' => 'Botão de pagamento (só aparece se houver link de pagamento)',
				],
				'assunto'    => 'Recebemos o seu pedido para o clube',
				'titulo'     => 'Pedido recebido',
				'corpo'      => "Olá, {nome}.\n\nRecebemos o seu pedido para entrar no clube de vantagens.\n\nO clube custa **{valor}** por {prazo}, em até 12x no cartão de crédito. O acesso é liberado depois que a recepção confirmar o pagamento.",
				'com_link'   => "Para pagar agora, use o botão abaixo:\n\n{botao}\n\nSe preferir, também dá para pagar na recepção da Alliance.",
				'sem_link'   => 'O pagamento é feito na recepção da Alliance.',
				'botao'      => 'Pagar o clube',
				'rodape'     => self::rodape_padrao(),
			],
			'membro_aprovado' => [
				'nome'       => 'Acesso liberado',
				'quando'     => 'Quando a recepção aprova o pedido de um participante.',
				'marcadores' => [
					'nome'     => 'Primeiro nome da pessoa',
					'validade' => 'Data em que o acesso vence (a frase some se não houver data)',
					'login'    => 'E-mail que a pessoa usa para entrar',
					'botao'    => 'Botão para criar a senha',
				],
				'assunto'    => 'Seu acesso ao clube foi liberado',
				'titulo'     => 'Acesso liberado',
				'corpo'      => "Seu acesso ao clube de vantagens foi liberado pela recepção.\n\nA partir de agora, é só informar seu CPF no caixa dos estabelecimentos parceiros. Não precisa apresentar carteirinha nem cupom.\n\n**Válido até {validade}.**\n\nDefina sua senha para acessar sua área e ver os parceiros:\n\n{botao}",
				'botao'      => 'Definir minha senha',
				'rodape'     => self::rodape_padrao(),
			],
			'membro_recusado' => [
				'nome'       => 'Pedido não aprovado',
				'quando'     => 'Quando a recepção recusa o pedido de um participante.',
				'marcadores' => [
					'nome'   => 'Nome da pessoa',
					'motivo' => 'Motivo escrito pela recepção (a frase some se ficar em branco)',
				],
				'assunto'    => 'Sobre seu pedido de acesso ao clube',
				'titulo'     => 'Pedido não aprovado',
				'corpo'      => "Olá, {nome}.\n\nNão conseguimos liberar seu acesso ao clube de vantagens.\n\n**Motivo:** {motivo}\n\nSe você acha que houve engano, fale com a recepção da academia e resolvemos na hora.",
				'rodape'     => self::rodape_padrao(),
			],
			'aluno_importado' => [
				'nome'       => 'Aluno cadastrado pela academia',
				'quando'     => 'Quando a academia cadastra alunos de uma vez, pela planilha.',
				'marcadores' => [
					'nome'  => 'Primeiro nome da pessoa',
					'login' => 'E-mail que a pessoa usa para entrar',
					'botao' => 'Botão para criar a senha',
				],
				'assunto'    => 'Seu acesso ao clube de vantagens da Alliance',
				'titulo'     => 'Seu acesso ao clube',
				'corpo'      => "Olá, {nome}.\n\nA Alliance liberou o seu acesso ao clube de vantagens. No caixa dos parceiros é só falar o seu CPF, sem carteirinha nem cupom.\n\nPara entrar na sua área, crie a sua senha. O link abaixo vale por 7 dias:\n\n{botao}\n\n**Seu login é:** {login}\n\nSe o link vencer, abra a página de entrar e use \"Esqueci minha senha\".",
				'botao'      => 'Criar minha senha',
				'rodape'     => $cadastrado_pela_academia,
			],
			'parceiro_aprovado' => [
				'nome'       => 'Parceiro aprovado',
				'quando'     => 'Quando a academia aprova a candidatura de um estabelecimento.',
				'marcadores' => [
					'estabelecimento' => 'Nome do estabelecimento',
					'login'           => 'E-mail que o parceiro usa para entrar',
					'botao'           => 'Botão para criar a senha',
					'passo_a_passo'   => 'Os 5 passos de como começar (bloco pronto, não dá para editar o texto dele aqui)',
				],
				'assunto'    => 'Sua candidatura foi aprovada — dados de acesso',
				'titulo'     => 'Bem-vindo ao clube',
				'corpo'      => "A candidatura de **{estabelecimento}** foi aprovada. Seu estabelecimento já faz parte do clube.\n\nPara começar, crie a sua senha:\n\n{botao}\n\n**Seu login é:** {login}\n\n{passo_a_passo}",
				'botao'      => 'Criar minha senha',
				'rodape'     => self::rodape_padrao(),
			],
			'parceiro_importado' => [
				'nome'       => 'Parceiro cadastrado pela academia',
				'quando'     => 'Quando a academia cadastra parceiros de uma vez, pela planilha.',
				'marcadores' => [
					'nome'            => 'Nome do responsável',
					'estabelecimento' => 'Nome do estabelecimento',
					'login'           => 'E-mail que o parceiro usa para entrar',
					'botao'           => 'Botão para criar a senha',
					'passo_a_passo'   => 'Os 5 passos de como começar (bloco pronto, não dá para editar o texto dele aqui)',
				],
				'assunto'    => 'Seu negócio no clube de vantagens da Alliance',
				'titulo'     => 'Bem-vindo ao clube',
				'corpo'      => "Olá, {nome}.\n\n**{estabelecimento}** já está cadastrado como parceiro no clube de vantagens da Alliance.\n\nPara começar, crie a sua senha:\n\n{botao}\n\n**Seu login é:** {login}\n\n{passo_a_passo}",
				'botao'      => 'Criar minha senha',
				'rodape'     => $cadastrado_pela_academia,
			],
			'candidatura_recebida' => [
				'nome'       => 'Candidatura de parceiro recebida',
				'quando'     => 'Logo depois que um estabelecimento envia a candidatura no site.',
				'marcadores' => [
					'responsavel'     => 'Nome do responsável pelo estabelecimento',
					'estabelecimento' => 'Nome do estabelecimento',
					'proposta'        => 'O benefício que o estabelecimento propôs, como ele escreveu',
				],
				'assunto'    => 'Recebemos a candidatura de {estabelecimento}',
				'titulo'     => 'Candidatura recebida',
				'corpo'      => "Olá, {responsavel}.\n\nRecebemos a candidatura de **{estabelecimento}** para o clube de vantagens da Alliance. Obrigado pelo interesse.\n\n**O que você propôs:**\n{proposta}\n\nA recepção da Alliance vai analisar o pedido. Quando houver uma resposta, você recebe outro e-mail neste endereço: se for aprovada, com o link para criar a sua senha e o endereço do terminal do caixa.\n\nSe precisar corrigir alguma informação, é só responder esta mensagem.",
				'rodape'     => 'Você recebeu este e-mail porque a candidatura de {estabelecimento} foi enviada no site do clube de vantagens da Alliance Mogi das Cruzes.',
			],
			'parceiro_recusado' => [
				'nome'       => 'Candidatura de parceiro não aprovada',
				'quando'     => 'Quando a academia recusa a candidatura de um estabelecimento.',
				'marcadores' => [
					'estabelecimento' => 'Nome do estabelecimento',
					'motivo'          => 'Motivo escrito pela academia (a frase some se ficar em branco)',
				],
				'assunto'    => 'Sobre sua candidatura ao clube',
				'titulo'     => 'Candidatura não aprovada',
				'corpo'      => "Agradecemos o interesse de **{estabelecimento}** em fazer parte do clube.\n\nPor ora não vamos seguir com a parceria.\n\n**Motivo:** {motivo}\n\nA lista de parceiros é revista periodicamente. Se quiser, pode se candidatar de novo mais para frente.",
				'rodape'     => self::rodape_padrao(),
			],
			'sorteio_ganhador' => [
				'nome'       => 'Ganhador do sorteio',
				'quando'     => 'Quando a administração faz o sorteio e escolhe o ganhador.',
				'marcadores' => [
					'nome'    => 'Primeiro nome do ganhador',
					'sorteio' => 'Nome do sorteio',
					'premio'  => 'Prêmio',
					'botao'   => 'Botão para ver o resultado',
				],
				'assunto'    => 'Você ganhou o sorteio do clube',
				'titulo'     => 'Você ganhou!',
				'corpo'      => "Olá, {nome}.\n\nVocê foi sorteado no **{sorteio}**. Parabéns!\n\n**Prêmio:** {premio}\n\nPara retirar, fale com a recepção da academia. Leve um documento com foto.\n\n{botao}",
				'botao'      => 'Ver o resultado',
				'rodape'     => self::rodape_padrao(),
			],
			'wp_redefinir_senha' => [
				'nome'       => 'Criar nova senha (esqueci a senha)',
				'quando'     => 'Quando alguém usa "Esqueci minha senha".',
				'marcadores' => [
					'nome'  => 'Primeiro nome da pessoa',
					'login' => 'Login da pessoa',
					'botao' => 'Botão para criar a nova senha',
				],
				'assunto'    => 'Criar nova senha no clube da Alliance',
				'titulo'     => 'Criar nova senha',
				'corpo'      => "Olá, {nome}.\n\nRecebemos um pedido para criar uma nova senha no clube de vantagens da Alliance.\n\n**Seu login:** {login}\n\n{botao}\n\nO link vale por 7 dias. Se não foi você que pediu, é só ignorar este e-mail: nada muda na sua conta.",
				'botao'      => 'Criar nova senha',
				'rodape'     => 'Você recebeu este e-mail porque alguém pediu a troca de senha no clube de vantagens da Alliance Mogi das Cruzes.',
			],
			'wp_senha_alterada' => [
				'nome'       => 'Senha alterada',
				'quando'     => 'Quando a senha de uma conta é trocada. É um aviso de segurança.',
				'marcadores' => [
					'nome' => 'Primeiro nome da pessoa',
				],
				'assunto'    => 'Sua senha do clube foi alterada',
				'titulo'     => 'Senha alterada',
				'corpo'      => "Olá, {nome}.\n\nA senha da sua conta no clube de vantagens acabou de ser alterada.\n\nSe foi você, não precisa fazer nada. **Se não foi, fale com a recepção da Alliance agora.**",
				'rodape'     => 'Aviso de segurança do clube de vantagens da Alliance Mogi das Cruzes.',
			],
		];
	}

	public static function definicao( $chave ) {
		$todas = self::definicoes();
		return $todas[ $chave ] ?? null;
	}

	/** Dados de exemplo para a prévia e para o e-mail de teste. */
	public static function exemplo( $chave ) {
		$link = home_url( '/' );
		$vars = [
			'nome'            => 'Camila',
			'valor'           => 'R$ 300,00',
			'prazo'           => '12 meses',
			'validade'        => wp_date( 'd/m/Y', strtotime( '+12 months' ) ),
			'login'           => 'camila@exemplo.com.br',
			'motivo'          => 'Não encontramos o pagamento do clube.',
			'estabelecimento' => 'Barbearia Corte Reto',
			'responsavel'     => 'Rafael',
			'proposta'        => "10% de desconto em todos os serviços,\nde segunda a quinta.",
			'sorteio'         => 'Sorteio de novembro',
			'premio'          => 'Mochila Multibag Alliance JJ Team',
		];

		$blocos = [];
		if ( false !== strpos( $chave, 'parceiro_aprovado' ) || false !== strpos( $chave, 'parceiro_importado' ) ) {
			$blocos['passo_a_passo'] = CAV_Emails::passo_a_passo_parceiro( home_url( '/terminal/' ) );
		}

		return [ $vars, $link, $blocos ];
	}

	/* --------------------------- Texto efetivo --------------------------- */

	private static function salvos() {
		$o = get_option( self::OPT, [] );
		return is_array( $o ) ? $o : [];
	}

	/** Os campos que valem agora: o que foi editado, ou o original. */
	public static function campos( $chave ) {
		$def = self::definicao( $chave );
		if ( ! $def ) {
			return [];
		}

		$salvo = self::salvos()[ $chave ] ?? [];
		$saida = [];
		foreach ( self::CAMPOS as $c ) {
			if ( ! isset( $def[ $c ] ) ) {
				continue;
			}
			$v            = isset( $salvo[ $c ] ) && is_string( $salvo[ $c ] ) ? trim( $salvo[ $c ] ) : '';
			$saida[ $c ] = '' !== $v ? $v : $def[ $c ];
		}
		return $saida;
	}

	public static function editado( $chave ) {
		$def = self::definicao( $chave );
		if ( ! $def ) {
			return false;
		}
		foreach ( self::campos( $chave ) as $c => $v ) {
			if ( trim( $v ) !== trim( $def[ $c ] ) ) {
				return true;
			}
		}
		return false;
	}

	/* ----------------------------- Montagem ----------------------------- */

	/** Troca {marcador} por valor, em texto puro (assunto, título, rodapé). */
	private static function simples( $modelo, array $vars ) {
		$t = preg_replace_callback( '/\{([a-z_]+)\}/', function ( $m ) use ( $vars ) {
			return array_key_exists( $m[1], $vars ) ? (string) $vars[ $m[1] ] : $m[0];
		}, (string) $modelo );

		return trim( str_replace( '**', '', $t ) );
	}

	/**
	 * Monta o e-mail pronto.
	 *
	 * @param string $chave     Qual modelo.
	 * @param array  $vars      Valores dos marcadores de texto (nome, valor...).
	 * @param string $botao_url Endereço do botão; vazio se não houver botão neste envio.
	 * @param array  $blocos    HTML pronto para marcadores de bloco (passo_a_passo).
	 * @return array{assunto:string,html:string}|null
	 */
	public static function montar( $chave, array $vars, $botao_url = '', array $blocos = [] ) {
		$def = self::definicao( $chave );
		if ( ! $def ) {
			return null;
		}

		$cfg   = self::campos( $chave );
		$corpo = $cfg['corpo'];

		if ( isset( $def['sem_link'] ) ) {
			$corpo .= "\n\n" . ( '' !== $botao_url ? $cfg['com_link'] : $cfg['sem_link'] );
		}

		// O botão leva o link de criar a senha ou de pagar: se alguém tirou o {botao} do texto, ele volta no fim.
		if ( '' !== $botao_url && isset( $def['marcadores']['botao'] ) && false === strpos( $corpo, '{botao}' ) ) {
			$corpo .= "\n\n{botao}";
		}

		$botao = ( '' !== $botao_url && isset( $cfg['botao'] ) ) ? CAV_Emails::botao( $botao_url, self::simples( $cfg['botao'], $vars ) ) : '';
		$html  = self::corpo_html( $corpo, $vars, array_keys( $def['marcadores'] ), $botao, $blocos );

		return [
			'assunto' => self::simples( $cfg['assunto'], $vars ),
			'html'    => CAV_Emails::molde( self::simples( $cfg['titulo'], $vars ), $html, self::simples( $cfg['rodape'], $vars ) ),
		];
	}

	/** Texto simples vira parágrafos: linha em branco separa, **assim** é negrito, {marcador} vira o valor. */
	private static function corpo_html( $modelo, array $vars, array $conhecidos, $botao_html, array $blocos ) {
		$modelo = str_replace( [ "\r\n", "\r" ], "\n", (string) $modelo );
		// Os blocos (botão, passo a passo) sempre ficam sozinhos, mesmo que alguém escreva colado no texto.
		$modelo = preg_replace( '/[ \t]*\{(botao|passo_a_passo)\}[ \t]*/', "\n\n{\$1}\n\n", $modelo );

		$html = '';
		foreach ( preg_split( '/\n\s*\n/', trim( $modelo ) ) as $p ) {
			$p = trim( $p );
			if ( '' === $p ) {
				continue;
			}
			if ( '{botao}' === $p ) {
				$html .= $botao_html;
				continue;
			}
			if ( '{passo_a_passo}' === $p ) {
				$html .= $blocos['passo_a_passo'] ?? '';
				continue;
			}

			// Se o parágrafo usa uma informação que não existe agora (sem motivo, sem validade), ele some.
			$some = false;
			if ( preg_match_all( '/\{([a-z_]+)\}/', $p, $achados ) ) {
				foreach ( $achados[1] as $k ) {
					if ( in_array( $k, $conhecidos, true ) && '' === trim( (string) ( $vars[ $k ] ?? '' ) ) ) {
						$some = true;
					}
				}
			}
			if ( $some ) {
				continue;
			}

			$t = esc_html( $p );
			$t = preg_replace( '/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $t );
			$t = preg_replace_callback( '/\{([a-z_]+)\}/', function ( $m ) use ( $vars, $conhecidos ) {
				return in_array( $m[1], $conhecidos, true ) ? esc_html( (string) ( $vars[ $m[1] ] ?? '' ) ) : $m[0];
			}, $t );

			// Só endereços de site viram link (e-mail fica como texto).
			$t = preg_replace( '#(https?://[^\s<]+[^\s<.,;:!?)])#i', '<a href="$1">$1</a>', nl2br( $t ) );

			$html .= '<p>' . $t . '</p>';
		}

		return $html;
	}

	/* ------------------------------ Admin ------------------------------ */

	public static function menu() {
		add_submenu_page( 'cav-relatorio', 'Modelos de e-mail', 'Modelos de e-mail', 'cav_gerenciar', 'cav-modelos', [ __CLASS__, 'tela' ] );
	}

	private static function url( $chave = '', array $extra = [] ) {
		$args = [ 'page' => 'cav-modelos' ];
		if ( $chave ) {
			$args['modelo'] = $chave;
		}
		return add_query_arg( array_merge( $args, $extra ), admin_url( 'admin.php' ) );
	}

	private static function rotulo_campo( $campo, $def ) {
		$r = [
			'assunto'  => 'Assunto do e-mail',
			'titulo'   => 'Título (aparece no alto do e-mail)',
			'corpo'    => 'Mensagem',
			'com_link' => 'Mensagem quando há link de pagamento',
			'sem_link' => 'Mensagem quando não há link de pagamento',
			'botao'    => 'Texto do botão',
			'rodape'   => 'Frase do rodapé',
		];
		return $r[ $campo ];
	}

	public static function tela() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.', '', [ 'response' => 403 ] );
		}

		$chave = isset( $_GET['modelo'] ) ? sanitize_key( wp_unslash( $_GET['modelo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $chave && self::definicao( $chave ) ) {
			self::tela_edicao( $chave );
			return;
		}
		self::tela_lista();
	}

	private static function tela_lista() {
		?>
		<div class="wrap">
			<h1>Modelos de e-mail</h1>
			<p>Aqui você muda o texto dos e-mails que o clube manda. O visual (faixa com o logo, botão amarelo, rodapé) é o mesmo para todos. Escolha um e-mail para editar.</p>
			<table class="widefat striped" style="max-width:980px">
				<thead><tr><th>E-mail</th><th>Quando é enviado</th><th>Texto</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( self::definicoes() as $chave => $d ) : ?>
					<tr>
						<td><strong><a href="<?php echo esc_url( self::url( $chave ) ); ?>"><?php echo esc_html( $d['nome'] ); ?></a></strong></td>
						<td><?php echo esc_html( $d['quando'] ); ?></td>
						<td><?php echo self::editado( $chave ) ? '<strong style="color:#996800">Editado</strong>' : 'Original'; ?></td>
						<td><a class="button" href="<?php echo esc_url( self::url( $chave ) ); ?>">Editar</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description" style="margin-top:1rem">Os e-mails saem pelo serviço configurado em Clube → E-mails.</p>
		</div>
		<?php
	}

	private static function tela_edicao( $chave ) {
		$def   = self::definicao( $chave );
		$cfg   = self::campos( $chave );
		$aviso = isset( $_GET['aviso'] ) ? sanitize_key( wp_unslash( $_GET['aviso'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$uid   = get_current_user_id();

		// Se o salvamento foi recusado, a tela volta com o que a pessoa tinha escrito, para ela corrigir.
		$rascunho = get_transient( 'cav_modelo_rascunho_' . $uid );
		if ( is_array( $rascunho ) && ( $rascunho['chave'] ?? '' ) === $chave ) {
			foreach ( (array) $rascunho['campos'] as $c => $v ) {
				if ( isset( $cfg[ $c ] ) ) {
					$cfg[ $c ] = $v;
				}
			}
			delete_transient( 'cav_modelo_rascunho_' . $uid );
		}
		$erro = get_transient( 'cav_modelo_erro_' . $uid );
		delete_transient( 'cav_modelo_erro_' . $uid );

		[ $vars, $link, $blocos ] = self::exemplo( $chave );
		$previa = self::montar( $chave, $vars, $link, $blocos );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $def['nome'] ); ?></h1>
			<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; Todos os e-mails</a> &nbsp;·&nbsp; <?php echo esc_html( $def['quando'] ); ?></p>

			<?php if ( 'salvo' === $aviso ) : ?>
				<div class="notice notice-success is-dismissible"><p>Texto salvo. A prévia ao lado já mostra como ficou.</p></div>
			<?php elseif ( 'teste' === $aviso ) : ?>
				<div class="notice notice-success is-dismissible"><p>Texto salvo e e-mail de teste enviado para <?php echo esc_html( wp_get_current_user()->user_email ); ?>. Se não chegar, olhe o spam.</p></div>
			<?php elseif ( 'teste_erro' === $aviso ) : ?>
				<div class="notice notice-error"><p>O texto foi salvo, mas o e-mail de teste não saiu. Confira Clube → E-mails.</p></div>
			<?php elseif ( 'restaurado' === $aviso ) : ?>
				<div class="notice notice-success is-dismissible"><p>Voltou para o texto original.</p></div>
			<?php elseif ( 'marcador' === $aviso && $erro ) : ?>
				<div class="notice notice-error"><p>Nada foi salvo. Estes marcadores não existem neste e-mail: <code><?php echo esc_html( $erro ); ?></code>. Use só os da lista abaixo do campo, entre chaves. O que você escreveu continua nos campos.</p></div>
			<?php endif; ?>

			<div style="display:flex;gap:28px;align-items:flex-start;flex-wrap:wrap">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="flex:1 1 440px;max-width:640px">
					<input type="hidden" name="action" value="cav_modelo_salvar">
					<input type="hidden" name="modelo" value="<?php echo esc_attr( $chave ); ?>">
					<?php wp_nonce_field( 'cav_modelo_' . $chave ); ?>

					<?php foreach ( $cfg as $campo => $valor ) :
						$linhas = in_array( $campo, [ 'corpo', 'com_link', 'sem_link' ], true ) ? ( 'corpo' === $campo ? 14 : 6 ) : 0;
						?>
						<p>
							<label for="cav_m_<?php echo esc_attr( $campo ); ?>"><strong><?php echo esc_html( self::rotulo_campo( $campo, $def ) ); ?></strong></label><br>
							<?php if ( $linhas ) : ?>
								<textarea id="cav_m_<?php echo esc_attr( $campo ); ?>" name="campos[<?php echo esc_attr( $campo ); ?>]" rows="<?php echo (int) $linhas; ?>" class="large-text cav-campo" maxlength="6000"><?php echo esc_textarea( $valor ); ?></textarea>
							<?php else : ?>
								<input type="text" id="cav_m_<?php echo esc_attr( $campo ); ?>" name="campos[<?php echo esc_attr( $campo ); ?>]" value="<?php echo esc_attr( $valor ); ?>" class="large-text cav-campo" maxlength="400">
							<?php endif; ?>
						</p>
					<?php endforeach; ?>

					<div style="background:#f6f7f7;border:1px solid #dcdcde;padding:10px 14px;margin:0 0 14px">
						<p style="margin:0 0 6px"><strong>Marcadores</strong> <span class="description">clique para colocar no texto, na posição do cursor</span></p>
						<p style="margin:0">
							<?php foreach ( $def['marcadores'] as $m => $descricao ) : ?>
								<button type="button" class="button button-small cav-marcador" data-m="{<?php echo esc_attr( $m ); ?>}" title="<?php echo esc_attr( $descricao ); ?>">{<?php echo esc_html( $m ); ?>}</button>
							<?php endforeach; ?>
						</p>
						<ul style="margin:8px 0 0;font-size:12px;color:#50575e">
							<?php foreach ( $def['marcadores'] as $m => $descricao ) : ?>
								<li><code>{<?php echo esc_html( $m ); ?>}</code> <?php echo esc_html( $descricao ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p class="description" style="margin:8px 0 0">
							Uma linha em branco separa os parágrafos. <code>**assim**</code> deixa em negrito. Escreva só texto, sem HTML.
							Se uma informação não existir na hora de enviar (por exemplo, o motivo de uma recusa), o parágrafo que usa ela some sozinho.
						</p>
					</div>

					<p>
						<button type="submit" name="acao" value="salvar" class="button button-primary">Salvar</button>
						<button type="submit" name="acao" value="testar" class="button">Salvar e enviar um teste para mim</button>
						<button type="submit" name="acao" value="restaurar" class="button-link-delete" style="margin-left:12px" formnovalidate
						        onclick="return confirm('Voltar este e-mail para o texto original? O que você escreveu aqui se perde.');">Restaurar o texto original</button>
					</p>
				</form>

				<div style="flex:1 1 400px;max-width:620px">
					<p><strong>Prévia</strong> <span class="description">com dados de exemplo; atualiza ao salvar</span></p>
					<iframe title="Prévia do e-mail" sandbox="" style="width:100%;height:760px;border:1px solid #dcdcde;background:#ececec" srcdoc="<?php echo esc_attr( $previa['html'] ); ?>"></iframe>
					<p class="description">Assunto: <strong><?php echo esc_html( $previa['assunto'] ); ?></strong></p>
				</div>
			</div>
		</div>
		<script>
		(function () {
			var ultimo = null;
			document.querySelectorAll('.cav-campo').forEach(function (c) {
				c.addEventListener('focus', function () { ultimo = c; });
			});
			document.querySelectorAll('.cav-marcador').forEach(function (b) {
				b.addEventListener('click', function () {
					var c = ultimo || document.getElementById('cav_m_corpo');
					if (!c) { return; }
					var ini = c.selectionStart || 0, fim = c.selectionEnd || 0, t = b.getAttribute('data-m');
					c.value = c.value.slice(0, ini) + t + c.value.slice(fim);
					c.focus();
					c.selectionStart = c.selectionEnd = ini + t.length;
				});
			});
		})();
		</script>
		<?php
	}

	public static function salvar() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.', '', [ 'response' => 403 ] );
		}

		$chave = isset( $_POST['modelo'] ) ? sanitize_key( wp_unslash( $_POST['modelo'] ) ) : '';
		$def   = self::definicao( $chave );
		if ( ! $def ) {
			wp_die( 'E-mail desconhecido.' );
		}
		check_admin_referer( 'cav_modelo_' . $chave );

		$acao = isset( $_POST['acao'] ) ? sanitize_key( wp_unslash( $_POST['acao'] ) ) : 'salvar';
		$uid  = get_current_user_id();
		$todos = self::salvos();

		if ( 'restaurar' === $acao ) {
			unset( $todos[ $chave ] );
			update_option( self::OPT, $todos, false );
			wp_safe_redirect( self::url( $chave, [ 'aviso' => 'restaurado' ] ) );
			exit;
		}

		$enviado = isset( $_POST['campos'] ) && is_array( $_POST['campos'] ) ? wp_unslash( $_POST['campos'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$novo    = [];
		$ruins   = [];
		$validos = array_keys( $def['marcadores'] );

		foreach ( self::CAMPOS as $c ) {
			if ( ! isset( $def[ $c ] ) ) {
				continue;
			}
			$v = isset( $enviado[ $c ] ) && is_string( $enviado[ $c ] ) ? $enviado[ $c ] : '';
			$v = in_array( $c, [ 'corpo', 'com_link', 'sem_link' ], true )
				? trim( sanitize_textarea_field( $v ) )
				: trim( sanitize_text_field( $v ) );
			$v = mb_substr( $v, 0, 6000 );

			if ( preg_match_all( '/\{([^{}\s]*)\}/u', $v, $achados ) ) {
				foreach ( $achados[1] as $k ) {
					if ( ! in_array( $k, $validos, true ) ) {
						$ruins[] = '{' . $k . '}';
					}
				}
			}
			$novo[ $c ] = $v;
		}

		if ( $ruins ) {
			set_transient( 'cav_modelo_rascunho_' . $uid, [ 'chave' => $chave, 'campos' => $novo ], 600 );
			set_transient( 'cav_modelo_erro_' . $uid, implode( ' ', array_unique( $ruins ) ), 600 );
			wp_safe_redirect( self::url( $chave, [ 'aviso' => 'marcador' ] ) );
			exit;
		}

		// Guarda só o que difere do original; campo em branco volta ao original.
		$guardar = [];
		foreach ( $novo as $c => $v ) {
			if ( '' !== $v && $v !== trim( $def[ $c ] ) ) {
				$guardar[ $c ] = $v;
			}
		}
		if ( $guardar ) {
			$todos[ $chave ] = $guardar;
		} else {
			unset( $todos[ $chave ] );
		}
		update_option( self::OPT, $todos, false );

		$aviso = 'salvo';
		if ( 'testar' === $acao ) {
			[ $vars, $link, $blocos ] = self::exemplo( $chave );
			$m  = self::montar( $chave, $vars, $link, $blocos );
			$ok = $m && wp_mail( wp_get_current_user()->user_email, '[Teste] ' . $m['assunto'], $m['html'], CAV_Emails::cabecalhos() );
			$aviso = $ok ? 'teste' : 'teste_erro';
		}

		wp_safe_redirect( self::url( $chave, [ 'aviso' => $aviso ] ) );
		exit;
	}
}
