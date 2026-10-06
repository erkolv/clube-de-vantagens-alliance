#!/usr/bin/env bash
# Mostra de onde vem um redirecionamento errado. Só lê, não muda nada.
cd "$(dirname "$0")/.."

wp() { docker compose run --rm -T wpcli wp "$@" 2>/dev/null; }

HOST_CS="${CODESPACE_NAME:-site}-8080.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"

echo "== versão do código"
git --no-pager log -1 --oneline
test -f wp-content/plugins/clube-alliance/includes/entrada.php && echo "entrada.php: presente" || echo "entrada.php: AUSENTE (faça git pull)"

echo; echo "== endereços gravados"
echo "home:    $(wp option get home)"
echo "siteurl: $(wp option get siteurl)"
echo "terminal: $(wp option get cav_url_terminal)"

echo; echo "== resposta do site (status e para onde manda)"
for host in "localhost:8080" "$HOST_CS"; do
  for caminho in / /terminal/ /o-que-e/ /wp-login.php; do
    printf '%-62s %-16s ' "$host" "$caminho"
    curl -sI -H "Host: $host" -H "X-Forwarded-Proto: https" "http://localhost:8080$caminho" \
      | tr -d '\r' | grep -i -E '^(HTTP|location)' | tr '\n' ' '
    echo
  done
done

echo; echo "== onde a palavra localhost aparece no banco"
wp search-replace 'localhost' 'localhost' --dry-run --all-tables --report-changed-only --skip-columns=guid

echo; echo "== páginas"
wp post list --post_type=page --fields=ID,post_name,post_status
