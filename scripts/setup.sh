#!/usr/bin/env bash
# Sobe o ambiente e deixa o WordPress pronto para editar.
# Pode rodar de novo à vontade: o que já existe é reaproveitado.
#
#   ./scripts/setup.sh           instala tudo
#   ./scripts/setup.sh --demo    instala e cria parceiros, benefícios e contas de teste

set -euo pipefail
cd "$(dirname "$0")/.."

DEMO=0
[ "${1:-}" = "--demo" ] && DEMO=1

if [ ! -f .env ]; then
	cp .env.example .env
	echo "Criei o .env a partir do .env.example."
fi

set -a
# shellcheck disable=SC1091
. ./.env
set +a

# No GitHub Codespaces o site não vive em localhost: a URL é a do porta encaminhada.
MAIL_URL="http://localhost:${MAILPIT_PORT:-8025}"
if [ -n "${CODESPACE_NAME:-}" ]; then
	DOMINIO="${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
	SITE_URL="https://${CODESPACE_NAME}-${WP_PORT:-8080}.${DOMINIO}"
	MAIL_URL="https://${CODESPACE_NAME}-${MAILPIT_PORT:-8025}.${DOMINIO}"
	echo "Codespaces detectado. O site vai usar $SITE_URL"
fi

wp() {
	docker compose run --rm -T wpcli wp "$@"
}

echo "==> Subindo os containers"
docker compose up -d

echo "==> Esperando o WordPress copiar os arquivos"
tentativas=0
until wp config path >/dev/null 2>&1; do
	tentativas=$((tentativas + 1))
	if [ "$tentativas" -gt 40 ]; then
		echo "O WordPress não ficou pronto a tempo. Veja: docker compose logs wordpress" >&2
		exit 1
	fi
	sleep 3
done

if ! wp core is-installed >/dev/null 2>&1; then
	echo "==> Instalando o WordPress"
	wp core install \
		--url="$SITE_URL" \
		--title="$SITE_TITLE" \
		--admin_user="$ADMIN_USER" \
		--admin_password="$ADMIN_PASSWORD" \
		--admin_email="$ADMIN_EMAIL" \
		--skip-email
fi

# Garante o endereço certo mesmo quando o WordPress já estava instalado com outro.
wp option update home "$SITE_URL" >/dev/null
wp option update siteurl "$SITE_URL" >/dev/null

echo "==> Idioma, fuso e formato de data"
# O fuso importa: a validade do acesso do aluno vence à meia-noite do horário do site.
wp language core install pt_BR --activate >/dev/null 2>&1 || echo "   (não consegui baixar o pt_BR, segue em inglês)"
wp option update timezone_string "America/Sao_Paulo" >/dev/null
wp option update date_format "d/m/Y" >/dev/null
wp option update time_format "H:i" >/dev/null
wp option update blogdescription "Descontos exclusivos para alunos da Alliance Mogi das Cruzes" >/dev/null

echo "==> Elementor e tema"
wp plugin install elementor --activate
wp theme install hello-elementor
wp theme activate clube-alliance-child

echo "==> Plugin do clube"
wp plugin activate clube-alliance
wp option update cav_url_terminal "${SITE_URL%/}/terminal/" >/dev/null

echo "==> Links permanentes"
wp rewrite structure '/%postname%/' >/dev/null
wp rewrite flush --hard >/dev/null

echo "==> Páginas"
criar_pagina() {
	# $1 título, $2 slug, $3 conteúdo
	local id
	id=$(wp post list --post_type=page --name="$2" --field=ID --post_status=any | head -n1)
	if [ -z "$id" ]; then
		id=$(wp post create --post_type=page --post_status=publish \
			--post_title="$1" --post_name="$2" --post_content="$3" --porcelain)
		echo "   criada: $1 (/$2/)" >&2
	else
		echo "   já existia: $1 (/$2/)" >&2
	fi
	echo "$id"
}

NOTA='<p>Monte esta página no Elementor. O protótipo de referência está em index.html, na raiz do repositório.</p>'

