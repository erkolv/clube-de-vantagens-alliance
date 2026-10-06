<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CAV_Membro {

	const META_CPF       = 'cav_cpf';
	const META_VALIDADE  = 'cav_validade';
	const META_BLOQUEADO = 'cav_bloqueado';
	const META_CONSENTE  = 'cav_consentimento';

	public static function init() {
		add_action( 'show_user_profile', [ __CLASS__, 'campos_perfil' ] );
		add_action( 'edit_user_profile', [ __CLASS__, 'campos_perfil' ] );
		add_action( 'personal_options_update', [ __CLASS__, 'salvar_perfil' ] );
		add_action( 'edit_user_profile_update', [ __CLASS__, 'salvar_perfil' ] );

		add_filter( 'manage_users_columns', [ __CLASS__, 'coluna_users' ] );
		add_filter( 'manage_users_custom_column', [ __CLASS__, 'conteudo_coluna_users' ], 10, 3 );
	}

	/**
	 * Resultado da checagem de elegibilidade.
	 *
	 * @return array{elegivel:bool, motivo:string}
	 */
	public static function checar( WP_User $user ) {
		if ( get_user_meta( $user->ID, self::META_BLOQUEADO, true ) === '1' ) {
			return [ 'elegivel' => false, 'motivo' => 'bloqueado' ];
		}

		$status = get_user_meta( $user->ID, 'cav_status', true );

		if ( 'pendente' === $status ) {
			return [ 'elegivel' => false, 'motivo' => 'pendente' ];
		}
		if ( 'recusado' === $status ) {
			return [ 'elegivel' => false, 'motivo' => 'recusado' ];
		}

		$validade = get_user_meta( $user->ID, self::META_VALIDADE, true );

		if ( ! $validade ) {
			return [ 'elegivel' => false, 'motivo' => 'sem_validade' ];
		}

		// Compara em data local do site, sem hora.
		$hoje = current_time( 'Y-m-d' );

		if ( $validade < $hoje ) {
			return [ 'elegivel' => false, 'motivo' => 'expirado' ];
		}

		return [ 'elegivel' => true, 'motivo' => 'ok' ];
	}

	public static function motivo_legivel( $motivo ) {
		$mapa = [
			'ok'           => 'Membro ativo',
			'bloqueado'    => 'Cadastro bloqueado',
			'sem_validade' => 'Cadastro sem matrícula vinculada',
			'expirado'     => 'Matrícula vencida',
			'nao_membro'   => 'CPF não cadastrado no clube',
			'pendente'     => 'Cadastro aguardando aprovação da academia',
			'recusado'     => 'Cadastro não aprovado',
		];
		return $mapa[ $motivo ] ?? 'Não elegível';
	}

	public static function primeiro_nome( WP_User $user ) {
		$nome = $user->first_name ?: $user->display_name;
		$nome = trim( $nome );
		$partes = preg_split( '/\s+/', $nome );
		return $partes ? $partes[0] : '';
	}

	public static function validade( $user_id ) {
		return get_user_meta( $user_id, self::META_VALIDADE, true );
	}

	/**
	 * Define a validade do membro. Usado pelo perfil e, mais tarde,
	 * pela rotina de sincronização com a lista de alunos ativos.
	 */
	public static function set_validade( $user_id, $data ) {
		$data = sanitize_text_field( $data );
		if ( $data && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ) {
			return false;
		}
		update_user_meta( $user_id, self::META_VALIDADE, $data );
		return true;
	}

	/** Grava o CPF garantindo que não exista em outro usuário. */
	public static function set_cpf( $user_id, $cpf ) {
		$cpf = CAV_CPF::normalizar( $cpf );

		if ( '' === $cpf ) {
			delete_user_meta( $user_id, self::META_CPF );
			return true;
		}

		if ( ! CAV_CPF::valido( $cpf ) ) {
			return new WP_Error( 'cpf_invalido', 'CPF inválido.' );
		}

		$dono = CAV_CPF::buscar_usuario( $cpf );
		if ( $dono && (int) $dono->ID !== (int) $user_id ) {
			return new WP_Error( 'cpf_duplicado', 'Este CPF já está vinculado a outro cadastro.' );
		}

		update_user_meta( $user_id, self::META_CPF, $cpf );
		return true;
	}

	public static function campos_perfil( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$cpf       = get_user_meta( $user->ID, self::META_CPF, true );
		$validade  = get_user_meta( $user->ID, self::META_VALIDADE, true );
		$bloqueado = get_user_meta( $user->ID, self::META_BLOQUEADO, true );
		$check     = self::checar( $user );
		?>
		<h2>Clube de Vantagens</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="cav_cpf">CPF</label></th>
				<td>
					<input type="text" name="cav_cpf" id="cav_cpf" class="regular-text"
					       value="<?php echo esc_attr( CAV_CPF::formatar( $cpf ) ); ?>">
					<p class="description">Usado pelo parceiro para verificar o benefício.</p>
				</td>
			</tr>
			<tr>
				<th><label for="cav_validade">Válido até</label></th>
				<td>
					<input type="date" name="cav_validade" id="cav_validade"
					       value="<?php echo esc_attr( $validade ); ?>">
					<p class="description">Data em que o acesso expira se a matrícula não for renovada.</p>
				</td>
			</tr>
			<tr>
				<th>Bloqueio</th>
				<td>
					<label>
						<input type="checkbox" name="cav_bloqueado" value="1" <?php checked( $bloqueado, '1' ); ?>>
						Bloquear este membro independente da validade
					</label>
				</td>
			</tr>
			<tr>
				<th>Situação atual</th>
				<td>
					<strong style="color:<?php echo $check['elegivel'] ? '#1a7f37' : '#b32d2e'; ?>">
						<?php echo esc_html( self::motivo_legivel( $check['motivo'] ) ); ?>
					</strong>
				</td>
			</tr>
		</table>
		<?php wp_nonce_field( 'cav_salvar_perfil', 'cav_perfil_nonce' ); ?>
		<?php
	}

	public static function salvar_perfil( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['cav_perfil_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cav_perfil_nonce'] ) ), 'cav_salvar_perfil' ) ) {
			return;
		}

		if ( isset( $_POST['cav_cpf'] ) ) {
			$res = self::set_cpf( $user_id, sanitize_text_field( wp_unslash( $_POST['cav_cpf'] ) ) );
			if ( is_wp_error( $res ) ) {
				set_transient( 'cav_erro_perfil_' . $user_id, $res->get_error_message(), 60 );
			}
		}

		if ( isset( $_POST['cav_validade'] ) ) {
			self::set_validade( $user_id, wp_unslash( $_POST['cav_validade'] ) );
		}

		update_user_meta( $user_id, self::META_BLOQUEADO, isset( $_POST['cav_bloqueado'] ) ? '1' : '' );
	}

	public static function coluna_users( $cols ) {
		$cols['cav_situacao'] = 'Clube';
		return $cols;
	}

	public static function conteudo_coluna_users( $valor, $col, $user_id ) {
		if ( 'cav_situacao' !== $col ) {
			return $valor;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! get_user_meta( $user_id, self::META_CPF, true ) ) {
			return '—';
		}
		$check = self::checar( $user );
		$cor   = $check['elegivel'] ? '#1a7f37' : '#b32d2e';
		return '<span style="color:' . $cor . '">' . esc_html( self::motivo_legivel( $check['motivo'] ) ) . '</span>';
	}
}
