<?php
/**
 * Rodapé do clube. Substitui o do Hello Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<footer class="clube-rodape" id="site-footer">
	<div class="clube-rodape__in">

		<div class="clube-rodape__marca">
			<strong>Alliance</strong>
			<span>Clube de Vantagens</span>
			<p>Descontos nos comércios de Mogi das Cruzes para quem treina na Alliance.</p>
		</div>

		<nav class="clube-rodape__col" aria-label="Rodapé">
			<p class="clube-olho">O clube</p>
			<ul>
				<li><a href="<?php echo esc_url( home_url( '/o-que-e/' ) ); ?>">O que é o clube</a></li>
				<li><a href="<?php echo esc_url( home_url( '/parceiros/' ) ); ?>">Parceiros</a></li>
				<li><a href="<?php echo esc_url( home_url( '/quero-fazer-parte/' ) ); ?>">Quero fazer parte</a></li>
				<li><a href="<?php echo esc_url( home_url( '/seja-parceiro/' ) ); ?>">Seja parceiro</a></li>
				<li><a href="<?php echo esc_url( home_url( '/entrar/' ) ); ?>">Entrar</a></li>
			</ul>
		</nav>

		<div class="clube-rodape__col">
			<p class="clube-olho">Dúvidas</p>
			<p>Fale com a recepção da Alliance Mogi das Cruzes.</p>
			<p><a href="https://alliancemogi.com.br/" rel="noopener">alliancemogi.com.br</a></p>
		</div>

	</div>

	<div class="clube-rodape__fim">
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Alliance Mogi das Cruzes. Clube de vantagens para alunos.</p>
	</div>
</footer>

<script>
(function () {
	var botao = document.querySelector('.clube-menu-btn');
	var topo = document.querySelector('.clube-topo');
	if (!botao || !topo) { return; }
	botao.addEventListener('click', function () {
		var aberto = topo.classList.toggle('clube-topo--aberto');
		botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
	});
})();
</script>
