# Clube de Vantagens — Alliance Mogi

Site do clube de vantagens da Alliance Mogi das Cruzes: diretório de parceiros, área do aluno, terminal de consulta por CPF para os estabelecimentos e fila de aprovação da recepção. WordPress + Elementor.

## O que tem aqui

| Caminho | O que é |
|---|---|
| `index.html` | Protótipo navegável (só visual, nada é gravado). Serve de referência para montar as páginas no Elementor. |
| `wp-content/plugins/clube-alliance/` | O plugin: elegibilidade por CPF, terminal, benefícios, aprovações, conteúdos exclusivos, sorteios, e-mails. Tem LEIA-ME próprio. |
| `wp-content/themes/clube-alliance-child/` | Tema filho do Hello Elementor com preto e amarelo da Alliance, Poppins, e a versão escura dos componentes do plugin. |
| `wp-content/mu-plugins/` | Manda os e-mails do WordPress para o Mailpit, só no ambiente local. |
| `docker-compose.yml` | WordPress, banco e caixa de e-mail de teste. |
| `scripts/` | Instalação, paleta do Elementor, dados de teste e empacotamento. |

O WordPress em si e o Elementor não ficam no repositório. São baixados quando você roda o setup.

## Rodar no GitHub (Codespaces)

Dá para editar e ver o site funcionando sem instalar nada no computador. O Codespaces é um computador na nuvem do GitHub, aberto direto no navegador, já com este repositório dentro.

1. Na página do repositório: **Code → Codespaces → Create codespace on main**.
2. Espere. Na primeira vez ele instala o WordPress sozinho (`scripts/setup.sh --demo`), o que leva alguns minutos. O terminal mostra o andamento.
3. Quando terminar, abra a aba **Portas** (embaixo no editor):
   - porta **8080**: o site (clique no ícone de globo). O painel é `/wp-admin`, com `admin` / `admin123`.
   - porta **8025**: a caixa de e-mails de teste.
4. Edite os arquivos de `wp-content/` no próprio editor e recarregue a página.

Detalhes que valem saber:

- As portas são privadas: só você, logado no GitHub, abre os links. Para mostrar a alguém, clique com o botão direito na porta e mude a visibilidade, sabendo que o site fica aberto a quem tiver o link.
- O Codespaces desliga sozinho depois de um tempo parado. O site e o banco continuam lá quando você voltar; para religar, abra o codespace de novo.
- O uso é limitado por uma cota mensal gratuita da conta. Confira o valor atual em github.com/settings/billing e apague os codespaces que não usa mais.
- Isso é ambiente de trabalho, não o site no ar. Para o público, o site precisa de uma hospedagem (veja "Levar para a hospedagem"). O GitHub Pages só serve o `index.html`, o protótipo.

## Rodar no seu computador

Precisa do Docker (Docker Desktop no Mac ou Windows).

```bash
git clone https://github.com/erkolv/clube-de-vantagens-alliance.git
cd clube-de-vantagens-alliance
./scripts/setup.sh --demo
```

Sem o `--demo`, o site sobe vazio. Com ele, entram um parceiro com dois benefícios e quatro contas de teste.

Quando terminar:

- Site: http://localhost:8080
- Painel: http://localhost:8080/wp-admin (`admin` / `admin123`)
- E-mails que o site enviar: http://localhost:8025

Contas de teste (senha `demo123`):

| Usuário | O que é |
|---|---|
| `aluno.demo` | Aluno ativo, CPF 111.444.777-35 |
| `aluno.vencido` | Matrícula vencida, CPF 529.982.247-25 |
| `parceiro.demo` | Opera a Barbearia Corte Reto |
| `recepcao.demo` | Cai direto na tela de aprovações |

Para testar o terminal: entre como `parceiro.demo`, abra `/terminal/` e digite um dos dois CPFs.

Para derrubar tudo: `docker compose down`. Para apagar também o banco: `docker compose down -v`.

## Onde editar

