<?php
/**
 * Dados de demonstração: um parceiro com dois benefícios e três contas de teste.
 * Roda com: ./scripts/setup.sh --demo
 *
 * Só funciona em ambiente local (WP_ENVIRONMENT_TYPE = local). A trava existe
 * para ninguém criar contas com senha de teste num site de verdade.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Os dados de demonstração só rodam em ambiente local.' );
}

if ( ! class_exists( 'CAV_Membro' ) ) {
	WP_CLI::error( 'O plugin clube-alliance não está ativo.' );
}

$senha = getenv( 'DEMO_PASSWORD' ) ?: 'demo123';

/** Cria o usuário se ainda não existir. */
$criar_usuario = static function ( $login, $email, $nome, $papel ) use ( $senha ) {
	$existente = get_user_by( 'login', $login );

	if ( $existente ) {
		return (int) $existente->ID;
	}

	$id = wp_insert_user( [
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => $senha,
		'display_name' => $nome,
		'first_name'   => strtok( $nome, ' ' ),
		'role'         => $papel,
	] );

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}

	return (int) $id;
};

/* ---- Parceiro -------------------------------------------------------- */

$existentes = get_posts( [
	'post_type'   => cav_parceiro_post_type(),
	'title'       => 'Barbearia Corte Reto',
	'post_status' => 'any',
	'numberposts' => 1,
] );

if ( $existentes ) {
	$parceiro_id = (int) $existentes[0]->ID;
} else {
	$parceiro_id = wp_insert_post( [
		'post_type'    => cav_parceiro_post_type(),
		'post_status'  => 'publish',
		'post_title'   => 'Barbearia Corte Reto',
		'post_content' => 'Barbearia no Centro de Mogi das Cruzes. Segunda a sábado, das 9h às 20h.',
	] );
}

$beneficio = static function ( $titulo, $regra, $limite, $fim ) use ( $parceiro_id ) {
	$ja = get_posts( [
		'post_type'   => 'cav_beneficio',
		'title'       => $titulo,
		'post_status' => 'any',
		'numberposts' => 1,
	] );

	if ( $ja ) {
		return;
	}

	$id = wp_insert_post( [
		'post_type'   => 'cav_beneficio',
		'post_status' => 'publish',
		'post_title'  => $titulo,
	] );

	update_post_meta( $id, CAV_Parceiro::META_BEN_PARCEIRO, $parceiro_id );
	update_post_meta( $id, CAV_Parceiro::META_BEN_REGRA, $regra );
	update_post_meta( $id, CAV_Parceiro::META_BEN_LIMITE, $limite );
	update_post_meta( $id, CAV_Parceiro::META_BEN_ATIVO, '1' );
	update_post_meta( $id, CAV_Parceiro::META_BEN_FIM, $fim );
};

$beneficio( 'Corte de cabelo — 30% off', 'Promoção, segunda a quinta, uma vez por semana', 'semana', wp_date( 'Y-m-d', strtotime( '+30 days' ) ) );
$beneficio( 'Barba — 10% off', 'Permanente, sem limite de uso', 'nenhum', '' );

// Dados do negócio, para a página do parceiro não ficar vazia.
if ( class_exists( 'CAV_AreaParceiro' ) && ! get_post_meta( $parceiro_id, CAV_AreaParceiro::META_TELEFONE, true ) ) {
	wp_update_post( [
		'ID'           => $parceiro_id,
		'post_excerpt' => 'Barbearia clássica no Centro, com hora marcada.',
	] );
	update_post_meta( $parceiro_id, CAV_AreaParceiro::META_TELEFONE, '(11) 98765-4321' );
	update_post_meta( $parceiro_id, CAV_AreaParceiro::META_ENDERECO, 'Rua Barão de Jaceguai, 100, Centro, Mogi das Cruzes' );
	update_post_meta( $parceiro_id, CAV_AreaParceiro::META_HORARIO, "Seg a sáb, 9h às 20h\nDomingo fechado" );
	update_post_meta( $parceiro_id, CAV_AreaParceiro::META_INSTAGRAM, 'corteretodemo' );
}

/* ---- Contas ---------------------------------------------------------- */

$parceiro_user = $criar_usuario( 'parceiro.demo', 'parceiro.demo@example.com', 'Corte Reto', 'cav_parceiro' );
update_user_meta( $parceiro_user, CAV_Parceiro::META_USER_PARCEIRO, $parceiro_id );

