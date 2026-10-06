<?php
/**
 * Conteúdo das páginas do clube no formato do Elementor (contêineres).
 *
 * A estrutura é montada aqui e o visual vem das classes clube-* do tema filho
 * (assets/site.css). Para mudar texto ou botão depois, edite a página no Elementor:
 * o setup não sobrescreve uma página que já foi montada.
 *
 * Funciona sem o WordPress carregado, para poder ser testado em linha de comando.
 */

if ( ! function_exists( 'cav_pg_id' ) ) {

	/** Id de 7 caracteres, como o Elementor gera. Estável, para o resultado não mudar a cada rodada. */
	function cav_pg_id( $semente ) {
		static $n = 0;
		$n++;
		return substr( md5( $semente . '|' . $n ), 0, 7 );
	}

	function cav_pg_contem( $classes, $filhos, $interno = true, $extra = [] ) {
		return [
			'id'       => cav_pg_id( 'c' . $classes ),
			'elType'   => 'container',
			'isInner'  => $interno,
			'settings' => array_merge( [
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'css_classes'    => trim( ( $interno ? 'clube-c ' : '' ) . $classes ),
			], $extra ),
			'elements' => $filhos,
		];
	}

	function cav_pg_widget( $tipo, $config, $classes = '' ) {
		if ( $classes ) {
			$config['_css_classes'] = $classes;
		}
		return [
			'id'         => cav_pg_id( $tipo ),
			'elType'     => 'widget',
			'widgetType' => $tipo,
			'settings'   => $config,
			'elements'   => [],
		];
	}

	function cav_pg_titulo( $html, $tag = 'h2', $classes = '' ) {
		return cav_pg_widget( 'heading', [ 'title' => $html, 'header_size' => $tag ], $classes );
	}

	function cav_pg_olho( $texto ) {
		return cav_pg_titulo( $texto, 'p', 'clube-olho' );
	}

	function cav_pg_texto( $html, $classes = '' ) {
		return cav_pg_widget( 'text-editor', [ 'editor' => $html ], $classes );
	}

	function cav_pg_botao( $texto, $url, $linha = false ) {
		return cav_pg_widget(
			'button',
			[
				'text' => $texto,
				'link' => [ 'url' => $url, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '' ],
			],
			$linha ? 'clube-btn-linha' : ''
		);
	}

	function cav_pg_atalho( $shortcode ) {
		return cav_pg_widget( 'shortcode', [ 'shortcode' => $shortcode ] );
	}

	/** Seção de largura total com um miolo centralizado. */
	function cav_pg_secao( $classes, $filhos ) {
		return cav_pg_contem( $classes, [ cav_pg_contem( 'clube-env', $filhos ) ], false );
	}

	function cav_pg_tabela( $linhas ) {
		$h = '<table class="clube-tabela"><tbody>';
		foreach ( $linhas as $l ) {
			$total = ! empty( $l[2] ) ? ' class="total"' : '';
			$h    .= '<tr' . $total . '><td>' . $l[0] . '</td><td>' . $l[1] . '</td></tr>';
		}
		return $h . '</tbody></table>';
	}

	function cav_pg_passo( $n, $titulo, $texto, $largo = false ) {
		return cav_pg_contem( 'clube-passo' . ( $largo ? ' clube-passo-largo' : '' ), [
			cav_pg_olho( $n ),
			cav_pg_titulo( $titulo, 'h3' ),
			cav_pg_texto( '<p>' . $texto . '</p>' ),
		] );
	}

	function cav_pg_pergunta( $titulo, $texto ) {
		return cav_pg_contem( '', [
			cav_pg_titulo( $titulo, 'h3' ),
			cav_pg_texto( '<p>' . $texto . '</p>' ),
		] );
	}

	function cav_pg_topo( $olho, $titulo, $lead ) {
		return cav_pg_secao( 'clube-topo-el', [
			cav_pg_olho( $olho ),
			cav_pg_titulo( $titulo, 'h1' ),
			cav_pg_texto( '<p>' . $lead . '</p>', 'clube-lead' ),
		] );
	}

	function cav_paginas_elementor() {
		$ouro = static function ( $t ) {
			return '<span class="clube-ouro">' . $t . '</span>';
		};

		/* ------------------------------ Início ------------------------------ */
		$inicio = [
			cav_pg_secao( 'clube-hero', [
				cav_pg_olho( 'Adicional ao seu plano' ),
				cav_pg_titulo( $ouro( 'Bem-vindo ao Clube de Vantagens' ) . '<br>Alliance Mogi das Cruzes', 'h1' ),
				cav_pg_texto(
					'<p>Seu kimono abre porta fora do tatame. A Alliance fechou parceria com comércios da cidade: você chega, informa o CPF e o desconto sai na hora. Sem cupom, sem app, sem carteirinha impressa. Adicione ao seu plano por <strong class="clube-ouro">R$ 19,90 por mês</strong> na recepção.</p>',
					'clube-lead'
				),
				cav_pg_contem( 'clube-linha-botoes', [
					cav_pg_botao( 'Quero fazer parte', '/quero-fazer-parte/' ),
					cav_pg_botao( 'Entrar', '/entrar/', true ),
					cav_pg_botao( 'Ver os parceiros', '/parceiros/', true ),
				] ),
				cav_pg_contem( 'clube-grade clube-selos', [
					cav_pg_contem( 'clube-selo', [ cav_pg_titulo( 'R$ 19,90', 'div', 'clube-selo-v' ), cav_pg_texto( '<p>por mês, no seu boleto</p>' ) ] ),
					cav_pg_contem( 'clube-selo', [ cav_pg_titulo( 'CPF', 'div', 'clube-selo-v' ), cav_pg_texto( '<p>é tudo que você informa no caixa</p>' ) ] ),
					cav_pg_contem( 'clube-selo', [ cav_pg_titulo( 'Sem fidelidade', 'div', 'clube-selo-v' ), cav_pg_texto( '<p>cancele quando quiser</p>' ) ] ),
				] ),
			] ),

			cav_pg_secao( 'clube-bloco clube-claro', [
				cav_pg_olho( 'Como funciona' ),
				cav_pg_titulo( 'Três passos, ' . $ouro( 'uma vez só' ) ),
				cav_pg_contem( 'clube-grade clube-grade-3', [
					cav_pg_passo( 'Passo 01', 'Peça para entrar', 'Preencha nome, CPF e WhatsApp. Um minuto, sem compromisso.' ),
					cav_pg_passo( 'Passo 02', 'A recepção adiciona ao plano', 'Os R$ 19,90 entram no seu próximo boleto e o acesso é liberado. Sem cartão, sem taxa de adesão.' ),
					cav_pg_passo( 'Passo 03', 'Informe o CPF no caixa', 'O parceiro confere na hora e aplica o desconto. Não precisa mostrar nada.' ),
					cav_pg_passo( 'Importante', 'Cancele quando quiser', 'Avise a recepção e o adicional sai do próximo boleto. Sem multa, sem fidelidade, sem conversa de retenção.', true ),
				] ),
			] ),

			cav_pg_secao( 'clube-bloco', [
				cav_pg_olho( 'Em destaque' ),
				cav_pg_titulo( 'Onde os alunos ' . $ouro( 'já economizam' ) ),
				cav_pg_atalho( '[clube_parceiros limite="6"]' ),
				cav_pg_contem( 'clube-linha-botoes', [ cav_pg_botao( 'Ver todos os parceiros', '/parceiros/', true ) ] ),
			] ),

			cav_pg_secao( 'clube-bloco clube-claro', [
				cav_pg_olho( 'A conta' ),
				cav_pg_titulo( 'Vale a pena ' . $ouro( 'para você?' ) ),
				cav_pg_texto( '<p>Depende de quanto você usa. Se for um corte de cabelo por mês, já se paga. Se você não usar nada, não vale, e nesse caso é melhor não assinar.</p>', 'clube-lead' ),
				cav_pg_contem( 'clube-grade clube-grade-2', [
					cav_pg_contem( 'clube-caixa', [
						cav_pg_olho( 'Custo' ),
						cav_pg_titulo( 'R$ 19,90', 'h3' ),
						cav_pg_texto( '<p>por mês, somado ao seu boleto da academia. R$ 238,80 por ano.</p>' ),
					] ),
					cav_pg_contem( 'clube-caixa clube-caixa--ouro', [
						cav_pg_olho( 'Retorno em um mês típico' ),
						cav_pg_texto( cav_pg_tabela( [
							[ '1 corte de cabelo (30% off)', 'R$ 18,00' ],
							[ '2 almoços no parceiro', 'R$ 16,00' ],
							[ 'Suplemento (12% off)', 'R$ 24,00' ],
							[ 'Total economizado', 'R$ 58,00', true ],
						] ) ),
					] ),
				] ),
				cav_pg_texto( '<p>Valores de exemplo, para mostrar como a conta funciona. O seu resultado depende dos descontos vigentes e de quanto você usar.</p>', 'clube-nota' ),
			] ),

			cav_pg_secao( 'clube-bloco clube-recruta', [
				cav_pg_olho( 'Para estabelecimentos' ),
				cav_pg_titulo( 'Coloque seu negócio na frente dos ' . $ouro( 'alunos da Alliance' ) ),
				cav_pg_texto( '<p>Entrar no clube é gratuito para o seu negócio. Você define o desconto, cadastra em cinco minutos e recebe um painel com quantas pessoas usaram. Sem mensalidade, sem comissão. E como o aluno paga para participar, quem chega até você já veio decidido a usar.</p>', 'clube-lead' ),
				cav_pg_contem( 'clube-linha-botoes', [ cav_pg_botao( 'Quero ser parceiro', '/seja-parceiro/' ) ] ),
			] ),
		];

		/* --------------------------- O que é o clube --------------------------- */
		$sobre = [
			cav_pg_topo(
				'Clube de vantagens',
				'O que é o clube',
				'Uma rede de comércios de Mogi das Cruzes que dá desconto para quem treina na Alliance. Adicional opcional de R$ 19,90 por mês no seu plano.'
			),

			cav_pg_secao( 'clube-bloco', [
				cav_pg_contem( 'clube-grade clube-grade-2', [
					cav_pg_contem( '', [
						cav_pg_olho( 'A ideia' ),
						cav_pg_titulo( 'Um adicional ' . $ouro( 'que se paga sozinho' ) ),
						cav_pg_texto( '<p>A Alliance procurou negócios da cidade e negociou condição para os alunos. O comércio ganha clientes recorrentes, você ganha desconto, e a academia cobra um adicional para manter a operação de pé: captar parceiro, negociar condição e sustentar a plataforma.</p><p>Não é programa de pontos. Não acumula, não expira saldo, não tem nível. É desconto direto, na hora de pagar.</p>' ),
					] ),
					cav_pg_contem( '', [
						cav_pg_olho( 'O que você tem acesso' ),
						cav_pg_texto( cav_pg_tabela( [
							[ 'Descontos nos parceiros', 'conforme cada parceiro' ],
							[ 'Promoções por temporada', 'rotativo' ],
							[ 'Conteúdo técnico gravado', 'só membros' ],
							[ 'Sorteios da academia', 'só membros' ],
							[ 'Custo', 'R$ 19,90/mês', true ],
						] ) ),
					] ),
				] ),
			] ),

			cav_pg_secao( 'clube-bloco clube-claro', [
				cav_pg_olho( 'Perguntas que sempre aparecem' ),
				cav_pg_titulo( 'Antes de perguntar ' . $ouro( 'na recepção' ) ),
				cav_pg_contem( 'clube-grade clube-grade-faq', [
					cav_pg_pergunta( 'Quanto custa?', 'R$ 19,90 por mês, somados ao seu boleto da academia. Não tem taxa de adesão nem cobrança separada.' ),
					cav_pg_pergunta( 'Por que preciso de aprovação?', 'Porque o clube é só para aluno matriculado e o valor entra no seu boleto. A recepção confirma as duas coisas antes de liberar.' ),
					cav_pg_pergunta( 'Preciso mostrar carteirinha?', 'Não. Você fala o CPF no caixa e o parceiro confere na hora pelo celular dele.' ),
					cav_pg_pergunta( 'Meus dados ficam expostos?', 'O parceiro vê apenas se você tem direito e seu primeiro nome. Nada de telefone, e-mail, endereço ou plano.' ),
					cav_pg_pergunta( 'Como eu cancelo?', 'Avisa a recepção e o adicional sai do próximo boleto. Sem multa e sem prazo mínimo. Se trancar a matrícula, o clube pausa junto.' ),
					cav_pg_pergunta( 'Posso emprestar para alguém?', 'Não. O CPF é seu e o uso fica registrado. Uso indevido tira você do clube.' ),
				] ),
				cav_pg_contem( 'clube-linha-botoes', [ cav_pg_botao( 'Quero fazer parte', '/quero-fazer-parte/' ) ] ),
			] ),
		];

		/* -------------------------------- Entrar -------------------------------- */
		$entrar = [
			cav_pg_topo( 'Entrar', 'Qual é ' . $ouro( 'o seu acesso?' ), 'Escolha o seu perfil. Depois de entrar, você cai direto na sua área.' ),

			cav_pg_secao( 'clube-bloco', [
				cav_pg_contem( 'clube-grade clube-grade-2', [
					cav_pg_contem( 'clube-caixa clube-caixa--link', [
						cav_pg_olho( 'Sou aluno' ),
						cav_pg_titulo( 'Área do membro', 'h3' ),
						cav_pg_texto( '<p>Sua carteirinha, histórico de benefícios, conteúdos e sorteios.</p>' ),
						cav_pg_botao( 'Entrar como aluno', '/wp-login.php?redirect_to=%2Farea-do-membro%2F' ),
					] ),
					cav_pg_contem( 'clube-caixa clube-caixa--link', [
						cav_pg_olho( 'Sou estabelecimento' ),
						cav_pg_titulo( 'Área do parceiro', 'h3' ),
						cav_pg_texto( '<p>Terminal de consulta, painel de uso e conteúdos para parceiros.</p>' ),
						cav_pg_botao( 'Entrar como parceiro', '/wp-login.php?redirect_to=%2Fterminal%2F' ),
					] ),
				] ),
				cav_pg_texto( '<p>Ainda não tem acesso? <a href="/quero-fazer-parte/">Sou aluno e quero participar</a> · <a href="/seja-parceiro/">Tenho um negócio e quero ser parceiro</a></p>', 'clube-nota' ),
			] ),
		];

		/* ---------------------------- Quero fazer parte ---------------------------- */
		$fazer = [
			cav_pg_topo(
				'Clube de vantagens',
				'Quero fazer parte',
				'Preencha o pedido. A recepção da Alliance confere a sua matrícula, libera o acesso e avisa por e-mail. O adicional de R$ 19,90 por mês entra no seu boleto da academia.'
			),
			cav_pg_contem( 'clube-forma', [ cav_pg_atalho( '[cav_solicitar]' ) ], false ),
		];

		/* ------------------------------ Seja parceiro ------------------------------ */
		$seja = [
			cav_pg_topo(
				'Para estabelecimentos',
				'Seja parceiro',
				'Entrar no clube é gratuito para o seu negócio. Você define o desconto e a Alliance analisa o cadastro. Aprovado, você recebe um e-mail com o acesso ao terminal.'
			),
			cav_pg_secao( 'clube-bloco clube-claro', [
				cav_pg_contem( 'clube-grade clube-grade-3', [
					cav_pg_passo( 'Passo 01', 'Cadastre o negócio', 'Nome, endereço, horário, fotos e telefone. Cinco minutos.' ),
					cav_pg_passo( 'Passo 02', 'Defina o benefício', 'Percentual, combo ou brinde. Permanente ou campanha com data.' ),
					cav_pg_passo( 'Passo 03', 'Atenda pelo terminal', 'O cliente informa o CPF, você confere na hora pelo celular.' ),
				] ),
			] ),
			cav_pg_contem( 'clube-forma', [ cav_pg_atalho( '[cav_candidatura]' ) ], false ),
		];

		return [
			'inicio'            => [ 'titulo' => 'Início',            'dados' => $inicio ],
			'o-que-e'           => [ 'titulo' => 'O que é o clube',   'dados' => $sobre ],
			'entrar'            => [ 'titulo' => 'Entrar',            'dados' => $entrar ],
			'quero-fazer-parte' => [ 'titulo' => 'Quero fazer parte', 'dados' => $fazer ],
			'seja-parceiro'     => [ 'titulo' => 'Seja parceiro',     'dados' => $seja ],
		];
	}

	/** Versão em HTML simples da mesma página: rede de segurança caso o Elementor não renderize. */
	function cav_pg_html_simples( $elementos ) {
		$h = '';
		foreach ( $elementos as $e ) {
			if ( 'container' === $e['elType'] ) {
				$h .= cav_pg_html_simples( $e['elements'] );
				continue;
			}
			$c = $e['settings'];
			switch ( $e['widgetType'] ) {
				case 'heading':
					$t  = in_array( $c['header_size'], [ 'h1', 'h2', 'h3' ], true ) ? $c['header_size'] : 'p';
					$h .= '<' . $t . '>' . $c['title'] . '</' . $t . ">\n";
					break;
				case 'text-editor':
					$h .= $c['editor'] . "\n";
					break;
				case 'button':
					$h .= '<p><a href="' . $c['link']['url'] . '">' . $c['text'] . "</a></p>\n";
					break;
				case 'shortcode':
					$h .= $c['shortcode'] . "\n";
					break;
			}
		}
		return $h;
	}
}
