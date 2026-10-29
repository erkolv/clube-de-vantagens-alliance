#!/usr/bin/env bash
# Gera os zips para instalar na hospedagem pelo painel do WordPress (só SFTP e wp-admin bastam).
# Saem em dist/: clube-alliance.zip (plugin) e clube-alliance-child.zip (tema filho).
# O zip do plugin leva junto, na pasta instalar/, os scripts que montam o site (Clube → Instalação).

set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p dist
rm -f dist/clube-alliance.zip dist/clube-alliance-child.zip

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

cp -R wp-content/plugins/clube-alliance "$tmp/clube-alliance"
mkdir -p "$tmp/clube-alliance/instalar/lib"
cp scripts/area-membro.php scripts/area-parceiro.php scripts/paginas-elementor.php \
   scripts/sorteios-calendario.php scripts/elementor-kit.php "$tmp/clube-alliance/instalar/"
cp scripts/lib/paginas.php "$tmp/clube-alliance/instalar/lib/"

(cd "$tmp" && zip -rq "$OLDPWD/dist/clube-alliance.zip" clube-alliance -x "*.DS_Store")
(cd wp-content/themes && zip -rq ../../dist/clube-alliance-child.zip clube-alliance-child -x "*.DS_Store")

ls -la dist/*.zip
