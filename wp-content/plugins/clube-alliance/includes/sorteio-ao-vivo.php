<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sorteio ao vivo.
 *
 * Duas telas, só para a administração:
 *  1. Preparar: escolhe o sorteio e marca quem NÃO participa (contas de teste, equipe...).
 *  2. Tela ao vivo: página cheia, escura, com os nomes girando, para projetar ou transmitir.
 *
 * Quem escolhe o ganhador é o servidor (random_int), no clique de "Sortear". A animação só
 * desacelera até o nome que o servidor devolveu. O resultado fica gravado igual ao botão do editor.
 */
class CAV_SorteioAoVivo {

	const PAGINA     = 'cav-sorteio-ao-vivo';
	const META_NOMES = '_cav_ao_vivo_nomes'; // 'curto' (Rafael M.) ou 'completo'

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 30 );
		add_action( 'admin_init', [ __CLASS__, 'tela_cheia' ] );
		add_action( 'admin_post_cav_ao_vivo_lista', [ __CLASS__, 'salvar_lista' ] );
		add_action( 'wp_ajax_cav_ao_vivo_sortear', [ __CLASS__, 'sortear' ] );
	}

	public static function menu() {
		add_submenu_page( 'cav-relatorio', 'Sorteio ao vivo', 'Sorteio ao vivo', 'cav_gerenciar', self::PAGINA, [ __CLASS__, 'tela' ] );
	}

	private static function url( $args = [] ) {
		return add_query_arg( $args, admin_url( 'admin.php?page=' . self::PAGINA ) );
	}

	private static function nome( $user_id, $modo ) {
		if ( 'completo' === $modo ) {
			$u = get_userdata( $user_id );
			return $u ? $u->display_name : 'Aluno';
		}
		return CAV_Sorteio::nome_publico( $user_id );
	}

	/* --------------------------- 1. Preparar --------------------------- */

	public static function tela() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		$id = isset( $_GET['sorteio'] ) ? absint( $_GET['sorteio'] ) : 0;
		$s  = $id ? get_post( $id ) : null;

		echo '<div class="wrap"><h1>Sorteio ao vivo</h1>';

		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['salvo'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Lista salva.</p></div>';
		}

		if ( ! $s || 'cav_sorteio' !== $s->post_type ) {
			self::escolher();
			echo '</div>';
			return;
		}

		$todos     = CAV_Sorteio::participantes();
		$excluidos = CAV_Sorteio::excluidos( $id );
		$ganhador  = (int) get_post_meta( $id, CAV_Conteudo::META_GANHADOR, true );
		$premio    = (string) get_post_meta( $id, CAV_Conteudo::META_PREMIO, true );
		$modo      = get_post_meta( $id, self::META_NOMES, true ) ?: 'curto';

		usort( $todos, static function ( $a, $b ) {
			return strcasecmp( get_userdata( $a )->display_name, get_userdata( $b )->display_name );
		} );
		$n = count( array_diff( $todos, $excluidos ) );
		?>
		<p><a href="<?php echo esc_url( self::url() ); ?>">← Outros sorteios</a></p>
		<h2 style="margin-bottom:.25rem"><?php echo esc_html( get_the_title( $s ) ); ?></h2>
		<?php if ( $premio ) : ?><p style="margin-top:0">Prêmio: <strong><?php echo esc_html( $premio ); ?></strong></p><?php endif; ?>

		<?php if ( $ganhador ) : ?>
			<div class="notice notice-info inline"><p>Este sorteio já foi apurado. Ganhador: <strong><?php echo esc_html( self::nome( $ganhador, 'completo' ) ); ?></strong>. A tela ao vivo mostra o resultado; para sortear de novo, use "Refazer o sorteio" no sorteio.</p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cav_ao_vivo_lista">
			<input type="hidden" name="sorteio" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'cav_ao_vivo_' . $id ); ?>

			<h3>1. Quem participa</h3>
			<p class="description">Todos os alunos com o clube ativo já vêm marcados. Desmarque quem não deve concorrer, como contas de teste ou gente da equipe, e salve.</p>
			<p>
				<input type="search" id="cav-filtro" placeholder="Buscar nome" style="min-width:260px">
				<button type="button" class="button" id="cav-todos">Marcar todos</button>
				<strong style="margin-left:1rem"><span id="cav-n"><?php echo (int) $n; ?></span> de <?php echo count( $todos ); ?> participando</strong>
			</p>
			<div id="cav-lista" style="columns:3 260px;column-gap:2rem;background:#fff;border:1px solid #dcdcde;padding:1rem 1.25rem;max-width:1100px">
				<?php foreach ( $todos as $uid ) : $u = get_userdata( $uid ); ?>
					<label style="display:block;break-inside:avoid;padding:.2rem 0">
						<input type="checkbox" name="participa[]" value="<?php echo (int) $uid; ?>" <?php checked( ! in_array( $uid, $excluidos, true ) ); ?>>
						<?php echo esc_html( $u->display_name ); ?>
					</label>
				<?php endforeach; ?>
				<?php if ( ! $todos ) : ?><em>Nenhum aluno com o clube ativo.</em><?php endif; ?>
			</div>

			<h3>2. Como os nomes aparecem na tela</h3>
			<label style="margin-right:1.5rem"><input type="radio" name="nomes" value="curto" <?php checked( 'curto', $modo ); ?>> Nome e inicial do sobrenome (Rafael M.)</label>
			<label><input type="radio" name="nomes" value="completo" <?php checked( 'completo', $modo ); ?>> Nome completo</label>
			<p class="description">Se for transmitir no Instagram, o nome curto protege quem está concorrendo.</p>

			<p style="margin-top:1.5rem">
				<button class="button button-primary button-hero" name="depois" value="salvar">Salvar lista</button>
				<button class="button button-hero" name="depois" value="abrir">Salvar e abrir a tela ao vivo</button>
			</p>
		</form>
		<script>
		(function(){
			var l=document.getElementById('cav-lista'),n=document.getElementById('cav-n');
			function conta(){n.textContent=l.querySelectorAll('input:checked').length;}
			l.addEventListener('change',conta);
			document.getElementById('cav-todos').onclick=function(){l.querySelectorAll('input').forEach(function(i){i.checked=true});conta();};
			document.getElementById('cav-filtro').oninput=function(){var q=this.value.toLowerCase();l.querySelectorAll('label').forEach(function(x){x.style.display=x.textContent.toLowerCase().indexOf(q)>-1?'block':'none'});};
		})();
		</script>
		</div>
		<?php
	}

	private static function escolher() {
		$lista = get_posts( [ 'post_type' => 'cav_sorteio', 'post_status' => 'publish', 'numberposts' => 30, 'orderby' => 'date', 'order' => 'DESC' ] );
		echo '<p>Escolha o sorteio:</p>';
		if ( ! $lista ) {
			echo '<p><em>Nenhum sorteio publicado.</em></p>';
			return;
		}
		echo '<table class="widefat striped" style="max-width:800px"><thead><tr><th>Sorteio</th><th>Situação</th><th></th></tr></thead><tbody>';
		foreach ( $lista as $p ) {
			$g = (int) get_post_meta( $p->ID, CAV_Conteudo::META_GANHADOR, true );
			printf(
				'<tr><td><strong>%s</strong></td><td>%s</td><td><a class="button" href="%s">%s</a></td></tr>',
				esc_html( get_the_title( $p ) ),
				$g ? 'Apurado: ' . esc_html( self::nome( $g, 'completo' ) ) : 'Ainda não sorteado',
				esc_url( self::url( [ 'sorteio' => $p->ID ] ) ),
				$g ? 'Ver' : 'Preparar'
			);
		}
		echo '</tbody></table>';
	}

	public static function salvar_lista() {
		$id = isset( $_POST['sorteio'] ) ? absint( $_POST['sorteio'] ) : 0;
		if ( ! $id || ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_ao_vivo_' . $id );

		$marcados = isset( $_POST['participa'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['participa'] ) ) : [];
		$fora     = array_values( array_diff( CAV_Sorteio::participantes(), $marcados ) );
		// Mantém quem já estava fora e hoje está inativo (se voltar a ficar ativo, continua fora).
		$fora = array_values( array_unique( array_merge( $fora, array_diff( CAV_Sorteio::excluidos( $id ), CAV_Sorteio::participantes() ) ) ) );
		update_post_meta( $id, CAV_Sorteio::META_EXCLUIDOS, $fora );

		$modo = isset( $_POST['nomes'] ) && 'completo' === $_POST['nomes'] ? 'completo' : 'curto';
		update_post_meta( $id, self::META_NOMES, $modo );

		$abrir = isset( $_POST['depois'] ) && 'abrir' === $_POST['depois'];
		wp_safe_redirect( self::url( $abrir ? [ 'sorteio' => $id, 'tela' => 1 ] : [ 'sorteio' => $id, 'salvo' => 1 ] ) );
		exit;
	}

	/* --------------------------- 2. Tela ao vivo --------------------------- */

	public static function tela_cheia() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page'], $_GET['tela'] ) || self::PAGINA !== $_GET['page'] ) {
			return;
		}
		$id = isset( $_GET['sorteio'] ) ? absint( $_GET['sorteio'] ) : 0;
		// phpcs:enable
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		$s = get_post( $id );
		if ( ! $s || 'cav_sorteio' !== $s->post_type ) {
			wp_die( 'Sorteio não encontrado.' );
		}

		nocache_headers();
		$modo     = get_post_meta( $id, self::META_NOMES, true ) ?: 'curto';
		$ids      = CAV_Sorteio::participantes_do_sorteio( $id );
		$nomes    = array_map( static function ( $u ) use ( $modo ) { return self::nome( $u, $modo ); }, $ids );
		$premio   = (string) get_post_meta( $id, CAV_Conteudo::META_PREMIO, true );
		$ganhador = (int) get_post_meta( $id, CAV_Conteudo::META_GANHADOR, true );
		$foto     = has_post_thumbnail( $id ) ? get_the_post_thumbnail_url( $id, 'large' ) : '';
		$logo     = get_stylesheet_directory_uri() . '/assets/logo-alliance-mogi.svg';

		$dados = [
			'ajax'     => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'cav_ao_vivo_sortear_' . $id ),
			'sorteio'  => $id,
			'nomes'    => array_values( $nomes ),
			'ganhador' => $ganhador ? self::nome( $ganhador, $modo ) : '',
		];
		?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sorteio ao vivo · <?php echo esc_html( get_the_title( $s ) ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,500;0,700;0,800;1,500&display=swap" rel="stylesheet">
<style>
:root{--ouro:#ffc629;--preto:#0d0d0d}
*{box-sizing:border-box;margin:0}
html,body{height:100%}
body{background:radial-gradient(ellipse at 50% 35%,#262019 0%,#111 45%,#070707 100%);color:#fff;font-family:Poppins,system-ui,sans-serif;overflow:hidden;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:4vh 4vw;gap:3.2vh}
.logo{height:6vh;opacity:.95}
.eyebrow{color:var(--ouro);letter-spacing:.42em;font-size:clamp(12px,1.25vw,20px);font-weight:500;text-transform:uppercase}
.premio{display:flex;align-items:center;gap:2vw;justify-content:center}
.premio img{height:16vh;width:16vh;object-fit:cover;border:3px solid var(--ouro)}
.premio h1{font-size:clamp(26px,3.6vw,64px);font-weight:800;line-height:1.05;max-width:70vw}
.premio img+h1{text-align:left;max-width:60vw}
.palco{position:relative;width:min(1100px,92vw);height:clamp(140px,22vh,260px);border:3px solid var(--ouro);background:linear-gradient(180deg,rgba(255,198,41,.06),rgba(255,198,41,.02));overflow:hidden;display:flex;align-items:center;justify-content:center}
.palco::before,.palco::after{content:"";position:absolute;left:0;right:0;height:30%;z-index:2;pointer-events:none}
.palco::before{top:0;background:linear-gradient(#111,transparent)}
.palco::after{bottom:0;background:linear-gradient(transparent,#111)}
#nome{font-size:clamp(34px,6.4vw,118px);font-weight:800;letter-spacing:-.01em;white-space:nowrap;transition:transform .08s,color .3s}
#nome.giro{filter:blur(.6px)}
.ganhou #nome{color:var(--ouro);transform:scale(1.06)}
.ganhou .palco{box-shadow:0 0 0 6px rgba(255,198,41,.25),0 0 80px rgba(255,198,41,.35)}
#rotulo{min-height:1.4em;color:var(--ouro);font-size:clamp(16px,2vw,34px);font-weight:700;letter-spacing:.18em;text-transform:uppercase;opacity:0;transition:opacity .4s}
.ganhou #rotulo{opacity:1}
.info{color:#bdbdbd;font-size:clamp(13px,1.2vw,20px)}
button{font-family:inherit;background:var(--ouro);color:#111;border:0;font-weight:800;font-size:clamp(18px,2vw,30px);letter-spacing:.2em;padding:1.1rem 3.2rem;cursor:pointer;text-transform:uppercase;transition:transform .15s,opacity .2s}
button:hover{transform:translateY(-2px)}
button:disabled{opacity:.35;cursor:default;transform:none}
.dica{position:fixed;bottom:2vh;right:2vw;color:#666;font-size:12px}
.sair{position:fixed;top:2vh;left:2vw;color:#666;font-size:12px;text-decoration:none}
canvas{position:fixed;inset:0;pointer-events:none;z-index:5}
.erro{color:#ff8a80}
</style></head>
<body>
<a class="sair" href="<?php echo esc_url( self::url( [ 'sorteio' => $id ] ) ); ?>">← voltar</a>
<img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="Alliance Mogi das Cruzes">
<div class="eyebrow">Clube de Vantagens · Sorteio</div>
<div class="premio">
	<?php if ( $foto ) : ?><img src="<?php echo esc_url( $foto ); ?>" alt=""><?php endif; ?>
	<h1><?php echo esc_html( $premio ?: get_the_title( $s ) ); ?></h1>
</div>
<div class="palco"><div id="nome"><?php echo esc_html( $ganhador ? $dados['ganhador'] : '?' ); ?></div></div>
<div id="rotulo"><?php echo $ganhador ? 'Ganhador(a)!' : ''; ?></div>
<div class="info" id="info"><?php echo count( $ids ); ?> participantes concorrendo</div>
<button id="bt" <?php disabled( $ganhador || ! $ids ); ?>><?php echo $ganhador ? 'Sorteado' : 'Sortear'; ?></button>
<div class="dica">Tecla F: tela cheia · Espaço: sortear</div>
<canvas id="cf"></canvas>
<script>
(function(){
var D=<?php echo wp_json_encode( $dados ); ?>;
var el=document.getElementById('nome'),bt=document.getElementById('bt'),info=document.getElementById('info');
if(D.ganhador){document.body.classList.add('ganhou');}
var i=0,rodando=false,parado=false,vel=55,alvo=null,inicio=0;
function mostra(){el.textContent=D.nomes[i%D.nomes.length];i++;}
function passo(){
  if(!rodando)return;
  var t=performance.now()-inicio;
  if(alvo!==null && t>4200){vel*=1.13;} // desacelera depois que o servidor respondeu
  if(alvo!==null && vel>520){rodando=false;el.classList.remove('giro');el.textContent=alvo;fim();return;}
  mostra();setTimeout(passo,vel);
}
function fim(){document.getElementById('rotulo').textContent='Ganhador(a)!';document.body.classList.add('ganhou');bt.textContent='Sorteado';confete();}
function sortear(){
  if(rodando||parado||bt.disabled||!D.nomes.length)return;
  parado=true;bt.disabled=true;rodando=true;inicio=performance.now();el.classList.add('giro');passo();
  var f=new FormData();f.append('action','cav_ao_vivo_sortear');f.append('sorteio',D.sorteio);f.append('_wpnonce',D.nonce);
  fetch(D.ajax,{method:'POST',body:f,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(j){
    if(!j.success){throw new Error(j.data||'Erro ao sortear');}
    alvo=j.data.nome;info.textContent=j.data.total+' participantes concorreram';
  }).catch(function(e){rodando=false;el.classList.remove('giro');el.textContent='—';info.innerHTML='<span class="erro">'+e.message+'</span>';});
}
bt.addEventListener('click',sortear);
document.addEventListener('keydown',function(e){
  if(e.code==='Space'){e.preventDefault();sortear();}
  if(e.key==='f'||e.key==='F'){document.fullscreenElement?document.exitFullscreen():document.documentElement.requestFullscreen();}
});
// confete
function confete(){
  var c=document.getElementById('cf'),x=c.getContext('2d');c.width=innerWidth;c.height=innerHeight;
  var cores=['#ffc629','#ffffff','#e0a800','#fff1c2'],p=[];
  for(var k=0;k<260;k++)p.push({x:innerWidth/2+(Math.random()-.5)*200,y:innerHeight*.45,vx:(Math.random()-.5)*22,vy:-Math.random()*20-6,s:Math.random()*8+4,r:Math.random()*6,vr:(Math.random()-.5)*.3,c:cores[k%4]});
  var t0=performance.now();
  (function loop(t){x.clearRect(0,0,c.width,c.height);var vivo=t-t0<6000;
    p.forEach(function(q){q.vy+=.45;q.vx*=.99;q.x+=q.vx;q.y+=q.vy;q.r+=q.vr;x.save();x.translate(q.x,q.y);x.rotate(q.r);x.fillStyle=q.c;x.fillRect(-q.s/2,-q.s/4,q.s,q.s/2);x.restore();});
    if(vivo)requestAnimationFrame(loop);else x.clearRect(0,0,c.width,c.height);})(t0);
}
// antes de sortear, os nomes passam devagar, para dar clima
if(!D.ganhador&&D.nomes.length){var ocioso=setInterval(function(){if(parado){clearInterval(ocioso);return;}mostra();},900);}
})();
</script>
</body></html>
		<?php
		exit;
	}

	/** Clique em "Sortear": escolhe, grava e avisa o ganhador. */
	public static function sortear() {
		$id = isset( $_POST['sorteio'] ) ? absint( $_POST['sorteio'] ) : 0;
		if ( ! $id || ! current_user_can( 'cav_gerenciar' ) || ! check_ajax_referer( 'cav_ao_vivo_sortear_' . $id, '_wpnonce', false ) ) {
			wp_send_json_error( 'Sem permissão. Recarregue a página.', 403 );
		}
		$s = get_post( $id );
		if ( ! $s || 'cav_sorteio' !== $s->post_type || 'publish' !== $s->post_status ) {
			wp_send_json_error( 'Sorteio não encontrado.' );
		}
		if ( get_post_meta( $id, CAV_Conteudo::META_GANHADOR, true ) ) {
			wp_send_json_error( 'Este sorteio já foi apurado.' );
		}

		$ids = CAV_Sorteio::participantes_do_sorteio( $id );
		if ( ! $ids ) {
			wp_send_json_error( 'Nenhum participante para sortear.' );
		}

		$ganhador = (int) $ids[ random_int( 0, count( $ids ) - 1 ) ];
		update_post_meta( $id, CAV_Conteudo::META_GANHADOR, $ganhador );
		update_post_meta( $id, '_cav_participantes_total', count( $ids ) );
		update_post_meta( $id, CAV_Conteudo::META_APURADO, current_time( 'mysql' ) );

		CAV_Emails::sorteio_ganhador( $id, $ganhador );
		do_action( 'cav_sorteio_apurado', $id, $ganhador );

		$modo = get_post_meta( $id, self::META_NOMES, true ) ?: 'curto';
		wp_send_json_success( [ 'nome' => self::nome( $ganhador, $modo ), 'total' => count( $ids ) ] );
	}
}
