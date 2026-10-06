<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Install {

	const DB_VERSION = '4';

	public static function activate() {
		self::criar_tabelas();
		self::criar_roles();
		update_option( 'cav_db_version', self::DB_VERSION );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'cav_db_version' ) !== self::DB_VERSION ) {
			self::criar_tabelas();
			self::criar_roles();
			update_option( 'cav_db_version', self::DB_VERSION );
		}
	}

	public static function tabela_usos() {
		global $wpdb;
		return $wpdb->prefix . 'cav_usos';
	}

	public static function tabela_inscricoes() {
		global $wpdb;
		return $wpdb->prefix . 'cav_inscricoes';
	}

	public static function tabela_consultas() {
		global $wpdb;
		return $wpdb->prefix . 'cav_consultas';
	}

	private static function criar_tabelas() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$usos    = self::tabela_usos();
		$logs    = self::tabela_consultas();

		dbDelta( "CREATE TABLE {$usos} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			membro_id BIGINT UNSIGNED NOT NULL,
			beneficio_id BIGINT UNSIGNED NOT NULL,
			parceiro_id BIGINT UNSIGNED NOT NULL,
			operador_id BIGINT UNSIGNED NOT NULL,
			criado_em DATETIME NOT NULL,
			ip VARCHAR(45) NULL,
			PRIMARY KEY (id),
			KEY membro_id (membro_id),
			KEY parceiro_id (parceiro_id),
			KEY beneficio_id (beneficio_id),
			KEY criado_em (criado_em)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$logs} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			operador_id BIGINT UNSIGNED NOT NULL,
			cpf_hash CHAR(64) NOT NULL,
			resultado VARCHAR(20) NOT NULL,
			criado_em DATETIME NOT NULL,
			ip VARCHAR(45) NULL,
			PRIMARY KEY (id),
			KEY operador_id (operador_id),
			KEY criado_em (criado_em)
		) {$charset};" );

		$insc = self::tabela_inscricoes();

		dbDelta( "CREATE TABLE {$insc} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			sorteio_id BIGINT UNSIGNED NOT NULL,
			membro_id BIGINT UNSIGNED NOT NULL,
			criado_em DATETIME NOT NULL,
			ip VARCHAR(45) NULL,
			PRIMARY KEY (id),
			UNIQUE KEY sorteio_membro (sorteio_id, membro_id),
			KEY membro_id (membro_id)
		) {$charset};" );
	}

	private static function criar_roles() {
		add_role( 'cav_membro', 'Membro do Clube', [ 'read' => true ] );

		add_role( 'cav_parceiro', 'Parceiro do Clube', [
			'read'               => true,
			'cav_consultar'      => true,
			'cav_registrar_uso'  => true,
			'cav_ver_relatorio'  => true,
			'upload_files'       => true,
			'cav_editar_negocio' => true,
		] );

		// add_role não mexe num papel que já existe: garante a permissão nova em sites já instalados.
		$papel_parceiro = get_role( 'cav_parceiro' );
		if ( $papel_parceiro ) {
			$papel_parceiro->add_cap( 'cav_editar_negocio' );
		}

		add_role( 'cav_recepcao', 'Recepção do Clube', [
			'read'        => true,
			'cav_aprovar' => true,
		] );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'cav_consultar' );
			$admin->add_cap( 'cav_registrar_uso' );
			$admin->add_cap( 'cav_ver_relatorio' );
			$admin->add_cap( 'cav_gerenciar' );
			$admin->add_cap( 'cav_aprovar' );
			$admin->add_cap( 'cav_editar_negocio' );
		}
	}
}
