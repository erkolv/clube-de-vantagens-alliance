<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clube → Importar.
 *
 * Três planilhas (CSV) e o envio dos acessos:
 *
 *  - alunos:     cadastra os alunos que já toparam entrar no clube;
 *  - parceiros:  cadastra os estabelecimentos, o responsável e o benefício;
 *  - em dia:     todo mês, estende a validade de quem pagou a mensalidade.
 *
 * Toda planilha passa primeiro por uma prévia, que mostra as linhas com erro. Só depois
 * de confirmar é que o sistema grava. Nada sai por e-mail na importação: os acessos
 * ficam numa fila e são enviados em lotes, quando o envio de e-mails estiver pronto.
 */
class CAV_Importar {

	const META_PENDENTE  = 'cav_acesso_pendente';
	const META_ENVIADO   = 'cav_acesso_enviado';
	const META_IMPORTADO = 'cav_importado_em';
	const META_COMBINADO = '_cav_combinado';   // no parceiro: como o desconto foi combinado

	const OPT_LOTE = 'cav_envio_lote';
	const OPT_AUTO = 'cav_envio_auto';
	const CRON     = 'cav_envio_lote_cron';

	const MAX_BYTES = 2097152; // 2 MB
	const MAX_LINHAS = 2000;

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'menu' ], 30 );
		add_action( 'admin_post_cav_importar_conferir', [ __CLASS__, 'conferir' ] );
		add_action( 'admin_post_cav_importar_confirmar', [ __CLASS__, 'confirmar' ] );
		add_action( 'admin_post_cav_modelo_csv', [ __CLASS__, 'modelo' ] );
		add_action( 'admin_post_cav_enviar_lote', [ __CLASS__, 'acao_enviar_lote' ] );
		add_action( 'admin_post_cav_envio_config', [ __CLASS__, 'acao_envio_config' ] );

		add_filter( 'cron_schedules', static function ( $s ) {
			$s['cav_dez_minutos'] = [ 'interval' => 600, 'display' => 'A cada 10 minutos' ];
			return $s;
		} );
		add_action( self::CRON, [ __CLASS__, 'cron_enviar' ] );
	}

	/* ------------------------------------------------------------------ */
	/* Definição das planilhas                                            */
	/* ------------------------------------------------------------------ */

	/** chave => [ nome na planilha, obrigatória, outros nomes aceitos ] */
	public static function colunas( $tipo ) {
		if ( 'alunos' === $tipo ) {
			return [
				'nome'        => [ 'Nome completo', true, [ 'nome' ] ],
				'cpf'         => [ 'CPF', true, [] ],
				'email'       => [ 'E-mail', true, [ 'email' ] ],
				'whatsapp'    => [ 'WhatsApp', false, [ 'telefone', 'celular' ] ],
				'plano'       => [ 'Plano', true, [] ],
				'vence'       => [ 'Matrícula paga até', true, [ 'proximo vencimento', 'vencimento', 'paga ate' ] ],
				'aceite_em'   => [ 'Data do aceite', true, [ 'aceite em', 'data aceite' ] ],
				'aceite_como' => [ 'Como aceitou', true, [ 'como ele aceitou', 'forma do aceite' ] ],
			];
		}

		if ( 'parceiros' === $tipo ) {
			return [
				'nome'        => [ 'Nome do estabelecimento', true, [ 'estabelecimento', 'nome' ] ],
				'categoria'   => [ 'Categoria', true, [] ],
				'responsavel' => [ 'Nome do responsável', true, [ 'responsavel' ] ],
				'email'       => [ 'E-mail do responsável', true, [ 'email do responsavel', 'email', 'e-mail' ] ],
				'whatsapp'    => [ 'WhatsApp', true, [ 'telefone', 'celular' ] ],
				'instagram'   => [ 'Instagram', true, [] ],
				'site'        => [ 'Site', true, [] ],
				'endereco'    => [ 'Endereço', true, [] ],
				'horario'     => [ 'Horário de funcionamento', true, [ 'horario' ] ],
				'beneficio'   => [ 'Benefício', true, [] ],
				'regra'       => [ 'Regra para o caixa', true, [ 'regra' ] ],
				'limite'      => [ 'Limite por aluno', false, [ 'limite' ] ],
				'inicio'      => [ 'Início da promoção', false, [ 'inicio' ] ],
				'fim'         => [ 'Fim da promoção', false, [ 'fim' ] ],
				'combinado'   => [ 'Como foi combinado', true, [ 'combinado' ] ],
			];
		}

		return [
			'cpf'    => [ 'CPF', false, [] ],
			'email'  => [ 'E-mail', false, [ 'email' ] ],
			'vence'  => [ 'Próximo vencimento', true, [ 'vencimento', 'matricula paga ate', 'paga ate' ] ],
		];
	}

	public static function titulo_tipo( $tipo ) {
		return [ 'alunos' => 'Alunos', 'parceiros' => 'Parceiros', 'em_dia' => 'Mensalidade em dia' ][ $tipo ] ?? '';
	}

	private static function tipo_valido( $tipo ) {
		return in_array( $tipo, [ 'alunos', 'parceiros', 'em_dia' ], true );
	}

	/* ------------------------------------------------------------------ */
	/* Leitura do CSV                                                     */
	/* ------------------------------------------------------------------ */

	/** Minúsculas, sem acento, só letras e números separados por espaço. */
	public static function chave( $texto ) {
		$t = strtolower( remove_accents( (string) $texto ) );
		$t = preg_replace( '/[^a-z0-9]+/', ' ', $t );
		return trim( $t );
	}

	/**
	 * Lê o CSV (vírgula, ponto e vírgula ou tab; UTF-8 ou Windows-1252) e devolve as linhas
	 * já com as chaves da planilha. WP_Error quando faltam colunas.
	 *
	 * @return array|WP_Error  [ 'linhas' => [ [ '_n' => 2, 'nome' => ..., ... ] ] ]
	 */
	public static function ler_csv( $conteudo, $tipo ) {
		$conteudo = (string) $conteudo;

		if ( 0 === strpos( $conteudo, "\xEF\xBB\xBF" ) ) {
			$conteudo = substr( $conteudo, 3 );
		}
		if ( ! mb_check_encoding( $conteudo, 'UTF-8' ) ) {
			$conteudo = mb_convert_encoding( $conteudo, 'UTF-8', 'Windows-1252' );
		}

		$primeira = strtok( $conteudo, "\r\n" );
		$conta    = [
			';'  => substr_count( (string) $primeira, ';' ),
			','  => substr_count( (string) $primeira, ',' ),
			"\t" => substr_count( (string) $primeira, "\t" ),
		];
		arsort( $conta );
		$sep = (string) key( $conta );

		$fp = fopen( 'php://memory', 'r+' );
		fwrite( $fp, $conteudo );
		rewind( $fp );

		$cabecalho = fgetcsv( $fp, 0, $sep, '"', '\\' );

		if ( ! is_array( $cabecalho ) || count( $cabecalho ) < 2 ) {
			fclose( $fp );
			return new WP_Error( 'cav_csv', 'Não consegui ler a planilha. Salve como CSV e envie de novo.' );
		}

		$colunas = self::colunas( $tipo );
		$indice  = [];

		foreach ( $cabecalho as $i => $nome ) {
			$k = self::chave( $nome );
			foreach ( $colunas as $chave => $def ) {
				$aceitos = array_map( [ __CLASS__, 'chave' ], array_merge( [ $def[0] ], $def[2] ) );
				if ( in_array( $k, $aceitos, true ) && ! isset( $indice[ $chave ] ) ) {
					$indice[ $chave ] = $i;
					break;
				}
			}
		}

		$faltam = [];
		foreach ( $colunas as $chave => $def ) {
			if ( $def[1] && ! isset( $indice[ $chave ] ) ) {
				$faltam[] = $def[0];
			}
		}
		if ( $faltam ) {
			fclose( $fp );
			return new WP_Error( 'cav_colunas', 'Faltam estas colunas na planilha: ' . implode( ', ', $faltam ) . '. Baixe a planilha modelo para ver os nomes certos.' );
		}

		$linhas = [];
		$n      = 1;

		while ( ( $campos = fgetcsv( $fp, 0, $sep, '"', '\\' ) ) !== false ) {
			$n++;

			if ( 1 === count( $campos ) && null === $campos[0] ) {
				continue;
			}

			$linha = [ '_n' => $n ];
			$vazia = true;

			foreach ( $colunas as $chave => $def ) {
				$v = isset( $indice[ $chave ], $campos[ $indice[ $chave ] ] ) ? trim( (string) $campos[ $indice[ $chave ] ] ) : '';
				if ( '' !== $v ) {
					$vazia = false;
				}
				$linha[ $chave ] = $v;
			}

			if ( $vazia ) {
				continue;
			}

			$linhas[] = $linha;

			if ( count( $linhas ) > self::MAX_LINHAS ) {
				fclose( $fp );
				return new WP_Error( 'cav_grande', 'A planilha tem mais de ' . self::MAX_LINHAS . ' linhas. Divida em duas e envie uma de cada vez.' );
			}
		}

		fclose( $fp );

		if ( ! $linhas ) {
			return new WP_Error( 'cav_vazia', 'A planilha não tem nenhuma linha preenchida.' );
		}

		return [ 'linhas' => $linhas ];
	}

	/* ------------------------------------------------------------------ */
	/* Validação                                                          */
	/* ------------------------------------------------------------------ */

	/** Aceita 2026-11-05 e 05/11/2026 (e 05/11/26). Devolve Y-m-d ou null. */
	public static function data( $texto ) {
		$t = trim( (string) $texto );

		if ( preg_match( '#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $t, $m ) ) {
			list( , $a, $mes, $d ) = $m;
		} elseif ( preg_match( '#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$#', $t, $m ) ) {
			$d   = $m[1];
			$mes = $m[2];
			$a   = 2 === strlen( $m[3] ) ? '20' . $m[3] : $m[3];
		} else {
			return null;
		}

		return checkdate( (int) $mes, (int) $d, (int) $a ) ? sprintf( '%04d-%02d-%02d', $a, $mes, $d ) : null;
	}

	/** CPF vindo de planilha: o Excel come os zeros da frente, então completa até 11 dígitos. */
	private static function cpf_da_planilha( $texto ) {
		$d = CAV_CPF::normalizar( $texto );

		if ( strlen( $d ) >= 9 && strlen( $d ) < 11 ) {
			$d = str_pad( $d, 11, '0', STR_PAD_LEFT );
		}

		return $d;
	}

	private static function plano_da_planilha( $texto ) {
		$k = self::chave( $texto );

		foreach ( CAV_Solicitacoes::PLANOS as $chave => $rotulo ) {
			if ( $k === self::chave( $rotulo ) || $k === self::chave( $chave ) ) {
				return $chave;
			}
		}

		return '';
	}

	/** Dia de vencimento + dias de tolerância, em Y-m-d. */
	public static function validade_do_vencimento( $vence ) {
		try {
			return ( new DateTimeImmutable( $vence ) )->modify( '+' . CAV_Config::tolerancia() . ' days' )->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			return $vence;
		}
	}

	/**
	 * Confere todas as linhas. Cada resultado: n, dados, erros[], ok.
	 *
	 * @param string $tipo   alunos | parceiros | em_dia
	 * @param array  $linhas Linhas lidas por ler_csv()
	 * @return array[]
	 */
	public static function validar( $tipo, array $linhas ) {
		$vistos = [ 'cpf' => [], 'email' => [], 'nome' => [] ];
		$saida  = [];

		foreach ( $linhas as $l ) {
			$erros = [];
			$d     = $l;

			if ( 'alunos' === $tipo ) {
				self::validar_aluno( $d, $erros, $vistos );
			} elseif ( 'parceiros' === $tipo ) {
				self::validar_parceiro( $d, $erros, $vistos );
			} else {
				self::validar_em_dia( $d, $erros, $vistos );
			}

			$saida[] = [ 'n' => $l['_n'], 'dados' => $d, 'erros' => $erros, 'ok' => ! $erros ];
		}

		return $saida;
	}

	private static function validar_aluno( array &$d, array &$erros, array &$vistos ) {
		if ( '' === $d['nome'] ) {
			$erros[] = 'Falta o nome.';
		}

		$cpf      = self::cpf_da_planilha( $d['cpf'] );
		$d['cpf'] = $cpf;

		if ( ! CAV_CPF::valido( $cpf ) ) {
			$erros[] = 'CPF inválido.';
		} elseif ( isset( $vistos['cpf'][ $cpf ] ) ) {
			$erros[] = 'CPF repetido na planilha (linha ' . $vistos['cpf'][ $cpf ] . ').';
		} elseif ( CAV_CPF::buscar_usuario( $cpf ) ) {
			$erros[] = 'Este CPF já está cadastrado no clube.';
		}
		if ( CAV_CPF::valido( $cpf ) ) {
			$vistos['cpf'][ $cpf ] = $vistos['cpf'][ $cpf ] ?? $d['_n'];
		}

		$email      = sanitize_email( $d['email'] );
		$d['email'] = strtolower( $email );

		if ( ! is_email( $email ) ) {
			$erros[] = 'E-mail inválido.';
		} elseif ( isset( $vistos['email'][ $d['email'] ] ) ) {
			$erros[] = 'E-mail repetido na planilha (linha ' . $vistos['email'][ $d['email'] ] . ').';
		} elseif ( email_exists( $email ) ) {
			$erros[] = 'Já existe uma conta com este e-mail.';
		}
		if ( is_email( $email ) ) {
			$vistos['email'][ $d['email'] ] = $vistos['email'][ $d['email'] ] ?? $d['_n'];
		}

		$plano = self::plano_da_planilha( $d['plano'] );
		if ( '' === $plano ) {
			$erros[] = 'Plano "' . $d['plano'] . '" não entra no clube. Use Recorrente, Recorrente + personal ou Mensal.';
		}
		$d['plano_chave'] = $plano;

		$vence = self::data( $d['vence'] );
		if ( null === $vence ) {
			$erros[] = 'Data da matrícula paga até inválida. Use dia/mês/ano, por exemplo 05/11/2026.';
		} elseif ( $vence < current_time( 'Y-m-d' ) ) {
			$erros[] = 'A matrícula já venceu em ' . mysql2date( 'd/m/Y', $vence ) . '.';
		}
		$d['vence_data'] = $vence;

		$aceite = self::data( $d['aceite_em'] );
		if ( null === $aceite ) {
			$erros[] = 'Data do aceite inválida.';
		} elseif ( $aceite > current_time( 'Y-m-d' ) ) {
			$erros[] = 'A data do aceite está no futuro.';
		}
		$d['aceite_data'] = $aceite;

		if ( '' === $d['aceite_como'] ) {
			$erros[] = 'Falta dizer como o aluno aceitou.';
		}
	}

	private static function validar_parceiro( array &$d, array &$erros, array &$vistos ) {
		foreach ( [ 'nome' => 'o nome do estabelecimento', 'categoria' => 'a categoria', 'responsavel' => 'o nome do responsável', 'endereco' => 'o endereço', 'horario' => 'o horário', 'beneficio' => 'o benefício', 'regra' => 'a regra para o caixa', 'combinado' => 'como foi combinado' ] as $k => $rotulo ) {
			if ( '' === $d[ $k ] ) {
				$erros[] = 'Falta ' . $rotulo . '.';
			}
		}

		if ( '' !== $d['nome'] ) {
			$k = self::chave( $d['nome'] );
			if ( isset( $vistos['nome'][ $k ] ) ) {
				$erros[] = 'Estabelecimento repetido na planilha (linha ' . $vistos['nome'][ $k ] . ').';
			} elseif ( get_posts( [ 'post_type' => cav_parceiro_post_type(), 'title' => $d['nome'], 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ] ) ) {
				$erros[] = 'Já existe um parceiro com este nome.';
			}
			$vistos['nome'][ $k ] = $vistos['nome'][ $k ] ?? $d['_n'];
		}

		$email      = sanitize_email( $d['email'] );
		$d['email'] = strtolower( $email );

		if ( ! is_email( $email ) ) {
			$erros[] = 'E-mail do responsável inválido.';
		} elseif ( isset( $vistos['email'][ $d['email'] ] ) ) {
			$erros[] = 'E-mail repetido na planilha (linha ' . $vistos['email'][ $d['email'] ] . ').';
		} elseif ( email_exists( $email ) ) {
			$erros[] = 'Já existe uma conta com este e-mail.';
		}
		if ( is_email( $email ) ) {
			$vistos['email'][ $d['email'] ] = $vistos['email'][ $d['email'] ] ?? $d['_n'];
		}

		if ( strlen( preg_replace( '/\D+/', '', $d['whatsapp'] ) ) < 10 ) {
			$erros[] = 'WhatsApp inválido. Coloque com DDD.';
		}

		$d['instagram_limpo'] = CAV_AreaParceiro::limpar_instagram( $d['instagram'] );
		if ( '' === $d['instagram_limpo'] ) {
			$erros[] = 'Instagram inválido. Coloque o @ do perfil.';
		}

		$site = self::chave( $d['site'] );
		if ( in_array( $site, [ 'nao tem', 'sem site', 'nao possui', 'nao' ], true ) ) {
			$d['site_limpo'] = '';
		} else {
			$d['site_limpo'] = CAV_AreaParceiro::limpar_site( $d['site'] );
			$host            = (string) wp_parse_url( $d['site_limpo'], PHP_URL_HOST );
			if ( '' === $d['site'] ) {
				$erros[] = 'Falta o site. Se o parceiro não tem, escreva "não tem".';
			} elseif ( '' === $d['site_limpo'] || false === strpos( $host, '.' ) ) {
				$erros[] = 'Site inválido. Se o parceiro não tem, escreva "não tem".';
			}
		}

		$lim = self::chave( $d['limite'] );
		if ( in_array( $lim, [ '', 'sem limite', 'nenhum', 'sem' ], true ) ) {
			$d['limite_chave'] = 'nenhum';
		} elseif ( false !== strpos( $lim, 'dia' ) ) {
			$d['limite_chave'] = 'dia';
		} elseif ( false !== strpos( $lim, 'semana' ) ) {
			$d['limite_chave'] = 'semana';
		} elseif ( false !== strpos( $lim, 'mes' ) ) {
			$d['limite_chave'] = 'mes';
		} else {
			$d['limite_chave'] = 'nenhum';
			$erros[]           = 'Limite por aluno não entendido. Use: sem limite, 1 por dia, 1 por semana ou 1 por mês.';
		}

		$d['inicio_data'] = '' === $d['inicio'] ? '' : self::data( $d['inicio'] );
		$d['fim_data']    = '' === $d['fim'] ? '' : self::data( $d['fim'] );

		if ( null === $d['inicio_data'] ) {
			$erros[]           = 'Data de início da promoção inválida.';
			$d['inicio_data']  = '';
		}
		if ( null === $d['fim_data'] ) {
			$erros[]        = 'Data de fim da promoção inválida.';
			$d['fim_data']  = '';
		}
		if ( $d['inicio_data'] && $d['fim_data'] && $d['fim_data'] < $d['inicio_data'] ) {
			$erros[] = 'O fim da promoção é antes do início.';
		}
	}

	private static function validar_em_dia( array &$d, array &$erros, array &$vistos ) {
		$d['usuario'] = null;
		$cpf          = self::cpf_da_planilha( $d['cpf'] );
		$email        = sanitize_email( $d['email'] );

		if ( '' === $cpf && '' === $email ) {
			$erros[] = 'Preencha o CPF ou o e-mail do aluno.';
		} else {
			$user = null;

			if ( '' !== $cpf ) {
				if ( ! CAV_CPF::valido( $cpf ) ) {
					$erros[] = 'CPF inválido.';
				} else {
					$user = CAV_CPF::buscar_usuario( $cpf );
				}
			}
			if ( ! $user && '' !== $email && is_email( $email ) ) {
				$user = get_user_by( 'email', $email );
			}

			if ( ! $erros ) {
				if ( ! $user || ! in_array( 'cav_membro', (array) $user->roles, true ) ) {
					$erros[] = 'Não encontrei este aluno no clube.';
				} elseif ( 'ativo' !== get_user_meta( $user->ID, 'cav_status', true ) ) {
					$erros[] = 'O cadastro ainda não foi aprovado pela recepção.';
				} elseif ( isset( $vistos['cpf'][ $user->ID ] ) ) {
					$erros[] = 'Este aluno aparece duas vezes (linha ' . $vistos['cpf'][ $user->ID ] . ').';
				} else {
					$vistos['cpf'][ $user->ID ] = $d['_n'];
					$d['usuario']               = $user->ID;
				}
			}
		}

		$vence = self::data( $d['vence'] );
		if ( null === $vence ) {
			$erros[] = 'Data de vencimento inválida. Use dia/mês/ano.';
		} elseif ( $vence < current_time( 'Y-m-d' ) ) {
			$erros[] = 'O vencimento já passou (' . mysql2date( 'd/m/Y', $vence ) . ').';
		}
		$d['vence_data'] = $vence;
	}

	/* ------------------------------------------------------------------ */
	/* Gravação                                                           */
	/* ------------------------------------------------------------------ */

	private static function gravar_aluno( array $d ) {
		$user_id = wp_insert_user( [
			'user_login'   => $d['email'],
			'user_email'   => $d['email'],
			'user_pass'    => wp_generate_password( 20 ),
			'display_name' => $d['nome'],
			'first_name'   => strtok( $d['nome'], ' ' ),
			'role'         => 'cav_membro',
		] );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$cpf = CAV_Membro::set_cpf( $user_id, $d['cpf'] );
		if ( is_wp_error( $cpf ) ) {
			wp_delete_user( $user_id );
			return $cpf;
		}

		$aceite_em = $d['aceite_data'] . ' 00:00:00';

		update_user_meta( $user_id, 'cav_status', CAV_Solicitacoes::STATUS_ATIVO );
		CAV_Membro::set_validade( $user_id, self::validade_do_vencimento( $d['vence_data'] ) );
		update_user_meta( $user_id, CAV_Membro::META_CONSENTE, $aceite_em );
		update_user_meta( $user_id, CAV_Solicitacoes::META_PLANO, $d['plano_chave'] );
		update_user_meta( $user_id, CAV_Solicitacoes::META_ACEITE, [
			'valor'  => number_format( CAV_Config::valor(), 2, '.', '' ),
			'texto'  => 'Aceite registrado pela academia (' . $d['aceite_como'] . ').',
			'em'     => $aceite_em,
			'ip'     => '',
			'origem' => 'importacao',
		] );
		update_user_meta( $user_id, self::META_IMPORTADO, current_time( 'mysql' ) );
		update_user_meta( $user_id, self::META_PENDENTE, '1' );

		if ( '' !== $d['whatsapp'] ) {
			update_user_meta( $user_id, CAV_Solicitacoes::META_WHATSAPP, sanitize_text_field( $d['whatsapp'] ) );
		}

		return $user_id;
	}

	private static function gravar_parceiro( array $d, $publicar ) {
		$status = $publicar ? 'publish' : 'draft';

		$parceiro_id = wp_insert_post( [
			'post_type'    => cav_parceiro_post_type(),
			'post_status'  => $status,
			'post_title'   => $d['nome'],
			'post_content' => '',
		], true );

		if ( is_wp_error( $parceiro_id ) || ! $parceiro_id ) {
			return new WP_Error( 'cav_parceiro', 'Não foi possível criar o estabelecimento.' );
		}

		$user_id = wp_insert_user( [
			'user_login'   => $d['email'],
			'user_email'   => $d['email'],
			'user_pass'    => wp_generate_password( 24 ),
			'display_name' => $d['responsavel'],
			'role'         => 'cav_parceiro',
		] );

		if ( is_wp_error( $user_id ) ) {
			wp_delete_post( $parceiro_id, true );
			return $user_id;
		}

		if ( taxonomy_exists( 'cav_categoria' ) && 'cav_parceiro' === cav_parceiro_post_type() ) {
			wp_set_object_terms( $parceiro_id, $d['categoria'], 'cav_categoria' );
		}

		update_post_meta( $parceiro_id, CAV_AreaParceiro::META_TELEFONE, sanitize_text_field( $d['whatsapp'] ) );
		update_post_meta( $parceiro_id, CAV_AreaParceiro::META_ENDERECO, sanitize_text_field( $d['endereco'] ) );
		update_post_meta( $parceiro_id, CAV_AreaParceiro::META_HORARIO, sanitize_textarea_field( $d['horario'] ) );
		update_post_meta( $parceiro_id, CAV_AreaParceiro::META_INSTAGRAM, $d['instagram_limpo'] );
		update_post_meta( $parceiro_id, CAV_AreaParceiro::META_SITE, $d['site_limpo'] );
		update_post_meta( $parceiro_id, self::META_COMBINADO, sanitize_text_field( $d['combinado'] ) );

		update_user_meta( $user_id, CAV_Parceiro::META_USER_PARCEIRO, $parceiro_id );
		update_user_meta( $user_id, self::META_IMPORTADO, current_time( 'mysql' ) );
		update_user_meta( $user_id, self::META_PENDENTE, '1' );

		$ben_id = wp_insert_post( [
			'post_type'   => 'cav_beneficio',
			'post_status' => $status,
			'post_title'  => $d['beneficio'],
		] );

		if ( $ben_id && ! is_wp_error( $ben_id ) ) {
			update_post_meta( $ben_id, CAV_Parceiro::META_BEN_PARCEIRO, $parceiro_id );
			update_post_meta( $ben_id, CAV_Parceiro::META_BEN_REGRA, sanitize_text_field( $d['regra'] ) );
			update_post_meta( $ben_id, CAV_Parceiro::META_BEN_LIMITE, $d['limite_chave'] );
			update_post_meta( $ben_id, CAV_Parceiro::META_BEN_ATIVO, '1' );
			update_post_meta( $ben_id, CAV_Parceiro::META_BEN_INICIO, $d['inicio_data'] );
			update_post_meta( $ben_id, CAV_Parceiro::META_BEN_FIM, $d['fim_data'] );
		}

		return $parceiro_id;
	}

	private static function gravar_em_dia( array $d ) {
		return CAV_Membro::set_validade( (int) $d['usuario'], self::validade_do_vencimento( $d['vence_data'] ) )
			? true
			: new WP_Error( 'cav_validade', 'Não foi possível gravar a validade.' );
	}

	/* ------------------------------------------------------------------ */
	/* Passos: conferir (prévia) e confirmar                              */
	/* ------------------------------------------------------------------ */

	private static function volta( array $args = [] ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=cav-importar' ) ) );
		exit;
	}

	public static function conferir() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_importar_conferir' );

		$tipo = isset( $_POST['tipo'] ) ? sanitize_key( wp_unslash( $_POST['tipo'] ) ) : '';
		if ( ! self::tipo_valido( $tipo ) ) {
			self::volta( [ 'aviso' => 'tipo' ] );
		}

		$arq = $_FILES['planilha'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		if ( ! $arq || ! empty( $arq['error'] ) || empty( $arq['tmp_name'] ) || ! is_uploaded_file( $arq['tmp_name'] ) ) {
			self::volta( [ 'aviso' => 'sem_arquivo' ] );
		}
		if ( (int) $arq['size'] > self::MAX_BYTES ) {
			self::volta( [ 'aviso' => 'grande' ] );
		}
		if ( ! preg_match( '/\.(csv|txt)$/i', (string) $arq['name'] ) ) {
			self::volta( [ 'aviso' => 'formato' ] );
		}

		$lido = self::ler_csv( file_get_contents( $arq['tmp_name'] ), $tipo ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( is_wp_error( $lido ) ) {
			set_transient( 'cav_imp_msg_' . get_current_user_id(), $lido->get_error_message(), 10 * MINUTE_IN_SECONDS );
			self::volta( [ 'aviso' => 'leitura' ] );
		}

		$token = strtolower( wp_generate_password( 16, false ) );
		set_transient( 'cav_imp_' . $token, [
			'user'   => get_current_user_id(),
			'tipo'   => $tipo,
			'linhas' => $lido['linhas'],
		], HOUR_IN_SECONDS );

		self::volta( [ 'previa' => $token ] );
	}

	public static function confirmar() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_importar_confirmar' );

		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		$dados = get_transient( 'cav_imp_' . $token );

		if ( ! is_array( $dados ) || (int) $dados['user'] !== get_current_user_id() ) {
			self::volta( [ 'aviso' => 'expirou' ] );
		}

		$tipo = $dados['tipo'];

		if ( 'alunos' === $tipo && ! CAV_Config::valor_definido() ) {
			self::volta( [ 'aviso' => 'sem_valor' ] );
		}

		$publicar = ! empty( $_POST['publicar'] );
		$criados  = 0;
		$pulados  = [];

		// Confere de novo na hora de gravar: o que estava certo na prévia pode ter mudado.
		foreach ( self::validar( $tipo, $dados['linhas'] ) as $r ) {
			if ( ! $r['ok'] ) {
				$pulados[] = [ $r['n'], implode( ' ', $r['erros'] ) ];
				continue;
			}

			if ( 'alunos' === $tipo ) {
				$res = self::gravar_aluno( $r['dados'] );
			} elseif ( 'parceiros' === $tipo ) {
				$res = self::gravar_parceiro( $r['dados'], $publicar );
			} else {
				$res = self::gravar_em_dia( $r['dados'] );
			}

			if ( is_wp_error( $res ) ) {
				$pulados[] = [ $r['n'], $res->get_error_message() ];
			} else {
				$criados++;
			}
		}

		delete_transient( 'cav_imp_' . $token );
		set_transient( 'cav_imp_rel_' . get_current_user_id(), [ 'tipo' => $tipo, 'criados' => $criados, 'pulados' => $pulados ], 30 * MINUTE_IN_SECONDS );

		do_action( 'cav_importacao_concluida', $tipo, $criados, $pulados );
		self::volta( [ 'feito' => $tipo ] );
	}

	/** Baixa a planilha modelo: só o cabeçalho, em CSV com ponto e vírgula (abre certo no Excel brasileiro). */
	public static function modelo() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$tipo = isset( $_GET['tipo'] ) ? sanitize_key( wp_unslash( $_GET['tipo'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! self::tipo_valido( $tipo ) ) {
			wp_die( 'Planilha não encontrada.' );
		}

		$nomes = array_map( static function ( $d ) {
			return $d[0];
		}, array_values( self::colunas( $tipo ) ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="modelo-' . str_replace( '_', '-', $tipo ) . '.csv"' );

		echo "\xEF\xBB\xBF" . implode( ';', $nomes ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Envio dos acessos em lotes                                         */
	/* ------------------------------------------------------------------ */

	public static function lote() {
		$n = (int) get_option( self::OPT_LOTE, 50 );
		return max( 1, min( 200, $n ?: 50 ) );
	}

	/** Contas importadas que ainda não receberam o e-mail de acesso. */
	public static function pendentes( $role = '' ) {
		$args = [
			'meta_key'   => self::META_PENDENTE,
			'meta_value' => '1',
			'orderby'    => 'ID',
			'order'      => 'ASC',
			'fields'     => 'ID',
			'number'     => -1,
		];
		if ( $role ) {
			$args['role'] = $role;
		}
		return array_map( 'intval', get_users( $args ) );
	}

	/**
	 * Manda o e-mail de acesso para as próximas contas da fila.
	 *
	 * @return array{enviados:int, falhas:int, restam:int}
	 */
	public static function enviar_lote( $quantos ) {
		$fila     = array_slice( self::pendentes(), 0, max( 1, (int) $quantos ) );
		$enviados = 0;
		$falhas   = 0;

		foreach ( $fila as $id ) {
			$user = get_userdata( $id );

			if ( ! $user ) {
				continue;
			}

			if ( in_array( 'cav_membro', (array) $user->roles, true ) ) {
				$ok = CAV_Emails::aluno_importado( $user, CAV_Membro::validade( $id ) );
			} elseif ( in_array( 'cav_parceiro', (array) $user->roles, true ) ) {
				$parceiro = (int) get_user_meta( $id, CAV_Parceiro::META_USER_PARCEIRO, true );
				$ok       = CAV_Emails::parceiro_importado( $user, $parceiro ? get_the_title( $parceiro ) : $user->display_name, get_option( 'cav_url_terminal', '' ) );
			} else {
				delete_user_meta( $id, self::META_PENDENTE );
				continue;
			}

			if ( $ok ) {
				delete_user_meta( $id, self::META_PENDENTE );
				update_user_meta( $id, self::META_ENVIADO, current_time( 'mysql' ) );
				$enviados++;
			} else {
				$falhas++;
			}
		}

		return [ 'enviados' => $enviados, 'falhas' => $falhas, 'restam' => count( self::pendentes() ) ];
	}

	public static function cron_enviar() {
		if ( '1' !== (string) get_option( self::OPT_AUTO, '' ) ) {
			return;
		}

		$r = self::enviar_lote( self::lote() );

		if ( 0 === $r['restam'] ) {
			update_option( self::OPT_AUTO, '' );
			wp_clear_scheduled_hook( self::CRON );
		}
	}

	public static function acao_enviar_lote() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_enviar_lote' );

		$n = isset( $_POST['lote'] ) ? (int) $_POST['lote'] : self::lote();
		$n = max( 1, min( 200, $n ) );
		update_option( self::OPT_LOTE, $n );

		$r = self::enviar_lote( $n );
		set_transient( 'cav_imp_envio_' . get_current_user_id(), $r, 10 * MINUTE_IN_SECONDS );

		self::volta( [ 'enviado' => 1 ] );
	}

	public static function acao_envio_config() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'cav_envio_config' );

		$n = isset( $_POST['lote'] ) ? (int) $_POST['lote'] : self::lote();
		update_option( self::OPT_LOTE, max( 1, min( 200, $n ) ) );

		$auto = ! empty( $_POST['auto'] );
		update_option( self::OPT_AUTO, $auto ? '1' : '' );

		if ( $auto && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'cav_dez_minutos', self::CRON );
		}
		if ( ! $auto ) {
			wp_clear_scheduled_hook( self::CRON );
		}

		self::volta( [ 'aviso' => 'envio_salvo' ] );
	}

	/* ------------------------------------------------------------------ */
	/* Tela                                                               */
	/* ------------------------------------------------------------------ */

	public static function menu() {
		add_submenu_page( 'cav-relatorio', 'Importar planilhas', 'Importar', 'cav_gerenciar', 'cav-importar', [ __CLASS__, 'tela' ] );
	}

	private static function avisos() {
		// phpcs:disable WordPress.Security.NonceVerification
		$uid = get_current_user_id();

		if ( ! empty( $_GET['feito'] ) ) {
			$rel = get_transient( 'cav_imp_rel_' . $uid );

			if ( is_array( $rel ) ) {
				$rotulos = [ 'alunos' => 'aluno(s) cadastrado(s)', 'parceiros' => 'parceiro(s) cadastrado(s)', 'em_dia' => 'aluno(s) com a validade atualizada' ];
				echo '<div class="notice notice-success"><p><strong>' . (int) $rel['criados'] . '</strong> ' . esc_html( $rotulos[ $rel['tipo'] ] ?? '' ) . '.';
				if ( in_array( $rel['tipo'], [ 'alunos', 'parceiros' ], true ) && $rel['criados'] ) {
					echo ' Os e-mails de acesso ainda <strong>não</strong> foram enviados: eles ficam na fila da seção "Enviar os acessos", mais abaixo.';
				}
				echo '</p></div>';

				if ( $rel['pulados'] ) {
					echo '<div class="notice notice-warning"><p><strong>' . count( $rel['pulados'] ) . ' linha(s) não entraram:</strong></p><ul style="list-style:disc;margin-left:22px">';
					foreach ( array_slice( $rel['pulados'], 0, 50 ) as $p ) {
						echo '<li>Linha ' . (int) $p[0] . ': ' . esc_html( $p[1] ) . '</li>';
					}
					echo '</ul></div>';
				}
			}
		}

		if ( ! empty( $_GET['enviado'] ) ) {
			$r = get_transient( 'cav_imp_envio_' . $uid );

			if ( is_array( $r ) ) {
				$cls = $r['falhas'] ? 'notice-warning' : 'notice-success';
				echo '<div class="notice ' . esc_attr( $cls ) . '"><p>' . (int) $r['enviados'] . ' e-mail(s) enviado(s)';
				if ( $r['falhas'] ) {
					echo ', <strong>' . (int) $r['falhas'] . ' não saíram</strong> (o envio de e-mails do site provavelmente ainda não está configurado). Elas continuam na fila';
				}
				echo '. Restam ' . (int) $r['restam'] . ' na fila.</p></div>';
			}
		}

		$aviso = isset( $_GET['aviso'] ) ? sanitize_key( wp_unslash( $_GET['aviso'] ) ) : '';
		$msgs  = [
			'tipo'        => 'Escolha qual planilha você está enviando.',
			'sem_arquivo' => 'Escolha o arquivo da planilha antes de conferir.',
			'grande'      => 'O arquivo é grande demais (o limite é 2 MB).',
			'formato'     => 'O arquivo precisa ser CSV. No Excel: Arquivo → Salvar como → CSV UTF-8. No Google Planilhas: Arquivo → Fazer download → CSV.',
			'expirou'     => 'A prévia expirou. Envie a planilha de novo.',
			'sem_valor'   => 'Defina o valor do clube em Clube → Configurações antes de cadastrar alunos. O valor fica registrado no aceite de cada um.',
			'envio_salvo' => 'Configuração do envio salva.',
		];

		if ( 'leitura' === $aviso ) {
			$m = get_transient( 'cav_imp_msg_' . $uid );
			echo '<div class="notice notice-error"><p>' . esc_html( is_string( $m ) ? $m : 'Não consegui ler a planilha.' ) . '</p></div>';
		} elseif ( isset( $msgs[ $aviso ] ) ) {
			$cls = 'envio_salvo' === $aviso ? 'notice-success' : 'notice-error';
			echo '<div class="notice ' . esc_attr( $cls ) . '"><p>' . esc_html( $msgs[ $aviso ] ) . '</p></div>';
		}
		// phpcs:enable
	}

	private static function bloco_envio_planilha( $tipo, $titulo, $explica, $desativado = '' ) {
		?>
		<div class="card" style="max-width:none;margin-top:16px">
			<h2 style="margin-top:0"><?php echo esc_html( $titulo ); ?></h2>
			<p><?php echo wp_kses_post( $explica ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=cav_modelo_csv&tipo=' . $tipo ) ); ?>">Baixar a planilha modelo</a>
			</p>
			<?php if ( $desativado ) : ?>
				<p style="color:#b32d2e"><strong><?php echo esc_html( $desativado ); ?></strong></p>
			<?php else : ?>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="cav_importar_conferir">
					<input type="hidden" name="tipo" value="<?php echo esc_attr( $tipo ); ?>">
					<?php wp_nonce_field( 'cav_importar_conferir' ); ?>
					<input type="file" name="planilha" accept=".csv,.txt" required>
					<button type="submit" class="button button-primary">Conferir a planilha</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function tela() {
		if ( ! current_user_can( 'cav_gerenciar' ) ) {
			wp_die( 'Sem permissão.' );
		}

		$token = isset( $_GET['previa'] ) ? sanitize_key( wp_unslash( $_GET['previa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap">
			<h1>Importar planilhas</h1>
			<?php self::avisos(); ?>

			<?php
			if ( $token ) {
				self::tela_previa( $token );
				echo '</div>';
				return;
			}
			?>

			<p>
				Cada planilha passa por uma conferência antes de entrar: você vê as linhas com erro e só depois confirma.
				Salve a planilha como <strong>CSV</strong> (Excel: Arquivo → Salvar como → CSV UTF-8; Google Planilhas: Arquivo → Fazer download → CSV).
			</p>

			<?php
			self::bloco_envio_planilha(
				'alunos',
				'1. Alunos',
				'Cadastra os alunos que já toparam entrar no clube, com plano, vencimento e aceite. Nenhum e-mail sai agora.',
				CAV_Config::valor_definido() ? '' : 'Defina o valor do clube em Clube → Configurações antes de cadastrar alunos.'
			);
			self::bloco_envio_planilha(
				'parceiros',
				'2. Parceiros',
				'Cadastra o estabelecimento, o responsável (que vira o login) e o benefício. Nenhum e-mail sai agora.'
			);
			self::bloco_envio_planilha(
				'em_dia',
				'3. Mensalidade em dia (todo mês)',
				'Lista de quem pagou, tirada do sistema de cobrança: CPF ou e-mail e o próximo vencimento. Cada aluno da lista fica liberado até o vencimento mais <strong>' . (int) CAV_Config::tolerancia() . ' dias</strong> de tolerância. Quem não estiver na lista perde o acesso sozinho quando a validade acabar.'
			);

			self::tela_envio();
			?>
		</div>
		<?php
	}

	private static function tela_previa( $token ) {
		$dados = get_transient( 'cav_imp_' . $token );

		if ( ! is_array( $dados ) || (int) $dados['user'] !== get_current_user_id() ) {
			echo '<div class="notice notice-error"><p>A prévia expirou. <a href="' . esc_url( admin_url( 'admin.php?page=cav-importar' ) ) . '">Voltar</a> e enviar a planilha de novo.</p></div>';
			return;
		}

		$tipo       = $dados['tipo'];
		$resultados = self::validar( $tipo, $dados['linhas'] );
		$ok         = array_values( array_filter( $resultados, static function ( $r ) {
			return $r['ok'];
		} ) );
		$com_erro   = array_values( array_filter( $resultados, static function ( $r ) {
			return ! $r['ok'];
		} ) );
		$sem_valor  = 'alunos' === $tipo && ! CAV_Config::valor_definido();
		?>
		<h2>Conferência: <?php echo esc_html( self::titulo_tipo( $tipo ) ); ?></h2>
		<p>
			<strong><?php echo count( $resultados ); ?></strong> linhas lidas:
			<strong style="color:#1a7f37"><?php echo count( $ok ); ?> certas</strong>,
			<strong style="color:#b32d2e"><?php echo count( $com_erro ); ?> com erro</strong>.
		</p>

		<?php if ( $sem_valor ) : ?>
			<div class="notice notice-error inline"><p>Defina o valor do clube em Clube → Configurações antes de cadastrar alunos. O valor fica registrado no aceite de cada um.</p></div>
		<?php endif; ?>

		<?php if ( $com_erro ) : ?>
			<h3>Linhas com erro (não entram)</h3>
			<p class="description">A linha é o número dela na planilha, contando o cabeçalho como linha 1. Corrija na planilha e envie de novo, ou importe só as certas e depois cadastre estas à mão.</p>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr><th style="width:60px">Linha</th><th style="width:240px"><?php echo 'em_dia' === $tipo ? 'CPF ou e-mail' : 'Nome'; ?></th><th>O que está errado</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $com_erro, 0, 300 ) as $r ) : ?>
					<tr>
						<td><?php echo (int) $r['n']; ?></td>
						<td><?php echo esc_html( 'em_dia' === $tipo ? ( $r['dados']['cpf'] ?: $r['dados']['email'] ) : $r['dados']['nome'] ); ?></td>
						<td><?php echo esc_html( implode( ' ', $r['erros'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $com_erro ) > 300 ) : ?>
				<p class="description">Mostrando as 300 primeiras.</p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $ok ) : ?>
			<h3>Linhas certas</h3>
			<table class="widefat striped" style="max-width:1000px">
				<thead><tr>
					<?php if ( 'alunos' === $tipo ) : ?>
						<th>Aluno</th><th>CPF</th><th>Plano</th><th>Liberado até</th>
					<?php elseif ( 'parceiros' === $tipo ) : ?>
						<th>Estabelecimento</th><th>Responsável</th><th>Benefício</th>
					<?php else : ?>
						<th>Aluno</th><th>Validade agora</th><th>Passa a ser</th>
					<?php endif; ?>
				</tr></thead>
				<tbody>
				<?php foreach ( array_slice( $ok, 0, 50 ) as $r ) : $d = $r['dados']; ?>
					<tr>
					<?php if ( 'alunos' === $tipo ) : ?>
						<td><?php echo esc_html( $d['nome'] ); ?></td>
						<td><?php echo esc_html( CAV_CPF::mascarar( $d['cpf'] ) ); ?></td>
						<td><?php echo esc_html( CAV_Solicitacoes::rotulo_plano( $d['plano_chave'] ) ); ?></td>
						<td><?php echo esc_html( mysql2date( 'd/m/Y', self::validade_do_vencimento( $d['vence_data'] ) ) ); ?></td>
					<?php elseif ( 'parceiros' === $tipo ) : ?>
						<td><?php echo esc_html( $d['nome'] ); ?></td>
						<td><?php echo esc_html( $d['responsavel'] . ' (' . $d['email'] . ')' ); ?></td>
						<td><?php echo esc_html( $d['beneficio'] ); ?></td>
					<?php else :
						$u     = get_userdata( (int) $d['usuario'] );
						$atual = $u ? CAV_Membro::validade( $u->ID ) : '';
						?>
						<td><?php echo esc_html( $u ? $u->display_name : '' ); ?></td>
						<td><?php echo esc_html( $atual ? mysql2date( 'd/m/Y', $atual ) : 'sem validade' ); ?></td>
						<td><?php echo esc_html( mysql2date( 'd/m/Y', self::validade_do_vencimento( $d['vence_data'] ) ) ); ?></td>
					<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $ok ) > 50 ) : ?>
				<p class="description">Mostrando 50 de <?php echo count( $ok ); ?>.</p>
			<?php endif; ?>

			<?php if ( 'em_dia' === $tipo ) :
				$ativos  = get_users( [ 'role' => 'cav_membro', 'meta_key' => 'cav_status', 'meta_value' => 'ativo', 'fields' => 'ID', 'number' => -1 ] );
				$na_lista = array_filter( array_map( static function ( $r ) {
					return (int) $r['dados']['usuario'];
				}, $ok ) );
				$fora = count( array_diff( array_map( 'intval', $ativos ), $na_lista ) );
				?>
				<p class="description"><?php echo (int) $fora; ?> aluno(s) ativo(s) não estão nesta lista. Eles não são alterados: perdem o acesso quando a validade que já têm acabar.</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
				<input type="hidden" name="action" value="cav_importar_confirmar">
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<?php wp_nonce_field( 'cav_importar_confirmar' ); ?>

				<?php if ( 'parceiros' === $tipo ) : ?>
					<p><label><input type="checkbox" name="publicar" value="1" checked> Publicar os parceiros e os benefícios já (aparecem no site e valem no terminal). Desmarque para entrarem como rascunho.</label></p>
				<?php endif; ?>

				<button type="submit" class="button button-primary" <?php disabled( $sem_valor ); ?>>Importar as <?php echo count( $ok ); ?> linhas certas</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cav-importar' ) ); ?>">Cancelar</a>
			</form>
		<?php else : ?>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cav-importar' ) ); ?>">Voltar</a></p>
		<?php endif; ?>
		<?php
	}

	private static function tela_envio() {
		$alunos    = count( self::pendentes( 'cav_membro' ) );
		$parceiros = count( self::pendentes( 'cav_parceiro' ) );
		$total     = $alunos + $parceiros;
		$enviados  = count( get_users( [ 'meta_key' => self::META_ENVIADO, 'fields' => 'ID', 'number' => -1 ] ) );
		$auto      = '1' === (string) get_option( self::OPT_AUTO, '' );
		?>
		<div class="card" style="max-width:none;margin-top:16px">
			<h2 style="margin-top:0">4. Enviar os acessos</h2>
			<p>
				Cada pessoa cadastrada pela planilha recebe um e-mail com um botão para <strong>criar a própria senha</strong>
				(o link vale 7 dias; depois disso ela usa "Esqueci minha senha"). Os e-mails saem em lotes pequenos para não caírem no spam.
			</p>
			<p>
				Na fila: <strong><?php echo (int) $alunos; ?></strong> aluno(s) e <strong><?php echo (int) $parceiros; ?></strong> parceiro(s).
				Já enviados: <strong><?php echo (int) $enviados; ?></strong>.
			</p>
			<p class="description">Antes de enviar, o site precisa ter um serviço de envio de e-mails configurado (SMTP, como Brevo ou Amazon SES). Sem isso, muitos e-mails não chegam.</p>

			<?php if ( $total ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0">
					<input type="hidden" name="action" value="cav_enviar_lote">
					<?php wp_nonce_field( 'cav_enviar_lote' ); ?>
					Enviar o próximo lote de
					<input type="number" name="lote" value="<?php echo esc_attr( self::lote() ); ?>" min="1" max="200" class="small-text"> e-mails
					<button type="submit" class="button button-primary">Enviar agora</button>
				</form>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:12px 0">
				<input type="hidden" name="action" value="cav_envio_config">
				<?php wp_nonce_field( 'cav_envio_config' ); ?>
				<label>
					<input type="checkbox" name="auto" value="1" <?php checked( $auto ); ?>>
					Enviar sozinho, um lote a cada 10 minutos, até esvaziar a fila
				</label>
				<input type="hidden" name="lote" value="<?php echo esc_attr( self::lote() ); ?>">
				<button type="submit" class="button">Salvar</button>
			</form>
		</div>
		<?php
	}
}
