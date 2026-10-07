<?php
/**
 * Plugin Name: Clube Alliance
 * Description: Clube de vantagens — elegibilidade por CPF, terminal do parceiro e registro de uso de benefícios.
 * Version:     0.9.0
 * Author:      Erick
 * Text Domain: clube-alliance
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CAV_VERSION', '0.9.0' );
define( 'CAV_FILE', __FILE__ );
define( 'CAV_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAV_URL', plugin_dir_url( __FILE__ ) );

require_once CAV_PATH . 'includes/cpf.php';
require_once CAV_PATH . 'includes/config.php';
require_once CAV_PATH . 'includes/install.php';
require_once CAV_PATH . 'includes/membro.php';
require_once CAV_PATH . 'includes/parceiro.php';
require_once CAV_PATH . 'includes/usos.php';
require_once CAV_PATH . 'includes/acesso.php';
require_once CAV_PATH . 'includes/sorteio.php';
require_once CAV_PATH . 'includes/conteudo.php';
require_once CAV_PATH . 'includes/emails.php';
require_once CAV_PATH . 'includes/solicitacoes.php';
require_once CAV_PATH . 'includes/aprovacoes.php';
require_once CAV_PATH . 'includes/entrada.php';
require_once CAV_PATH . 'includes/ofertas.php';
require_once CAV_PATH . 'includes/agenda.php';
require_once CAV_PATH . 'includes/area-membro.php';
require_once CAV_PATH . 'includes/area-parceiro.php';
require_once CAV_PATH . 'includes/importar.php';
require_once CAV_PATH . 'includes/google.php';
require_once CAV_PATH . 'includes/login.php';
require_once CAV_PATH . 'includes/rest.php';
require_once CAV_PATH . 'includes/shortcodes.php';
require_once CAV_PATH . 'includes/admin.php';

register_activation_hook( __FILE__, [ 'CAV_Install', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'CAV_Install', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
	CAV_Install::maybe_upgrade();
	CAV_Config::init();
	CAV_Membro::init();
	CAV_Parceiro::init();
	CAV_Acesso::init();
	CAV_Conteudo::init();
	CAV_Solicitacoes::init();
	CAV_Aprovacoes::init();
	CAV_Entrada::init();
	CAV_Ofertas::init();
	CAV_Agenda::init();
	CAV_Area::init();
	CAV_AreaParceiro::init();
	CAV_Importar::init();
	CAV_Login::init();
	CAV_Rest::init();
	CAV_Shortcodes::init();
	CAV_Admin::init();
} );

/**
 * Post type usado para os parceiros.
 *
 * Por padrão o plugin registra o CPT "cav_parceiro". Se você adotar o Voxel,
 * o GeoDirectory ou qualquer outro diretório, basta apontar para o post type
 * deles no filtro abaixo e todo o resto continua funcionando:
 *
 *   add_filter( 'cav_parceiro_post_type', fn() => 'places' );
 */
function cav_parceiro_post_type() {
	return apply_filters( 'cav_parceiro_post_type', 'cav_parceiro' );
}