ID_HOME=$(criar_pagina "Início" "inicio" "$NOTA")
ID_SOBRE=$(criar_pagina "O que é o clube" "o-que-e" "$NOTA")
ID_ENTRAR=$(criar_pagina "Entrar" "entrar" "$NOTA")
ID_FAZER=$(criar_pagina "Quero fazer parte" "quero-fazer-parte" '[cav_solicitar]')
ID_SEJA=$(criar_pagina "Seja parceiro" "seja-parceiro" '[cav_candidatura]')

ID_CART=$(criar_pagina "Minha carteirinha" "minha-carteirinha" '[cav_carteirinha]')
ID_USOS=$(criar_pagina "Meus benefícios" "meus-beneficios" '[cav_meus_usos]')
ID_CONT=$(criar_pagina "Conteúdos" "conteudos" '[cav_conteudos]')
ID_SORT=$(criar_pagina "Sorteios do clube" "sorteios-do-clube" '[cav_sorteios]')

ID_TERM=$(criar_pagina "Terminal" "terminal" '[cav_terminal]')
ID_PAIN=$(criar_pagina "Painel do parceiro" "painel-do-parceiro" '[cav_painel_parceiro]')
ID_CPAR=$(criar_pagina "Conteúdos do parceiro" "conteudos-do-parceiro" '[cav_conteudos publico="parceiros"]')

wp option update show_on_front page >/dev/null
wp option update page_on_front "$ID_HOME" >/dev/null

echo "==> Menu principal"
# Versão 2: Início, O que é, Parceiros, Seja parceiro. "Quero fazer parte" e "Entrar" são botões do cabeçalho.
if [ "$(wp option get cav_menu_versao 2>/dev/null || true)" != "2" ]; then
	wp menu delete Principal >/dev/null 2>&1 || true
	wp menu create "Principal" >/dev/null
	wp menu item add-post Principal "$ID_HOME" >/dev/null
	wp menu item add-post Principal "$ID_SOBRE" >/dev/null
	wp menu item add-custom Principal "Parceiros" "/parceiros/" >/dev/null
	wp menu item add-post Principal "$ID_SEJA" >/dev/null
	wp option update cav_menu_versao 2 >/dev/null
fi
wp menu location assign Principal menu-1 >/dev/null 2>&1 || true

echo "==> Área do aluno"
wp eval-file /scripts/area-membro.php || echo "   (não consegui montar a área do aluno; veja a mensagem acima)"

echo "==> Área do parceiro"
wp eval-file /scripts/area-parceiro.php || echo "   (não consegui montar a área do parceiro; veja a mensagem acima)"

echo "==> Páginas no Elementor"
# Versão do conteúdo das páginas. Quando sobe, só as páginas que mudaram são refeitas, uma vez.
# 2 = home e "O que é o clube" ganharam a seção do que o clube oferece.
# 3 = o valor do clube vira marca {{valor_clube}}, "Entrar" vira a página de login e o pedido ganha o aceite do valor.
PAGINAS_VERSAO=4
PEDIDO_PAGINAS="${REFAZER_PAGINAS:+refazer}"
if [ -z "$PEDIDO_PAGINAS" ] && [ "$(wp option get cav_paginas_versao 2>/dev/null || true)" != "$PAGINAS_VERSAO" ]; then
	PEDIDO_PAGINAS="refazer:inicio,o-que-e,entrar,quero-fazer-parte,seja-parceiro"
fi
if wp eval-file /scripts/paginas-elementor.php $PEDIDO_PAGINAS; then
	wp option update cav_paginas_versao "$PAGINAS_VERSAO" >/dev/null
else
	echo "   (não consegui montar as páginas; veja a mensagem acima)"
fi

echo "==> Paleta e fontes no Elementor"
wp eval-file /scripts/elementor-kit.php || echo "   (não consegui aplicar a paleta; veja o README, seção Paleta)"
wp elementor flush-css >/dev/null 2>&1 || true

if [ "$DEMO" = "1" ]; then
	echo "==> Dados de demonstração"
	DEMO_PASSWORD="$DEMO_PASSWORD" wp eval-file /scripts/demo-data.php
fi

cat <<FIM

Pronto.

  Site ............ $SITE_URL
  Painel .......... ${SITE_URL%/}/wp-admin   ($ADMIN_USER / $ADMIN_PASSWORD)
  E-mails de teste  $MAIL_URL

FIM
