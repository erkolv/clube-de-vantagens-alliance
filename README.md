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

Com o `--demo` entram também 4 produtos com foto e preço, 3 descontos exclusivos, 5 eventos (as datas são sempre a partir do dia em que você rodou) e 3 sorteios de exemplo (1 aberto e 2 com resultado, um deles ganho pelo `aluno.demo`).

Contas de teste (senha `demo123`):

| Usuário | O que é |
|---|---|
| `aluno.demo` | Aluno ativo, CPF 111.444.777-35. Depois de entrar cai em `/area-do-membro/` |
| `aluno.vencido` | Matrícula vencida, CPF 529.982.247-25 |
| `parceiro.demo` | Opera a Barbearia Corte Reto |
| `recepcao.demo` | Cai direto na tela de aprovações |

Para testar o terminal: entre como `parceiro.demo`, abra `/terminal/` e digite um dos dois CPFs.

Para derrubar tudo: `docker compose down`. Para apagar também o banco: `docker compose down -v`.

## Onde editar

**Páginas do site** (Início, O que é o clube, Entrar, Quero fazer parte, Seja parceiro): o setup já grava o conteúdo no Elementor. É só abrir a página e **Editar com Elementor** para mudar texto, botão ou ordem das seções. O setup não mexe numa página que já foi montada, então o que você editar fica.

Para voltar uma página ao modelo original: `REFAZER_PAGINAS=1 ./scripts/setup.sh` (apaga as edições feitas nas cinco páginas acima). O conteúdo de cada uma está em `scripts/lib/paginas.php`.

**Cabeçalho e rodapé** ficam no tema filho (`template-parts/header.php` e `footer.php`), sem precisar do Elementor Pro. O menu é o "Principal" (Aparência → Menus). Os botões Entrar, Quero fazer parte e, depois do login, "Meu terminal" ou "Minha carteirinha" são fixos no cabeçalho.

**Lista e página de parceiros** (`/parceiros/`): busca por nome, filtro por categoria e, em cada parceiro, os benefícios vigentes. Vêm do tema filho (`archive-cav_parceiro.php`, `single-cav_parceiro.php`). O shortcode `[clube_parceiros limite="6"]` mostra os últimos parceiros em qualquer página.

**Área do aluno.** Quem entra como aluno cai em `/area-do-membro/`, com um menu (Painel, Carteirinha, Benefícios, Ofertas, Agenda, Sorteios, Conteúdos) no topo de todas essas páginas. O setup cria as páginas novas e coloca o menu nas que já existiam.

- **Painel**: saudação e validade, números de uso, próximos eventos, ofertas em destaque e sorteios abertos.
- **Ofertas** (`/ofertas/`): *Descontos exclusivos na academia* e *Produtos com desconto*, cada produto com foto, preço cheio riscado e preço do clube. No painel do WordPress: **Ofertas da academia → Adicionar**. A foto é a *Imagem destacada*; o texto vira a descrição; o selo ("-20%") sai do cálculo dos dois preços, ou digite o seu ("GRÁTIS"). Dá para marcar uma data final, e a oferta some sozinha depois dela.
- **Agenda** (`/agenda/`): calendário do mês com setas para os outros meses, mais a lista de eventos do mês. No painel: **Agenda → Adicionar**, com tipo (seminário, campeonato, graduação, treino aberto, evento), data (e data final para eventos de vários dias), horário, local e um link opcional.
- **Sorteios** (`/sorteios-do-clube/`): dois blocos, *Em andamento* e *Resultados*. Depois que a recepção apura (botão no painel do WordPress, na tela do sorteio), o resultado aparece para todos os alunos com o nome e a inicial do sobrenome do ganhador ("Camila L."). Quem ganhou vê um aviso amarelo "Parabéns, você ganhou" no topo do painel e da página de sorteios por 60 dias, e recebe um e-mail para retirar o prêmio na recepção. O painel também mostra os dois últimos resultados.

Hoje só quem é administrador cadastra ofertas e eventos. A recepção ainda não tem essa permissão.

**Home para quem ainda não é do clube.** A página inicial ganhou a seção *Por dentro do clube*: sorteios, entrada VIP nos seminários, descontos exclusivos na academia e produtos com preço de aluno, mais uma faixa ao vivo (`[cav_clube_agora]`) com o sorteio aberto, o próximo seminário e quantos descontos estão no ar. A faixa só mostra título e data, nada da área restrita. Quando o setup roda e a versão das páginas mudou, ele refaz só a Início e a "O que é o clube" uma vez (as outras páginas não são tocadas). O texto da seção está em `scripts/lib/paginas.php`. A frase sobre "condição VIP" é genérica: ajuste para a regra real da academia.

