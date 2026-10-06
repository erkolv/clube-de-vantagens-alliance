#!/usr/bin/env bash
# Gera os zips para instalar na hospedagem (cPanel, Locaweb, Speedinx...).
# Saem em dist/: clube-alliance.zip (plugin) e clube-alliance-child.zip (tema filho).

set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p dist
rm -f dist/clube-alliance.zip dist/clube-alliance-child.zip

(cd wp-content/plugins && zip -rq ../../dist/clube-alliance.zip clube-alliance -x "*.DS_Store")
(cd wp-content/themes && zip -rq ../../dist/clube-alliance-child.zip clube-alliance-child -x "*.DS_Store")

ls -la dist/*.zip
