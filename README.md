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

Com o `--demo` entram também 5 eventos (as datas são sempre a partir do dia em que você rodou) e 3 sorteios de exemplo (1 aberto e 2 com resultado, um deles ganho pelo `aluno.demo`).

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

**Páginas do site** (Início, O que é o clube, Entrar, Quero fazer parte, Seja parceiro): o setup já grava o conteúdo no Elementor. É só abrir a página e **Editar com Elementor** para mudar texto, botão ou ordem das seções. O setup não mexe numa página que já foi montada, então o que você editar fica. Uma exceção: nesta versão (a das páginas de login, valor e aceite) o setup refaz **uma vez** as páginas Início, O que é o clube, Entrar, Quero fazer parte e Seja parceiro, para trocar o valor fixo pelas marcas de valor, pôr a entrada nova e a seção de vantagens do parceiro. Se você já editou alguma delas no Elementor, anote antes de rodar o setup ou refaça a edição depois.

Para voltar uma página ao modelo original: `REFAZER_PAGINAS=1 ./scripts/setup.sh` (apaga as edições feitas nas cinco páginas acima). O conteúdo de cada uma está em `scripts/lib/paginas.php`.

**Valor e duração do clube.** O clube é um serviço pago à parte da mensalidade: hoje **R$ 300,00 por 12 meses**, pagos na recepção da academia, no cartão de crédito, em até 12x (definição do Rodolfo). O site não cobra nada. Valor e duração ficam num lugar só: **Clube → Configurações**. Ao salvar, o novo valor aparece na página inicial, em "O que é o clube", em "Quero fazer parte" e no texto de aceite do aluno. Nos textos das páginas do Elementor, escreva `{{valor_clube}}` (R$ 300,00), `{{valor_clube_mes}}` (valor dividido pelos meses, R$ 25,00) e `{{prazo_clube}}` (12 meses) em vez do número; o site troca na hora de mostrar. Em qualquer outro lugar dá para usar os shortcodes `[cav_valor]`, `[cav_valor_mes]` e `[cav_prazo]`. O protótipo `index.html` da raiz é só um desenho e continua com o valor antigo escrito à mão.

**Aceite do valor.** O formulário "Quero fazer parte" tem duas caixas obrigatórias: a autorização para os parceiros consultarem o CPF, e "Estou ciente de que o clube custa R$ X por 12 meses, pagos à parte da mensalidade, na recepção da Alliance, e de que o meu acesso só é liberado depois do pagamento". O texto, o valor, a data e o IP ficam gravados no cadastro do aluno, e a recepção vê "Aceitou R$ X em [data]" antes de aprovar. Se a Alliance mudar o valor enquanto a pessoa preenche, o pedido não passa e ela precisa ler o novo valor e confirmar de novo. O texto está em `texto_aceite()`, em `includes/solicitacoes.php`. **O Rodolfo vai preparar um contrato de prestação de serviços com as regras do clube; quando ele existir, o aceite deve apontar para ele e o texto precisa ser revisto.**

**Pagamento e liberação.** O aluno pede o acesso pelo site e paga na recepção (R$ 300 em até 12x no cartão). A recepção confere o pagamento e aprova em **Aprovações**; a validade já vem sugerida com 12 meses a partir de hoje (dá para mudar a data se o pagamento foi em outro dia). O clube não depende do plano do aluno, então o pedido não pergunta plano nem turma.

**Menores de idade.** O pedido pergunta a data de nascimento. Abaixo de 12 anos o site recusa. De 12 a 17, pede nome e CPF do responsável e uma caixa de autorização; a recepção vê "Menor de idade. Responsável: …" ao aprovar. Na planilha de alunos, as colunas de responsável são obrigatórias só para quem tem menos de 18 anos. A idade mínima está em `CAV_Solicitacoes::IDADE_MINIMA`.

**Importar alunos e parceiros por planilha.** Em **Clube → Importar** há três planilhas, cada uma com botão "Baixar a planilha modelo". Salve a sua como CSV (Excel: *CSV UTF-8*; Google Planilhas: *Fazer download → CSV*); aceita vírgula ou ponto e vírgula, e CPF sem o zero da frente do Excel é corrigido. Toda planilha passa por uma **conferência antes de entrar**: o site mostra quantas linhas estão certas, lista as com erro (CPF inválido ou repetido, e-mail inválido ou já cadastrado, data inválida, campo obrigatório vazio) e só grava quando você confirma. As linhas com erro não entram; corrija e envie de novo só elas.