$aluno = $criar_usuario( 'aluno.demo', 'aluno.demo@example.com', 'Rafael Moreira', 'cav_membro' );
CAV_Membro::set_cpf( $aluno, '111.444.777-35' ); // CPF de teste válido
CAV_Membro::set_validade( $aluno, wp_date( 'Y-m-d', strtotime( '+40 days' ) ) );
update_user_meta( $aluno, 'cav_status', 'ativo' );

$vencido = $criar_usuario( 'aluno.vencido', 'aluno.vencido@example.com', 'Bruno Alves', 'cav_membro' );
CAV_Membro::set_cpf( $vencido, '529.982.247-25' ); // CPF de teste válido
CAV_Membro::set_validade( $vencido, wp_date( 'Y-m-d', strtotime( '-10 days' ) ) );
update_user_meta( $vencido, 'cav_status', 'ativo' );

$criar_usuario( 'recepcao.demo', 'recepcao.demo@example.com', 'Recepção Alliance', 'cav_recepcao' );

/* ---- Área do aluno: ofertas, agenda e sorteio ------------------------------ */

if ( class_exists( 'CAV_Ofertas' ) && class_exists( 'CAV_Agenda' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	/** Foto de mentirinha (fundo escuro, faixa amarela e o nome) para o produto não ficar sem imagem. */
	$foto_demo = static function ( $post_id, $texto, $cor ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) || has_post_thumbnail( $post_id ) ) {
			return;
		}

		$w   = 800;
		$h   = 600;
		$img = imagecreatetruecolor( $w, $h );
		imagefilledrectangle( $img, 0, 0, $w, $h, imagecolorallocate( $img, 26, 26, 26 ) );
		imagefilledrectangle( $img, 0, $h - 90, $w, $h, imagecolorallocate( $img, $cor[0], $cor[1], $cor[2] ) );
		imagefilledellipse( $img, (int) ( $w / 2 ), 250, 280, 280, imagecolorallocate( $img, $cor[0], $cor[1], $cor[2] ) );
		imagefilledellipse( $img, (int) ( $w / 2 ), 250, 220, 220, imagecolorallocate( $img, 26, 26, 26 ) );
		imagestring( $img, 5, 40, $h - 55, strtoupper( remove_accents( $texto ) ), imagecolorallocate( $img, 17, 17, 17 ) );

		$arquivo = wp_tempnam( 'cav-demo.png' );
		imagepng( $img, $arquivo );
		imagedestroy( $img );

		$anexo = media_handle_sideload( [ 'name' => sanitize_title( $texto ) . '.png', 'tmp_name' => $arquivo ], $post_id );

		if ( is_wp_error( $anexo ) ) {
			@unlink( $arquivo );
			return;
		}

		set_post_thumbnail( $post_id, $anexo );
	};

	$oferta = static function ( $titulo, $dados ) use ( $foto_demo ) {
		$ja = get_posts( [ 'post_type' => CAV_Ofertas::CPT, 'title' => $titulo, 'post_status' => 'any', 'numberposts' => 1 ] );

		if ( $ja ) {
			return;
		}

		$id = wp_insert_post( [
			'post_type'    => CAV_Ofertas::CPT,
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_content' => $dados['texto'],
		] );

		update_post_meta( $id, CAV_Ofertas::META_TIPO, $dados['tipo'] );
		update_post_meta( $id, CAV_Ofertas::META_DE, $dados['de'] ?? '' );
		update_post_meta( $id, CAV_Ofertas::META_POR, $dados['por'] ?? '' );
		update_post_meta( $id, CAV_Ofertas::META_SELO, $dados['selo'] ?? '' );
		update_post_meta( $id, CAV_Ofertas::META_COMO, $dados['como'] ?? '' );
		update_post_meta( $id, CAV_Ofertas::META_FIM, $dados['fim'] ?? '' );
		update_post_meta( $id, CAV_Ofertas::META_ATIVO, '1' );

		if ( 'produto' === $dados['tipo'] ) {
			$foto_demo( $id, $titulo, $dados['cor'] ?? [ 255, 198, 41 ] );
		}
	};

	$oferta( 'Kimono Alliance Pro', [
		'tipo' => 'produto', 'de' => '389.90', 'por' => '329.90', 'cor' => [ 255, 198, 41 ],
		'texto' => 'Kimono trançado, preto, com o bordado oficial da Alliance. Tamanhos A1 a A4.',
		'como' => 'Retire na recepção',
	] );
	$oferta( 'Faixa Alliance', [
		'tipo' => 'produto', 'de' => '79.90', 'por' => '59.90', 'cor' => [ 230, 230, 230 ],
		'texto' => 'Faixa oficial para todas as graduações.',
		'como' => 'Retire na recepção',
	] );
	$oferta( 'Rashguard Alliance', [
		'tipo' => 'produto', 'de' => '149.90', 'por' => '119.90', 'cor' => [ 255, 198, 41 ],
		'texto' => 'Rashguard de compressão, manga longa, secagem rápida.',
		'como' => 'Retire na recepção',
		'fim' => wp_date( 'Y-m-d', strtotime( '+20 days' ) ),
	] );
	$oferta( 'Protetor bucal', [
		'tipo' => 'produto', 'de' => '49.90', 'por' => '39.90', 'cor' => [ 90, 170, 255 ],
		'texto' => 'Protetor bucal moldável, com estojo.',
		'como' => 'Retire na recepção',
	] );
	$oferta( '20% off em aulas particulares', [
		'tipo' => 'desconto', 'selo' => '20% OFF',
		'texto' => 'Aulas particulares com os professores da casa, em qualquer horário livre.',
		'como' => 'Agende na recepção',
	] );
	$oferta( 'Segunda modalidade com desconto', [
		'tipo' => 'desconto', 'selo' => '30% OFF',
		'texto' => 'Aluno do clube que faz uma segunda modalidade (Muay Thai ou Boxe) paga menos na segunda mensalidade.',
		'como' => 'Fale com a recepção',
	] );
	$oferta( 'Matrícula grátis para quem você indicar', [
		'tipo' => 'desconto', 'selo' => 'GRÁTIS',
		'texto' => 'Indique um amigo: ele não paga matrícula.',
		'como' => 'Passe o nome dele na recepção',
	] );

	$evento = static function ( $titulo, $dados ) {
		$ja = get_posts( [ 'post_type' => CAV_Agenda::CPT, 'title' => $titulo, 'post_status' => 'any', 'numberposts' => 1 ] );

		if ( $ja ) {
			return;
		}

		$id = wp_insert_post( [
			'post_type'    => CAV_Agenda::CPT,
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_content' => $dados['texto'],
		] );

		$data = wp_date( 'Y-m-d', strtotime( $dados['dias'] . ' days' ) );
		$ate  = isset( $dados['ate'] ) ? wp_date( 'Y-m-d', strtotime( $dados['ate'] . ' days' ) ) : $data;

		update_post_meta( $id, CAV_Agenda::META_TIPO, $dados['tipo'] );
		update_post_meta( $id, CAV_Agenda::META_DATA, $data );
		update_post_meta( $id, CAV_Agenda::META_ATE, $ate );
		update_post_meta( $id, CAV_Agenda::META_HORA, $dados['hora'] ?? '' );
		update_post_meta( $id, CAV_Agenda::META_LOCAL, $dados['local'] ?? 'Alliance Mogi das Cruzes' );
		update_post_meta( $id, CAV_Agenda::META_LINK, $dados['link'] ?? '' );
		update_post_meta( $id, CAV_Agenda::META_LINK_TEXTO, $dados['link_texto'] ?? '' );
	};

	$evento( 'Seminário de passagem de guarda', [
		'tipo' => 'seminario', 'dias' => 6, 'hora' => '10:00',
		'texto' => 'Seminário aberto a todas as faixas, com professor convidado. Vagas limitadas.',
	] );
	$evento( 'Exame de graduação', [
		'tipo' => 'graduacao', 'dias' => 13, 'hora' => '09:00',
		'texto' => 'Troca de faixas e graus da turma adulta e infantil.',
	] );
	$evento( 'Treino aberto de sábado', [
		'tipo' => 'treino', 'dias' => 2, 'hora' => '11:00',
		'texto' => 'Traga um amigo para treinar com a gente.',
	] );
	$evento( 'Campeonato Paulista de Jiu-Jitsu', [
		'tipo' => 'campeonato', 'dias' => 24, 'ate' => 25, 'local' => 'Ginásio Municipal de Mogi das Cruzes',
		'texto' => 'Inscrições abertas com a recepção. A academia ajuda com a inscrição da equipe.',
		'link' => 'https://alliancemogi.com.br', 'link_texto' => 'Saiba mais',
	] );
	$evento( 'Churrasco de confraternização', [
		'tipo' => 'evento', 'dias' => 38, 'hora' => '13:00',
		'texto' => 'Alunos, familiares e amigos. Cada um leva uma bebida.',
	] );

	$sorteio = get_posts( [ 'post_type' => 'cav_sorteio', 'title' => 'Kimono Alliance Pro', 'post_status' => 'any', 'numberposts' => 1 ] );

	if ( ! $sorteio ) {
		$sorteio_id = wp_insert_post( [
			'post_type'    => 'cav_sorteio',
			'post_status'  => 'publish',
			'post_title'   => 'Kimono Alliance Pro',
			'post_content' => 'Um kimono Alliance Pro para um aluno do clube. Participe pela página do sorteio.',
		] );
		update_post_meta( $sorteio_id, CAV_Acesso::META_PUBLICO, 'membros' );
		update_post_meta( $sorteio_id, CAV_Conteudo::META_PREMIO, 'Kimono Alliance Pro' );
		update_post_meta( $sorteio_id, CAV_Conteudo::META_INICIO, wp_date( 'Y-m-d', strtotime( '-3 days' ) ) );
		update_post_meta( $sorteio_id, CAV_Conteudo::META_FIM, wp_date( 'Y-m-d', strtotime( '+14 days' ) ) );
	}

	// Dois sorteios já apurados: um ganho pelo aluno.demo (aparece o aviso "você ganhou"),
	// outro por outro aluno (aparece só o nome dele).
	$apurado = static function ( $titulo, $premio, $ganhador, $dias_atras, $inscritos ) {
		$ja = get_posts( [ 'post_type' => 'cav_sorteio', 'title' => $titulo, 'post_status' => 'any', 'numberposts' => 1 ] );

		if ( $ja ) {
			return;
		}

		global $wpdb;

		$id = wp_insert_post( [
			'post_type'    => 'cav_sorteio',
			'post_status'  => 'publish',
			'post_title'   => $titulo,
			'post_content' => 'Sorteio já realizado.',
		] );

		update_post_meta( $id, CAV_Acesso::META_PUBLICO, 'membros' );
		update_post_meta( $id, CAV_Conteudo::META_PREMIO, $premio );
		update_post_meta( $id, CAV_Conteudo::META_INICIO, wp_date( 'Y-m-d', strtotime( '-' . ( $dias_atras + 15 ) . ' days' ) ) );
		update_post_meta( $id, CAV_Conteudo::META_FIM, wp_date( 'Y-m-d', strtotime( '-' . ( $dias_atras + 1 ) . ' days' ) ) );
		update_post_meta( $id, CAV_Conteudo::META_GANHADOR, $ganhador );
		update_post_meta( $id, CAV_Conteudo::META_APURADO, wp_date( 'Y-m-d H:i:s', strtotime( '-' . $dias_atras . ' days' ) ) );

		foreach ( $inscritos as $membro ) {
			$wpdb->insert( CAV_Sorteio::tabela(), [
				'sorteio_id' => $id,
				'membro_id'  => $membro,
				'criado_em'  => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( $dias_atras + 5 ) . ' days' ) ),
				'ip'         => '',
			] );
		}
	};

	$apurado( 'Rashguard Alliance de brinde', 'Rashguard Alliance', $aluno, 4, [ $aluno, $vencido ] );
	$apurado( 'Kit protetor bucal e faixa', 'Protetor bucal e faixa Alliance', $vencido, 27, [ $vencido ] );
}

WP_CLI::success( 'Dados de demonstração prontos.' );
WP_CLI::log( '  aluno.demo      CPF 111.444.777-35  (ativo)' );
WP_CLI::log( '  aluno.vencido   CPF 529.982.247-25  (matrícula vencida)' );
WP_CLI::log( '  parceiro.demo   opera a Barbearia Corte Reto' );
WP_CLI::log( '  recepcao.demo   vê só a tela de aprovações' );
WP_CLI::log( '  área do aluno   4 produtos, 3 descontos, 5 eventos, 1 sorteio aberto e 2 com resultado (o aluno.demo ganhou um)' );
WP_CLI::log( '  senha das contas de teste: a do .env (DEMO_PASSWORD)' );
