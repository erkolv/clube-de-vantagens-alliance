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
if ! wp menu list --fields=name --format=csv | grep -qx "Principal"; then
	wp menu create "Principal" >/dev/null
	for id in "$ID_HOME" "$ID_SOBRE" "$ID_FAZER" "$ID_SEJA"; do
		wp menu item add-post Principal "$id" >/dev/null
	done
fi
wp menu location assign Principal menu-1 >/dev/null 2>&1 || true

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
  E-mails de teste  http://localhost:${MAILPIT_PORT:-8025}

FIM