1. **Alunos**: nome, CPF, e-mail, WhatsApp (opcional), data de nascimento, responsável e CPF do responsável (só para menores de 18), data do pagamento, data do aceite e como aceitou. O acesso vale 12 meses a partir da data do pagamento.
2. **Parceiros**: estabelecimento, categoria, responsável, e-mail do responsável (vira o login), WhatsApp, Instagram, site (pode escrever "não tem"), endereço, horário, benefício, regra para o caixa e "como foi combinado"; limite por aluno e início/fim da promoção são opcionais. Uma caixa na conferência decide se já entram no ar ou como rascunho.
3. **Renovação**: CPF ou e-mail e a data do novo pagamento de quem renovou. O acesso passa a valer 12 meses a partir dessa data, e a conferência recusa pagamento que não estenda o acesso que o aluno já tem. Quem não renovar perde o acesso sozinho quando a validade acabar.

**Enviar os acessos.** Importar **não envia e-mail**. Quem foi importado fica numa fila, e na mesma tela (seção 4) o botão **Enviar agora** manda um lote (50 por padrão, configurável) com um botão "Criar minha senha"; ninguém recebe senha pronta. O link vale **7 dias**; depois a pessoa usa "Esqueci minha senha". Dá para ligar **Enviar sozinho**, que manda um lote a cada 10 minutos até a fila esvaziar (usa o agendador do WordPress, então precisa de visitas ao site ou de um cron de verdade chamando `wp-cron.php`). Antes do primeiro envio configure o serviço de envio em **Clube → E-mails** (servidor, porta, usuário, senha, e-mail e nome de envio), e use o botão de e-mail de teste. Sem isso o WordPress usa o envio simples da hospedagem, que costuma cair no spam. A senha pode ficar no `wp-config.php` com `define( 'CAV_SMTP_SENHA', '...' );`. Código em `includes/importar.php`.

**Página de entrar.** `/entrar/` é uma página do site (e-mail ou usuário, senha, "esqueci minha senha"), no lugar da tela azul do WordPress. Quem tenta abrir `/wp-login.php` é levado para ela, e as telas de "esqueci a senha" e "nova senha", que continuam sendo do WordPress, ganham as cores do site. Cada perfil cai onde sempre caiu (aluno na área do membro, parceiro no terminal, recepção nas aprovações). **Se algo der errado com a página nova, o acesso de emergência do administrador é `/wp-login.php?cav_wp=1`**, que abre a tela original do WordPress.

**Entrar com Google.** Só aparece depois de colar o ID do cliente Google em **Clube → Configurações → Entrar com Google** (a própria tela explica o passo a passo no Google Cloud, uns 10 minutos, uma vez só; é preciso cadastrar o endereço do site em "Origens JavaScript autorizadas", e o endereço do Codespaces é diferente do de produção). O Google **não cria cadastro**: a pessoa só entra se o e-mail da conta Google já for o e-mail dela no clube e o pedido estiver aprovado. Administradores entram só por senha. O servidor confere a assinatura do token do Google, quem emitiu, para qual site, validade e e-mail verificado antes de abrir a sessão.

**Vantagens de ser parceiro.** A Início e a Seja parceiro têm uma seção com seis cartões: alunos chegando até o negócio, sem mensalidade e sem comissão, promoções que o próprio parceiro cria e muda, página do negócio no site, números de uso e conferência só pelo CPF. Tudo que está escrito ali o sistema já faz. O texto está em `scripts/lib/paginas.php` (`$vantagens_parceiro`), um só para as duas páginas. Se a Alliance quiser prometer algo a mais (divulgação nas redes da academia, por exemplo), é só acrescentar um cartão.

**Cabeçalho e rodapé** ficam no tema filho (`template-parts/header.php` e `footer.php`), sem precisar do Elementor Pro. O menu é o "Principal" (Aparência → Menus). Os botões Entrar, Quero fazer parte e, depois do login, "Meu terminal" ou "Minha carteirinha" são fixos no cabeçalho.

**Lista e página de parceiros** (`/parceiros/`): busca por nome, filtro por categoria e, em cada parceiro, os benefícios vigentes. Vêm do tema filho (`archive-cav_parceiro.php`, `single-cav_parceiro.php`). O shortcode `[clube_parceiros limite="6"]` mostra os últimos parceiros em qualquer página.

**Área do aluno.** Quem entra como aluno cai em `/area-do-membro/`, com um menu (Painel, Carteirinha, Benefícios, Ofertas, Agenda, Sorteios, Conteúdos) no topo de todas essas páginas. O setup cria as páginas novas e coloca o menu nas que já existiam.

