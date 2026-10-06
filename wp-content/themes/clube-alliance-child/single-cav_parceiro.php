<?php
/**
 * Página de um parceiro: apresentação e benefícios vigentes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$id         = get_the_ID();
	$categorias = get_the_terms( $id, 'cav_categoria' );
	$beneficios = class_exists( 'CAV_Parceiro' ) ? CAV_Parceiro::beneficios( $id ) : [];
	?>
	<main id="content" class="clube-pagina">

		<section class="clube-topo-pagina">
			<div class="clube-env">
				<p class="clube-olho">
					<a href="<?php echo esc_url( get_post_type_archive_link( 'cav_parceiro' ) ); ?>">&larr; Parceiros</a>
				</p>
				<h1><?php the_title(); ?></h1>
				<?php if ( $categorias && ! is_wp_error( $categorias ) ) : ?>
					<p class="clube-tags">
						<?php foreach ( $categorias as $c ) : ?>
							<span class="clube-tag"><?php echo esc_html( $c->name ); ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			</div>
		</section>

		<section class="clube-bloco">
			<div class="clube-env clube-parceiro">

				<div class="clube-parceiro__texto">
					<?php if ( has_post_thumbnail() ) : ?>
						<div class="clube-parceiro__foto"><?php the_post_thumbnail( 'large' ); ?></div>
					<?php endif; ?>
					<div class="clube-conteudo"><?php the_content(); ?></div>
				</div>

				<aside class="clube-parceiro__beneficios">
					<p class="clube-olho">Benefícios para alunos</p>

					<?php if ( $beneficios ) : ?>
						<ul class="clube-beneficios">
							<?php foreach ( $beneficios as $b ) : ?>
								<?php $regra = get_post_meta( $b->ID, CAV_Parceiro::META_BEN_REGRA, true ); ?>
								<li>
									<span class="clube-tag <?php echo CAV_Parceiro::e_temporario( $b->ID ) ? '' : 'clube-tag--neutra'; ?>">
										<?php echo esc_html( CAV_Parceiro::rotulo_vigencia( $b->ID ) ); ?>
									</span>
									<h3><?php echo esc_html( get_the_title( $b ) ); ?></h3>
									<?php if ( $regra ) : ?>
										<p><?php echo esc_html( $regra ); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="clube-vazio">Este parceiro não tem benefício no ar agora.</p>
					<?php endif; ?>

					<div class="clube-como">
						<p><strong>Como usar</strong></p>
						<p>Informe seu CPF no caixa. O parceiro confere na hora e aplica o desconto. Não precisa mostrar nada.</p>
						<?php if ( ! is_user_logged_in() ) : ?>
							<p><a class="clube-btn" href="<?php echo esc_url( home_url( '/quero-fazer-parte/' ) ); ?>">Quero fazer parte</a></p>
						<?php endif; ?>
					</div>
				</aside>

			</div>
		</section>

	</main>
	<?php
endwhile;

get_footer();
