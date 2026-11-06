<?php
/**
 * "App" de celular para aprovar parceiros: uma página enxuta, sem o tema,
 * feita para ser adicionada à tela de início do iPhone.
 *
 * Endereço: /aprovar-parceiros/ (a página precisa existir; o conteúdo dela não importa).
 * Mostra só as candidaturas de estabelecimentos pendentes, com Aprovar e Recusar.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_AppParceiros {

	const SLUG = 'aprovar-parceiros';

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'talvez_mostrar' ], 1 );
	}

	public static function url( $args = [] ) {
		return add_query_arg( $args, home_url( '/' . self::SLUG . '/' ) );
	}

	public static function talvez_mostrar() {
		if ( ! is_page( self::SLUG ) ) {
			return;
		}

		nocache_headers();

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url() ) );
			exit;
		}
		if ( ! current_user_can( 'cav_aprovar' ) ) {
			wp_die( 'Esta página é só para quem aprova parceiros do clube.', 'Sem permissão', [ 'response' => 403 ] );
		}

		self::render();
		exit;
	}

	private static function mensagem() {
		$c = isset( $_GET['cav_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['cav_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $c ) {
			return null;
		}
		$mapa = [
			'parceiro'       => [ 'ok', 'Aprovado. O parceiro recebeu o e-mail com o acesso.' ],
			'parceiro_atual' => [ 'ok', 'Aprovado. A loja já está no site e o parceiro recebeu o e-mail com o acesso.' ],
			'parceiro_novo'  => [ 'ok', 'Aprovado. A loja entrou como rascunho em Parceiros para você revisar e publicar; o parceiro recebeu o acesso.' ],
			'recusado'       => [ 'aviso', 'Recusado. O responsável recebeu um e-mail.' ],
		];
		return $mapa[ $c ] ?? [ 'erro', 'Não foi possível concluir: ' . $c ];
	}

	private static function limite( $l ) {
		$m = [ 'dia' => '1x por dia', 'semana' => '1x por semana', 'mes' => '1x por mês' ];
		return $m[ $l ] ?? '';
	}

	private static function render() {
		$cands = CAV_Solicitacoes::pendentes_candidaturas();
		$msg   = self::mensagem();
		$icone = get_site_icon_url( 180 );
		$nome  = 'Parceiros';

		$manifest = wp_json_encode( [
			'name'             => 'Aprovar parceiros · Clube Alliance',
			'short_name'       => $nome,
			'start_url'        => self::url(),
			'display'          => 'standalone',
			'background_color' => '#0f0f0f',
			'theme_color'      => '#0f0f0f',
			'icons'            => $icone ? [ [ 'src' => $icone, 'sizes' => '180x180', 'type' => 'image/png' ] ] : [],
		] );

		header( 'Content-Type: text/html; charset=utf-8' );
		?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>Aprovar parceiros</title>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( $nome ); ?>">
<meta name="theme-color" content="#0f0f0f">
<?php if ( $icone ) : ?><link rel="apple-touch-icon" href="<?php echo esc_url( $icone ); ?>"><?php endif; ?>
<link rel="manifest" href="data:application/manifest+json,<?php echo rawurlencode( $manifest ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<style>
:root{--ouro:#ffc629;--fundo:#0f0f0f;--card:#1a1a1a;--linha:rgba(255,255,255,.12);--fraco:rgba(255,255,255,.62)}
*{box-sizing:border-box}
html,body{margin:0;background:var(--fundo);color:#fff;font-family:Poppins,system-ui,sans-serif;-webkit-text-size-adjust:100%}
body{padding:calc(env(safe-area-inset-top) + 12px) 16px calc(env(safe-area-inset-bottom) + 28px)}
.topo{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:6px 0 18px}
.olho{font-size:.68rem;letter-spacing:.24em;text-transform:uppercase;color:var(--ouro);margin:0 0 4px}
h1{font-size:1.45rem;margin:0;line-height:1.15}
.atualizar{border:1px solid var(--ouro);color:var(--ouro);background:none;font:600 .78rem Poppins,sans-serif;padding:9px 12px;text-decoration:none;white-space:nowrap}
.msg{padding:12px 14px;margin:0 0 16px;font-size:.9rem;border-left:3px solid}
.msg.ok{background:rgba(46,160,67,.15);border-color:#2ea043}
.msg.aviso{background:rgba(255,198,41,.12);border-color:var(--ouro)}
.msg.erro{background:rgba(248,81,73,.14);border-color:#f85149}
.vazio{text-align:center;color:var(--fraco);padding:56px 12px;border:1px dashed var(--linha)}
.vazio strong{display:block;color:#fff;font-size:1.05rem;margin-bottom:6px}
.card{background:var(--card);border:1px solid var(--linha);margin:0 0 16px;overflow:hidden}
.capa{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;background:#111}
.corpo{padding:16px}
.cab{display:flex;gap:12px;align-items:center;margin-bottom:12px}
.logo{width:52px;height:52px;flex:0 0 52px;object-fit:contain;background:#fff}
.tag{display:inline-block;font-size:.62rem;font-weight:700;letter-spacing:.12em;padding:2px 7px;margin-bottom:4px}
.tag.ja{background:var(--ouro);color:#111}
.tag.novo{border:1px solid var(--ouro);color:var(--ouro)}
.nome{font-size:1.1rem;font-weight:700;margin:0;line-height:1.2}
.cat{color:var(--fraco);font-size:.82rem}
.ben{background:#111;border-left:3px solid var(--ouro);padding:10px 12px;margin:0 0 12px}
.ben b{display:block;font-size:.98rem}
.ben span{display:block;color:var(--fraco);font-size:.8rem;margin-top:3px}
dl{margin:0 0 12px;display:grid;grid-template-columns:auto 1fr;gap:6px 12px;font-size:.86rem}
dt{color:var(--fraco)}
dd{margin:0;word-break:break-word}
dd a{color:#fff}
details{margin:0 0 14px;font-size:.88rem}
summary{color:var(--ouro);cursor:pointer;font-weight:600}
details p{white-space:pre-line;color:rgba(255,255,255,.85);margin:8px 0 0;line-height:1.55}
.acoes{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.acoes button{font:600 .95rem Poppins,sans-serif;padding:14px 10px;border:1px solid var(--ouro);cursor:pointer;-webkit-appearance:none;border-radius:0}
.aprovar{background:var(--ouro);color:#111}
.recusar{background:none;color:#fff;border-color:rgba(255,255,255,.35)!important}
.acoes button[disabled]{opacity:.5}
.rodape{text-align:center;color:var(--fraco);font-size:.75rem;margin-top:24px}
.rodape a{color:var(--fraco)}
</style>
</head>
<body>
	<div class="topo">
		<div>
			<p class="olho">Clube de Vantagens</p>
			<h1>Aprovar parceiros</h1>
		</div>
		<a class="atualizar" href="<?php echo esc_url( self::url() ); ?>">Atualizar</a>
	</div>

	<?php if ( $msg ) : ?>
		<div class="msg <?php echo esc_attr( $msg[0] ); ?>"><?php echo esc_html( $msg[1] ); ?></div>
	<?php endif; ?>

	<?php if ( ! $cands ) : ?>
		<div class="vazio"><strong>Nenhum parceiro esperando.</strong>Quando alguém mandar os dados, aparece aqui e chega um aviso no seu e-mail.</div>
	<?php endif; ?>

	<?php foreach ( $cands as $c ) :
		$g     = static function ( $k ) use ( $c ) {
			return (string) get_post_meta( $c->ID, $k, true );
		};
		$ja    = 'atual' === $g( CAV_Solicitacoes::CAND_ORIGEM );
		$logo  = (int) $g( CAV_Solicitacoes::CAND_LOGO );
		$foto  = (int) $g( CAV_Solicitacoes::CAND_FOTO );
		$zap   = $g( CAV_Solicitacoes::CAND_ZAP );
		$wa    = CAV_Aprovacoes::numero_whatsapp( $zap );
		$insta = $g( CAV_Solicitacoes::CAND_INSTA );
		$email = $g( CAV_Solicitacoes::CAND_EMAIL );
		$lim   = self::limite( $g( CAV_Solicitacoes::CAND_LIMITE ) );
		$regra = $g( CAV_Solicitacoes::CAND_REGRA );
		$desc  = $g( CAV_Solicitacoes::CAND_DESC );
		$dados = array_filter( [
			'Responsável' => esc_html( $g( CAV_Solicitacoes::CAND_RESP ) ),
			'E-mail'      => $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '',
			'WhatsApp'    => $zap ? ( $wa ? '<a href="https://wa.me/' . esc_attr( $wa ) . '">' . esc_html( $zap ) . '</a>' : esc_html( $zap ) ) : '',
			'Instagram'   => $insta ? '<a href="https://instagram.com/' . esc_attr( $insta ) . '">@' . esc_html( $insta ) . '</a>' : '',
			'Endereço'    => esc_html( $g( CAV_Solicitacoes::CAND_END ) ),
			'Horário'     => esc_html( $g( CAV_Solicitacoes::CAND_HORARIO ) ),
			'Enviado'     => esc_html( get_the_date( 'd/m/Y \à\s H:i', $c ) ),
		] );
		?>
		<article class="card">
			<?php if ( $foto ) { echo wp_get_attachment_image( $foto, 'large', false, [ 'class' => 'capa', 'alt' => '' ] ); } ?>
			<div class="corpo">
				<div class="cab">
					<?php if ( $logo ) { echo wp_get_attachment_image( $logo, 'thumbnail', false, [ 'class' => 'logo', 'alt' => '' ] ); } ?>
					<div>
						<span class="tag <?php echo $ja ? 'ja' : 'novo'; ?>"><?php echo $ja ? 'JÁ É PARCEIRO' : 'PARCEIRO NOVO'; ?></span>
						<p class="nome"><?php echo esc_html( $c->post_title ); ?></p>
						<?php if ( $g( CAV_Solicitacoes::CAND_CAT ) ) : ?><span class="cat"><?php echo esc_html( $g( CAV_Solicitacoes::CAND_CAT ) ); ?></span><?php endif; ?>
					</div>
				</div>

				<div class="ben">
					<b><?php echo esc_html( $g( CAV_Solicitacoes::CAND_BEN ) ); ?></b>
					<?php if ( $regra ) : ?><span>Regra: <?php echo esc_html( $regra ); ?></span><?php endif; ?>
					<?php if ( $lim ) : ?><span>Limite: <?php echo esc_html( $lim ); ?></span><?php endif; ?>
				</div>

				<dl>
					<?php foreach ( $dados as $rotulo => $valor ) : ?>
						<dt><?php echo esc_html( $rotulo ); ?></dt><dd><?php echo $valor; // phpcs:ignore WordPress.Security.EscapeOutput -- montado e escapado acima ?></dd>
					<?php endforeach; ?>
				</dl>

				<?php if ( $desc ) : ?>
					<details><summary>Sobre o negócio</summary><p><?php echo esc_html( $desc ); ?></p></details>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acoes">
					<input type="hidden" name="action" value="cav_aprovar_candidatura">
					<input type="hidden" name="post_id" value="<?php echo esc_attr( $c->ID ); ?>">
					<input type="hidden" name="voltar" value="app">
					<?php wp_nonce_field( 'cav_aprovar_candidatura_' . $c->ID ); ?>
					<button class="recusar" name="decisao" value="recusar"
					        onclick="return confirm('Recusar <?php echo esc_js( $c->post_title ); ?>? O responsável recebe um e-mail.')">Recusar</button>
					<button class="aprovar" name="decisao" value="aprovar"
					        onclick="return confirm('Aprovar <?php echo esc_js( $c->post_title ); ?>?<?php echo $ja ? ' A loja entra no site agora.' : ''; ?>')">Aprovar</button>
				</form>
			</div>
		</article>
	<?php endforeach; ?>

	<p class="rodape"><a href="<?php echo esc_url( admin_url( 'admin.php?page=cav-aprovacoes' ) ); ?>">Abrir as Aprovações completas</a></p>

	<script>
	document.querySelectorAll('form.acoes').forEach(function(f){
		f.addEventListener('submit',function(){ setTimeout(function(){ f.querySelectorAll('button').forEach(function(b){ b.disabled=true; }); },0); });
	});
	</script>
</body>
</html>
		<?php
	}
}
