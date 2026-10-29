<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clube → Instalação: monta o site pelo painel, para hospedagens sem terminal (só SFTP e wp-admin).
 * Faz o mesmo que o scripts/setup.sh faz no Codespace, menos instalar WordPress, Elementor e dados de teste:
 * fuso e datas, links permanentes, páginas, menu, área do aluno e do parceiro, páginas no Elementor,
 * sorteios do calendário e paleta. Pode rodar de novo: o que já existe é mantido.
 *
 * Os scripts de montagem vêm dentro do zip do plugin, na pasta instalar/ (o scripts/empacotar.sh copia).
 * No Codespace eles ficam em /scripts.
 */
class CAV_Instalar {

	/** Mesmo número do PAGINAS_VERSAO do setup.sh. Quando sobe, as cinco páginas principais são refeitas uma vez. */
	const PAGINAS_VERSAO = 9;

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 40 );
		add_action( 'admin_post_cav_instalar', [ __CLASS__, 'executar_pelo_painel' ] );
	}

	public static function menu() {
		add_submenu_page( 'cav-relatorio', 'Instalação do site', 'Instalação', 'manage_options', 'cav-instalar', [ __CLASS__, 'tela' ] );
	}

	/** Pasta com os scripts de montagem, ou '' se não achar. */
	private static function pasta_scripts() {
		foreach ( [ CAV_PATH . 'instalar', '/scripts' ] as $p ) {
			if ( is_readable( $p . '/paginas-elementor.php' ) && is_readable( $p . '/lib/paginas.php' ) ) {
				return $p;
			}
		}
		return '';
	}

	/** O que precisa estar certo antes (e depois) de montar. [rótulo, ok, explicação se não estiver ok] */
	public static function verificacoes() {
		$hello = wp_get_theme( 'hello-elementor' );
		return [
			[ 'Endereço com https', 0 === strpos( home_url(), 'https://' ), 'Peça à hospedagem o certificado SSL e, em Configurações → Geral, troque os dois endereços para https://.' ],
			[ 'Extensão sodium do PHP', function_exists( 'sodium_crypto_secretbox' ), 'Peça à hospedagem para ativar a extensão sodium do PHP. Sem ela os CPFs não ficam protegidos.' ],
			[ 'Chave dos CPFs no wp-config.php', class_exists( 'CAV_Cripto' ) && CAV_Cripto::tem_chave(), 'Siga o passo 1 abaixo antes de qualquer cadastro.' ],
			[ 'Plugin Elementor ativo', did_action( 'elementor/loaded' ) > 0, 'Plugins → Adicionar novo → procure "Elementor" → Instalar → Ativar.' ],
			[ 'Tema Hello Elementor instalado', $hello->exists(), 'Aparência → Temas → Adicionar novo → procure "Hello Elementor" → Instalar (não precisa ativar).' ],
			[ 'Tema do clube ativo', 'clube-alliance-child' === get_stylesheet(), 'Aparência → Temas → Adicionar novo → Enviar tema → clube-alliance-child.zip → Ativar.' ],
			[ 'Arquivos de montagem no plugin', '' !== self::pasta_scripts(), 'O zip do plugin está incompleto. Use o clube-alliance.zip gerado pelo scripts/empacotar.sh.' ],
		];
	}

	public static function tela() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.', '', [ 'response' => 403 ] );
		}

		$checks  = self::verificacoes();
		$prontos = true;
		foreach ( $checks as $c ) {
			// A chave não trava a montagem (as páginas não guardam CPF), mas o aviso fica.
			if ( ! $c[1] && 'Chave dos CPFs no wp-config.php' !== $c[0] && 'Endereço com https' !== $c[0] ) {
				$prontos = false;
			}
		}

		$relatorio = get_transient( 'cav_instalar_log_' . get_current_user_id() );
		delete_transient( 'cav_instalar_log_' . get_current_user_id() );
		$ultima = get_option( 'cav_instalado_em' );
		?>
		<div class="wrap">
			<h1>Instalação do site</h1>
			<p style="max-width:760px">Esta tela monta o site do clube na hospedagem: páginas, menu, áreas do aluno e do parceiro, páginas no Elementor, sorteios do calendário e paleta. Pode clicar de novo depois de atualizar o plugin: o que já existe é mantido.</p>

			<?php if ( is_array( $relatorio ) ) : ?>
				<div class="notice notice-success"><p><strong>Pronto.</strong> Veja o que foi feito:</p>
					<pre style="white-space:pre-wrap;max-height:360px;overflow:auto;background:#f6f7f7;padding:10px"><?php echo esc_html( implode( "\n", $relatorio ) ); ?></pre></div>
			<?php endif; ?>

			<h2>O que já está certo</h2>
			<table class="widefat striped" style="max-width:900px">
				<tbody>
				<?php foreach ( $checks as $c ) : ?>
					<tr>
						<td style="width:30px;font-size:18px"><?php echo $c[1] ? '<span style="color:#1a7f37">✔</span>' : '<span style="color:#b32d2e">✖</span>'; ?></td>
						<td><strong><?php echo esc_html( $c[0] ); ?></strong><?php if ( ! $c[1] ) : ?><br><span class="description"><?php echo esc_html( $c[2] ); ?></span><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! CAV_Cripto::tem_chave() ) :
				$chave = bin2hex( random_bytes( 32 ) ); ?>
				<h2>Passo 1: a chave que protege os CPFs</h2>
				<div style="max-width:900px;background:#fff8e1;border-left:4px solid #dba617;padding:12px 16px">
					<p><strong>Faça isto antes de qualquer aluno se cadastrar.</strong> Pelo SFTP, baixe o arquivo <code>wp-config.php</code> (fica na pasta principal do WordPress), abra num editor de texto e cole a linha abaixo logo antes da linha que diz <code>/* That's all, stop editing!</code> (ou <code>/* Isso é tudo, pode parar de editar!</code>). Salve e envie o arquivo de volta.</p>
					<p><input type="text" readonly value="<?php echo esc_attr( "define( 'CAV_CHAVE_DADOS', '" . $chave . "' );" ); ?>" style="width:100%;font-family:monospace" onclick="this.select()"></p>
					<p><strong>Guarde uma cópia dessa linha fora do servidor</strong> (num gerenciador de senhas, por exemplo). Se ela se perder, os CPFs gravados não voltam. Cada vez que esta tela abre ela sugere uma chave nova: use uma só e não troque depois.</p>
				</div>
			<?php endif; ?>

			<h2><?php echo CAV_Cripto::tem_chave() ? 'Montar o site' : 'Passo 2: montar o site'; ?></h2>
			<?php if ( $ultima ) : ?>
				<p class="description">Última montagem: <?php echo esc_html( mysql2date( 'd/m/Y H:i', $ultima ) ); ?>.</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cav_instalar">
				<?php wp_nonce_field( 'cav_instalar' ); ?>
				<p><label><input type="checkbox" name="refazer" value="1"> Refazer as cinco páginas principais (Início, O que é, Entrar, Quero fazer parte, Seja parceiro). Apaga o que foi editado nelas no Elementor.</label></p>
				<p><button type="submit" class="button button-primary button-hero" <?php disabled( ! $prontos ); ?>>Montar o site</button></p>
				<?php if ( ! $prontos ) : ?>
					<p class="description">Resolva os itens marcados com ✖ acima para liberar o botão.</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	public static function executar_pelo_painel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.', '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'cav_instalar' );

		$log = self::executar( ! empty( $_POST['refazer'] ) );
		set_transient( 'cav_instalar_log_' . get_current_user_id(), $log, 600 );
		wp_safe_redirect( admin_url( 'admin.php?page=cav-instalar' ) );
		exit;
	}

	/** Faz a montagem e devolve o relatório, linha a linha. */
	public static function executar( $refazer = false ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$log = [];

		// Fuso e datas: a validade do acesso vence à meia-noite deste fuso.
		update_option( 'timezone_string', 'America/Sao_Paulo' );
		update_option( 'date_format', 'd/m/Y' );
		update_option( 'time_format', 'H:i' );
		if ( '' === trim( (string) get_option( 'blogdescription' ) ) || 'Just another WordPress site' === get_option( 'blogdescription' ) || 'Só mais um site WordPress' === get_option( 'blogdescription' ) ) {
			update_option( 'blogdescription', 'Descontos exclusivos para alunos da Alliance Mogi das Cruzes' );
		}
		$log[] = 'Fuso de São Paulo e datas em dd/mm/aaaa.';

		// Páginas base.
		$paginas = [
			'inicio'                => [ 'Início', '<p>Página montada pelo clube.</p>' ],
			'o-que-e'               => [ 'O que é o clube', '<p>Página montada pelo clube.</p>' ],
			'entrar'                => [ 'Entrar', '<p>Página montada pelo clube.</p>' ],
			'quero-fazer-parte'     => [ 'Quero fazer parte', '[cav_solicitar]' ],
			'seja-parceiro'         => [ 'Seja parceiro', '[cav_candidatura]' ],
			'minha-carteirinha'     => [ 'Minha carteirinha', '[cav_carteirinha]' ],
			'meus-beneficios'       => [ 'Meus benefícios', '[cav_meus_usos]' ],
			'conteudos'             => [ 'Conteúdos', '[cav_conteudos]' ],
			'sorteios-do-clube'     => [ 'Sorteios do clube', '[cav_sorteios]' ],
			'terminal'              => [ 'Terminal', '[cav_terminal]' ],
			'painel-do-parceiro'    => [ 'Painel do parceiro', '[cav_painel_parceiro]' ],
			'conteudos-do-parceiro' => [ 'Conteúdos do parceiro', '[cav_conteudos publico="parceiros"]' ],
		];
		$ids = [];
		foreach ( $paginas as $slug => $p ) {
			$existe = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $existe ) {
				$ids[ $slug ] = $existe->ID;
				continue;
			}
			$id = wp_insert_post( [
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $p[0],
				'post_name'    => $slug,
				'post_content' => $p[1],
			], true );
			if ( is_wp_error( $id ) ) {
				$log[] = 'Não consegui criar /' . $slug . '/: ' . $id->get_error_message();
				continue;
			}
			$ids[ $slug ] = $id;
			$log[]        = 'Página criada: ' . $p[0] . ' (/' . $slug . '/)';
		}

		if ( ! empty( $ids['inicio'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $ids['inicio'] );
		}
		update_option( 'cav_url_terminal', home_url( '/terminal/' ) );

		// Menu principal: Início, O que é, Parceiros, Seja parceiro.
		if ( '2' !== (string) get_option( 'cav_menu_versao' ) ) {
			$velho = wp_get_nav_menu_object( 'Principal' );
			if ( $velho ) {
				wp_delete_nav_menu( $velho->term_id );
			}
			$menu_id = wp_create_nav_menu( 'Principal' );
			if ( ! is_wp_error( $menu_id ) ) {
				foreach ( [ 'inicio', 'o-que-e' ] as $s ) {
					if ( ! empty( $ids[ $s ] ) ) {
						wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-object-id' => $ids[ $s ], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ] );
					}
				}
				wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-title' => 'Parceiros', 'menu-item-url' => home_url( '/parceiros/' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ] );
				if ( ! empty( $ids['seja-parceiro'] ) ) {
					wp_update_nav_menu_item( $menu_id, 0, [ 'menu-item-object-id' => $ids['seja-parceiro'], 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ] );
				}
				update_option( 'cav_menu_versao', 2 );
				$log[] = 'Menu Principal criado.';
			}
		}
		$menu = wp_get_nav_menu_object( 'Principal' );
		if ( $menu ) {
			$locais           = (array) get_theme_mod( 'nav_menu_locations', [] );
			$locais['menu-1'] = $menu->term_id;
			set_theme_mod( 'nav_menu_locations', $locais );
		}

		// Scripts de montagem (os mesmos do setup.sh).
		$pasta = self::pasta_scripts();
		if ( '' === $pasta ) {
			$log[] = 'Não achei os arquivos de montagem (pasta instalar/ do plugin). Parei aqui.';
			return $log;
		}

		$versao_atual = (string) get_option( 'cav_paginas_versao' );
		if ( $refazer ) {
			$pedido = 'refazer:inicio,o-que-e,entrar,quero-fazer-parte,seja-parceiro';
		} elseif ( '' !== $versao_atual && (string) self::PAGINAS_VERSAO !== $versao_atual ) {
			$pedido = 'refazer:inicio,o-que-e,entrar,quero-fazer-parte,seja-parceiro';
		} else {
			$pedido = '';
		}

		$etapas = [
			'Área do aluno'          => [ 'area-membro.php', [] ],
			'Área do parceiro'       => [ 'area-parceiro.php', [] ],
			'Páginas no Elementor'   => [ 'paginas-elementor.php', $pedido ? [ $pedido ] : [] ],
			'Sorteios do calendário' => [ 'sorteios-calendario.php', [] ],
			'Paleta do Elementor'    => [ 'elementor-kit.php', [] ],
		];

		self::preparar_wp_cli();
		foreach ( $etapas as $nome => $e ) {
			$log[] = '— ' . $nome;
			CAV_Instalar_Saida::$linhas = [];
			try {
				self::rodar( $pasta . '/' . $e[0], $e[1] );
			} catch ( \Throwable $erro ) {
				CAV_Instalar_Saida::$linhas[] = 'Erro: ' . $erro->getMessage();
			}
			foreach ( CAV_Instalar_Saida::$linhas as $l ) {
				$log[] = trim( $l );
			}
		}
		update_option( 'cav_paginas_versao', self::PAGINAS_VERSAO );

		// Links permanentes por último, depois de tudo registrado.
		update_option( 'permalink_structure', '/%postname%/' );
		flush_rewrite_rules( true );
		$log[] = 'Links permanentes no formato /nome-da-pagina/.';

		update_option( 'cav_instalado_em', current_time( 'mysql' ) );
		return $log;
	}

	/** Roda um script de montagem com as variáveis que o "wp eval-file" daria a ele. */
	private static function rodar( $arquivo, array $args ) {
		ob_start();
		try {
			( static function () use ( $arquivo, $args ) {
				require $arquivo;
			} )();
		} finally {
			$saida = trim( (string) ob_get_clean() );
			if ( '' !== $saida ) {
				foreach ( preg_split( '/\r?\n/', $saida ) as $l ) {
					CAV_Instalar_Saida::$linhas[] = $l;
				}
			}
		}
	}

	/** Fora do terminal não existe a classe WP_CLI; os scripts só usam log, warning, success e error. */
	private static function preparar_wp_cli() {
		if ( ! class_exists( 'WP_CLI', false ) ) {
			class_alias( 'CAV_Instalar_Saida', 'WP_CLI' );
		}
	}
}

/** Substituto da classe WP_CLI quando a montagem roda pelo painel: guarda as mensagens para o relatório. */
class CAV_Instalar_Saida {
	public static $linhas = [];
	public static function log( $m ) { self::$linhas[] = (string) $m; }
	public static function success( $m ) { self::$linhas[] = (string) $m; }
	public static function warning( $m ) { self::$linhas[] = 'Atenção: ' . $m; }
	public static function error( $m ) { throw new \RuntimeException( (string) $m ); }
}