Para pôr um pedaço disso em outra página: `[cav_proximos_eventos qtd="3"]` e `[cav_ofertas tipo="produto" qtd="4"]`, `[cav_clube_agora]` (`tipo` pode ser `produto` ou `desconto`).

**Área do parceiro.** Quem opera um estabelecimento entra e cai no Terminal, com um menu no topo (Terminal, Painel, Promoções, Meu negócio e um atalho para a página pública dele). Tudo pelo site, sem o painel do WordPress.

- **Promoções** (`/minhas-promocoes/`): o parceiro vê a lista com a situação de cada uma (no ar, pausada, agendada, encerrada, em análise), cadastra uma nova, edita, pausa, reativa ou apaga. Cada promoção tem nome, regra para o caixa, limite por aluno (sem limite, dia, semana, mês) e datas de início e fim opcionais. Salvou, vale na hora no terminal e na página pública. Apagar manda para a lixeira, então o histórico de usos continua legível.
- **Meu negócio** (`/meu-negocio/`): foto do negócio, logo, resumo, descrição, WhatsApp, Instagram, endereço, horário e site. Aparecem na página do parceiro, em "Onde encontrar". A foto e o logo aceitam JPG, PNG e WebP até 4 MB. Nome e categoria só a Alliance muda.
- Cada parceiro mexe só no que é dele. A promoção que a Alliance deixa em rascunho na aprovação do cadastro aparece para ele como "Em análise" e vai ao ar quando for publicada no painel.
- Pelo painel do WordPress, o administrador edita os mesmos dados na tela do parceiro (caixa "Dados do negócio").

Hoje o que o parceiro salva vai ao ar sem passar por aprovação. Se a Alliance preferir revisar antes, dá para mudar para "em análise" (é uma alteração pequena no plugin).

**Páginas com shortcode.** O setup já cria cada uma com o shortcode certo:

| Página | Shortcode |
|---|---|
| Quero fazer parte | `[cav_solicitar]` |
| Seja parceiro | `[cav_candidatura]` |
| Minha carteirinha | `[cav_carteirinha]` |
| Meus benefícios | `[cav_meus_usos]` |
| Área do membro (painel) | `[cav_menu_membro]` `[cav_painel_membro]` |
| Ofertas da academia | `[cav_menu_membro]` `[cav_ofertas]` |
| Agenda e eventos | `[cav_menu_membro]` `[cav_agenda]` |
| Conteúdos | `[cav_conteudos]` |
| Sorteios do clube | `[cav_sorteios]` |
| Terminal | `[cav_menu_parceiro]` `[cav_terminal]` |
| Painel do parceiro | `[cav_menu_parceiro]` `[cav_painel_parceiro]` |
| Minhas promoções (parceiro) | `[cav_menu_parceiro]` `[cav_minhas_promocoes]` |
| Meu negócio (parceiro) | `[cav_menu_parceiro]` `[cav_meu_negocio]` |
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
- **Diretório por distância.** A lista de parceiros busca por nome e categoria. Busca por distância ou mapa pede o Voxel ou o GeoDirectory.
- **Telas de login.** Usa a tela padrão do WordPress. Depois de entrar, parceiro vai para `/terminal/`, aluno para `/area-do-membro/` e recepção para as aprovações. A escolha de acesso do protótipo (aluno ou parceiro) ainda não existe no plugin.
- **Sorteios.** Com o clube pago, a participação passa a ter contrapartida financeira. Vale a Alliance confirmar com o contador se precisa de autorização da SPA/Ministério da Fazenda antes do primeiro.

## Estado dos testes

Confirmado no Codespaces: o setup, o WordPress, o plugin, o login por perfil e o terminal de consulta.

Conferido num WordPress de verdade rodando no sandbox (WordPress Playground, sem o Elementor nem o tema): a área do aluno (painel, ofertas, agenda, menu) e a do parceiro (promoções, meu negócio, envio de foto e logo, página pública), no computador e no celular, e os scripts que criam as páginas e os dados de teste, inclusive rodando duas vezes.

Conferido só por simulação: o visual do cabeçalho, do rodapé e das páginas (num navegador, com uma imitação do HTML do Elementor, no computador e no celular), e a sintaxe de todos os arquivos PHP.

Ainda sem confirmação: a gravação das páginas no Elementor de verdade (`scripts/paginas-elementor.php`) e a aplicação da paleta (`scripts/elementor-kit.php`). Se uma página abrir como texto simples em vez do layout, o Elementor recusou a estrutura; o texto está lá como reserva e o erro ajuda a corrigir.