- **Painel**: saudação e validade, números de uso, próximos eventos, ofertas em destaque e sorteios abertos.
- **Ofertas** (`/ofertas/`): *Descontos exclusivos na academia* e *Produtos com desconto*, cada produto com foto, preço cheio riscado e preço do clube. No painel do WordPress: **Ofertas da academia → Adicionar**. A foto é a *Imagem destacada*; o texto vira a descrição; o selo ("-20%") sai do cálculo dos dois preços, ou digite o seu ("GRÁTIS"). Dá para marcar uma data final, e a oferta some sozinha depois dela. A Alliance decidiu não dar desconto em serviço nem vender produto com preço de aluno, então por enquanto não há ofertas: sem nenhuma publicada, o item "Ofertas" some do menu do aluno e o painel não mostra a seção. O recurso continua no plugin, e o item volta sozinho quando alguém publicar uma oferta.
- **Agenda** (`/agenda/`): calendário do mês com setas para os outros meses, mais a lista de eventos do mês. No painel: **Agenda → Adicionar**, com tipo (seminário, campeonato, graduação, treino aberto, evento), data (e data final para eventos de vários dias), horário, local e um link opcional.
- **Sorteios** (`/sorteios-do-clube/`): dois blocos, *Em andamento* e *Resultados*. Depois que a recepção apura (botão no painel do WordPress, na tela do sorteio), o resultado aparece para todos os alunos com o nome e a inicial do sobrenome do ganhador ("Camila L."). Quem ganhou vê um aviso amarelo "Parabéns, você ganhou" no topo do painel e da página de sorteios por 60 dias, e recebe um e-mail para retirar o prêmio na recepção. O painel também mostra os dois últimos resultados.

Hoje só quem é administrador cadastra ofertas e eventos. A recepção ainda não tem essa permissão.

**Home para quem ainda não é do clube.** A página inicial ganhou a seção *Por dentro do clube*: sorteios, entrada VIP nos seminários, descontos exclusivos na academia e produtos com preço de aluno, mais uma faixa ao vivo (`[cav_clube_agora]`) com o sorteio aberto, o próximo seminário e quantos descontos estão no ar. A faixa só mostra título e data, nada da área restrita. Quando o setup roda e a versão das páginas mudou, ele refaz só as páginas que mudaram de conteúdo, uma vez. O texto da seção está em `scripts/lib/paginas.php`. A frase sobre "condição VIP" é genérica: ajuste para a regra real da academia.

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

## Segurança

O que o plugin já faz sozinho (código em `includes/seguranca.php` e `includes/cripto.php`):

- **CPF não fica legível no banco.** O CPF do aluno, o CPF do responsável e a senha do e-mail (Clube → E-mails) são gravados embaralhados (libsodium). A busca por CPF usa uma "impressão digital" do número. Quem copiar o banco vê só texto sem sentido. A chave **não** fica no banco: vem da constante `CAV_CHAVE_DADOS` do `wp-config.php`. O `setup.sh` cria essa linha sozinho; em produção gere uma (`openssl rand -hex 32`), coloque no `wp-config.php` **e guarde uma cópia fora do backup do banco**. Sem a chave os CPFs gravados não voltam. Se ela sumir ou ainda não existir, o painel avisa. Cadastros antigos, em texto puro, são protegidos sozinhos na primeira carga.
- **Login com limite.** 5 erros na mesma conta, vindos do mesmo endereço, e a conta espera 15 minutos (vale para `/entrar/` e para o `wp-login.php`; outras contas não são afetadas). "Esqueci a senha" responde sempre a mesma coisa, exista a conta ou não, e tem limite de 5 por hora por endereço. Senha nova precisa de 10 caracteres ou mais.
- **Formulários públicos** (pedido do aluno e candidatura do parceiro): campo-armadilha para robô, tempo mínimo de preenchimento e limite por endereço (15 pedidos por hora, 5 candidaturas). A mensagem de "já cadastrado" é a mesma para CPF e e-mail, então o formulário não confirma quem faz parte do clube.
- **Terminal do parceiro.** 60 consultas de CPF por hora e 300 por dia por operador. As consultas guardam só a impressão digital do CPF e somem em 90 dias; o endereço IP dos usos e das inscrições é apagado depois de 12 meses (limpeza diária).
- **A lista de quem tem conta não vaza**: a API `/wp-json/wp/v2/users`, `?author=1`, a página de autor, o mapa do site e o oEmbed ficam fechados para visitantes. O XML-RPC e as senhas de aplicativo estão desligados e o editor de arquivos do painel também.
- **Conteúdo de membro** (exclusivos e sorteios) não aparece na busca do site, no mapa do site, no oEmbed nem na versão "embed" da página.
- **Parceiro e aluno não entram no painel** do WordPress; só nas áreas deles.
- **Cabeçalhos** `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` e, com https, `Strict-Transport-Security`. Páginas do clube e qualquer página vista por alguém logado saem sem cache.
- **Planilhas importadas** não ficam gravadas no servidor. A prévia fica embaralhada no banco por 20 minutos e some.

