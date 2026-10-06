<?php
/**
 * Aplica a paleta e as fontes da Alliance no Kit ativo do Elementor.
 * Roda com: wp eval-file scripts/elementor-kit.php
 *
 * O plugin do clube lê essas variáveis globais, então o que for definido aqui
 * aparece nos componentes dele. Se o Kit ainda não existir (o Elementor cria
 * na primeira vez que o editor é aberto), o script avisa e não faz nada.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Plugin' ) ) {
	WP_CLI::warning( 'Elementor não está ativo.' );
	return;
}

$kit_id = (int) get_option( 'elementor_active_kit' );

if ( ! $kit_id || ! get_post( $kit_id ) ) {
	WP_CLI::warning( 'O Elementor ainda não criou o Kit. Abra qualquer página no editor do Elementor uma vez e rode o setup de novo.' );
	return;
}

$cores = [
	[ '_id' => 'primary',   'title' => 'Amarelo Alliance', 'color' => '#FFC629' ],
	[ '_id' => 'secondary', 'title' => 'Cinza',            'color' => '#9A9A9A' ],
	[ '_id' => 'text',      'title' => 'Texto',            'color' => '#FFFFFF' ],
	[ '_id' => 'accent',    'title' => 'Destaque',         'color' => '#FFC629' ],
];

$fonte = static function ( $id, $titulo, $peso ) {
	return [
		'_id'                    => $id,
		'title'                  => $titulo,
		'typography_typography'  => 'custom',
		'typography_font_family' => 'Poppins',
		'typography_font_weight' => $peso,
	];
};

$tipografia = [
	$fonte( 'primary',   'Títulos',  '700' ),
	$fonte( 'secondary', 'Apoio',    '500' ),
	$fonte( 'text',      'Corpo',    '400' ),
	$fonte( 'accent',    'Botões',   '500' ),
];

$ajustes = get_post_meta( $kit_id, '_elementor_page_settings', true );
$ajustes = is_array( $ajustes ) ? $ajustes : [];

$ajustes['system_colors']     = $cores;
$ajustes['system_typography'] = $tipografia;

update_post_meta( $kit_id, '_elementor_page_settings', $ajustes );

\Elementor\Plugin::$instance->files_manager->clear_cache();

WP_CLI::success( 'Paleta e fontes aplicadas no Kit #' . $kit_id . '.' );