**Páginas institucionais** (início, o que é o clube, seja parceiro): no Elementor, usando `index.html` como guia. O setup cria as páginas com um texto de apoio no lugar.

**Páginas com shortcode.** O setup já cria cada uma com o shortcode certo:

| Página | Shortcode |
|---|---|
| Quero fazer parte | `[cav_solicitar]` |
| Seja parceiro | `[cav_candidatura]` |
| Minha carteirinha | `[cav_carteirinha]` |
| Meus benefícios | `[cav_meus_usos]` |
| Conteúdos | `[cav_conteudos]` |
| Sorteios do clube | `[cav_sorteios]` |
| Terminal | `[cav_terminal]` |
| Painel do parceiro | `[cav_painel_parceiro]` |
| Conteúdos do parceiro | `[cav_conteudos publico="parceiros"]` |

Para encaixar um desses numa página montada no Elementor, use o widget Shortcode.

**Cores e fontes.** O setup grava a paleta (amarelo `#FFC629`, cinza, branco) e a Poppins no Kit do Elementor. Ajustes finos dos componentes do plugin ficam em `wp-content/themes/clube-alliance-child/assets/cav-escuro.css`.

Se o setup avisar que não conseguiu aplicar a paleta, abra qualquer página no editor do Elementor uma vez (isso cria o Kit) e rode `./scripts/setup.sh` de novo.

## Levar para a hospedagem

```bash
./scripts/empacotar.sh
```

Gera dois zips em `dist/`: `clube-alliance.zip` e `clube-alliance-child.zip`. No WordPress do subdomínio (por exemplo `clube.alliancemogi.com.br`):

1. Instale e ative o Elementor e o tema Hello Elementor.
2. Plugins → Adicionar → Enviar plugin → `clube-alliance.zip` → ativar.
3. Aparência → Temas → Adicionar → Enviar tema → `clube-alliance-child.zip` → ativar.
4. Configurações → Geral: fuso `America/Sao_Paulo`. A validade do acesso do aluno vence à meia-noite desse fuso.
5. Crie as páginas da tabela acima e salve a URL do terminal: `update_option( 'cav_url_terminal', 'https://clube.alliancemogi.com.br/terminal/' );`

## O que ainda não está resolvido

- **E-mail em produção.** Localmente o Mailpit recebe tudo. No site de verdade o `wp_mail` sozinho costuma cair em spam ou nem sair, e os e-mails de aprovação dependem disso. Configure um SMTP (Brevo, Amazon SES ou Postmark) antes de abrir os formulários.
- **Elementor Pro.** É pago e não dá para instalar pelo script. Instale com a sua licença se for usar o Theme Builder para o cabeçalho, o rodapé e a página individual do parceiro.
- **Diretório com busca e filtro.** O plugin ainda não tem. Hoje existe a listagem simples em `/parceiros/`. Busca por categoria e distância pede o Voxel ou o GeoDirectory, ou um shortcode próprio.
- **Benefícios dentro da página do parceiro.** Ainda não há shortcode que os puxe para a página do estabelecimento.
- **Parceiro editar o próprio cadastro pelo site.** Hoje só pelo painel do WordPress.
- **Login do aluno e do parceiro.** Usa a tela padrão do WordPress. A escolha de acesso do protótipo ainda não existe no plugin.
- **Sorteios.** Com o clube pago, a participação passa a ter contrapartida financeira. Vale a Alliance confirmar com o contador se precisa de autorização da SPA/Ministério da Fazenda antes do primeiro.

## Estado dos testes

Os arquivos PHP do plugin, do tema e dos scripts passam na checagem de sintaxe (`php -l`). O `docker-compose.yml`, o `setup.sh`, a configuração do Codespaces e a aplicação da paleta no Elementor foram escritos sem poder rodar (quem escreveu não tinha Docker nem acesso ao wordpress.org). Na primeira execução pode aparecer um ajuste. Se aparecer, o erro do passo que falhou diz onde olhar.