Atrás de Cloudflare ou outro proxy, o endereço do visitante chega num cabeçalho. Para os limites enxergarem o endereço certo, coloque no `wp-config.php`: `define( 'CAV_PROXY_CONFIAVEL', true );` (só faça isso se o site realmente estiver atrás desse proxy).

**Antes de abrir o site ao público** (o painel mostra um aviso para cada item que faltar):

1. https ligado e o certificado válido.
2. `CAV_CHAVE_DADOS` no `wp-config.php`, com cópia guardada em outro lugar.
3. Nenhuma conta de teste (`aluno.demo`, `parceiro.demo`, `recepcao.demo`, `aluno.vencido`) e nenhum usuário chamado `admin`. Crie um administrador com outro nome e senha longa.
4. Ative a verificação em dois passos nos administradores e na recepção (plugin oficial **Two Factor**).
5. Atualizações automáticas ligadas no WordPress, no Elementor e nos plugins.
6. Backup diário do banco **e** dos arquivos, guardado fora da hospedagem, com acesso restrito. O backup do banco com os CPFs embaralhados é inútil sem a chave; guarde a chave em outro lugar.
7. Na hospedagem: usuário do banco só com acesso a este banco, phpMyAdmin fechado ao público, `WP_DEBUG_DISPLAY` falso, permissões 644 para arquivos e 755 para pastas, `wp-config.php` com 600 ou 640.
8. Quem recebe os dados: os CPFs são dados pessoais (LGPD). Peça à Alliance para registrar quem tem acesso à fila de aprovações e à lista de alunos, e use o contrato do clube para dizer para que o CPF é usado e por quanto tempo.

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
6. Faça a lista da seção **Segurança** acima antes de divulgar. O principal: a chave `CAV_CHAVE_DADOS` no `wp-config.php` **antes** de importar o primeiro aluno.

## O que ainda não está resolvido

- **E-mail em produção.** A tela **Clube → E-mails** já está pronta, mas falta a Alliance criar a conta num serviço de envio (Brevo, Amazon SES ou Postmark), verificar o domínio e colar os dados lá. Localmente o Mailpit recebe tudo.
- **Elementor Pro.** É pago e não dá para instalar pelo script. Instale com a sua licença se for usar o Theme Builder para o cabeçalho, o rodapé e a página individual do parceiro.
- **Diretório por distância.** A lista de parceiros busca por nome e categoria. Busca por distância ou mapa pede o Voxel ou o GeoDirectory.
- **Login com Google.** Está pronto, mas só funciona depois que a Alliance cria o ID do cliente no Google Cloud e cola em Clube → Configurações. Foi testado só com uma chave de mentira, não com o Google de verdade. A escolha de acesso do protótipo (aluno ou parceiro) ainda não existe: a mesma página serve todo mundo.
- **Sorteios.** Com o clube pago, a participação passa a ter contrapartida financeira. Vale a Alliance confirmar com o contador se precisa de autorização da SPA/Ministério da Fazenda antes do primeiro.

## Estado dos testes

Confirmado no Codespaces: o setup, o WordPress, o plugin, o login por perfil e o terminal de consulta.

Conferido num WordPress de verdade rodando no sandbox (WordPress Playground, sem o Elementor nem o tema): a área do aluno (painel, ofertas, agenda, menu) e a do parceiro (promoções, meu negócio, envio de foto e logo, página pública), no computador e no celular, e os scripts que criam as páginas e os dados de teste, inclusive rodando duas vezes.

Conferido só por simulação: o visual do cabeçalho, do rodapé e das páginas (num navegador, com uma imitação do HTML do Elementor, no computador e no celular), e a sintaxe de todos os arquivos PHP.

A página de entrar, o aceite, o valor configurável e a tela de Configurações foram conferidos nesse mesmo WordPress de teste (entrar por perfil, mensagens de erro, sair, redirecionamentos, valor novo aparecendo em todas as páginas, aceite gravado e visível para a recepção). O login com Google foi conferido com uma chave de teste: token válido entra; token adulterado, vencido, de outro site ou de e-mail não cadastrado é recusado.

Ainda sem confirmação: o login com o Google real, a gravação das páginas no Elementor de verdade (`scripts/paginas-elementor.php`) e a aplicação da paleta (`scripts/elementor-kit.php`). Se uma página abrir como texto simples em vez do layout, o Elementor recusou a estrutura; o texto está lá como reserva e o erro ajuda a corrigir.
