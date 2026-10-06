# Clube Alliance — v0.5.0

Motor de elegibilidade e terminal do parceiro para o clube de vantagens.
Funciona sozinho, sem depender de qual plugin de diretório você adotar depois.

## Instalação

1. WordPress → Plugins → Adicionar → Enviar plugin → `clube-alliance.zip` → Ativar.
2. A ativação cria duas tabelas (`cav_usos`, `cav_consultas`) e dois papéis:
   **Membro do Clube** e **Parceiro do Clube**.

## Configuração mínima

**1. Cadastre um parceiro**
Menu *Parceiros* → Adicionar. Título, foto, descrição, categoria.

**2. Cadastre os benefícios dele**
Menu *Benefícios* → Adicionar. Escolha o parceiro, escreva a regra
(ex.: "15% no corte, seg a qui") e defina o limite por membro.
Um parceiro pode ter quantos benefícios quiser.

Dois controles independentes:

- **Limite por membro** — de que em que o mesmo membro pode repetir.
  "Sem limite" é o padrão: usa toda vez que aparecer.
- **Vigência** — em branco = permanente, nunca expira. Preencha só
  "termina em" para uma campanha; depois da data ela some sozinha do
  terminal, sem ninguém precisar lembrar de desligar.

O checkbox *Benefício ativo* é o corte manual: desmarcou, sai do ar na hora,
independente da vigência.

**3. Crie o usuário do estabelecimento**
Usuários → Adicionar, papel **Parceiro do Clube**. Depois edite o usuário
e, em *Vínculo com parceiro*, aponte para o estabelecimento.

**4. Cadastre um membro**
Usuários → Adicionar, papel **Membro do Clube**. Edite o perfil e preencha
**CPF** e **Válido até**.

## Páginas a criar no Elementor

| Página | Shortcode | Quem acessa |
|---|---|---|
| Terminal | `[cav_terminal]` | Parceiro |
| Painel do parceiro | `[cav_painel_parceiro]` | Parceiro |
| Minha carteirinha | `[cav_carteirinha]` | Membro |
| Meus benefícios | `[cav_meus_usos]` | Membro |
| Quero fazer parte | `[cav_solicitar]` | Público |
| Seja parceiro | `[cav_candidatura]` | Público |
| Conteúdos exclusivos | `[cav_conteudos]` | Conforme o público |
| Sorteios | `[cav_sorteios]` | Conforme o público |
| Área dos parceiros | `[cav_conteudos publico="parceiros"]` | Parceiro |

Use o widget *Shortcode* do Elementor.

## Cores e fontes

O plugin lê as variáveis globais do Elementor. Se o Kit do site define a
paleta e a tipografia, o terminal, o painel e a carteirinha já saem na
identidade certa — e acompanham qualquer mudança futura no Kit.

Mapa: `--cav-marca` ← cor primária · `--cav-fraco` ← secundária ·
`--cav-destaque` ← accent · títulos e números ← tipografia primária ·
corpo ← tipografia de texto.

Para forçar um valor exato, sem editar o plugin (sobrevive a updates),
no `functions.php` do tema filho:

```php
add_filter( 'cav_tokens', function ( $t ) {
    $t['--cav-marca']    = '#0B0B0B';
    $t['--cav-destaque'] = '#C8102E';
    return $t;
} );
```

Tokens disponíveis: `--cav-tinta`, `--cav-marca`, `--cav-destaque`,
`--cav-papel`, `--cav-linha`, `--cav-fraco`, `--cav-sim`, `--cav-nao`,
`--cav-raio`, `--cav-fonte-titulo`, `--cav-fonte-texto`.

Verde e vermelho ficam fora da paleta de marca de propósito: no balcão eles
são sinal, não decoração.

## Aprovações

Quem aprova é a **recepção**. O plugin cria o papel *Recepção do Clube*: a
conta entra no WordPress e cai direto na tela de aprovações, sem acesso a
posts, plugins ou configurações.

**Aluno.** Preenche `[cav_solicitar]` com nome, e-mail, CPF, WhatsApp, turma
e o aceite da consulta de CPF. O cadastro nasce com status *pendente* e não
tem acesso a nada. Na fila, a recepção **digita a data de validade** e
aprova — o aluno recebe um e-mail com link para definir a senha.

**Estabelecimento.** Preenche `[cav_candidatura]`. Ao aprovar, o plugin faz
tudo de uma vez: cria o estabelecimento no diretório (rascunho), cria o
benefício proposto (rascunho, já vinculado), cria o login do operador e
envia o e-mail com o link de definição de senha e o endereço do terminal.

Nada de senha em texto no e-mail: vai sempre um link de definição, com a
validade padrão do WordPress.

Para o e-mail trazer o endereço do terminal, salve a URL:

```php
update_option( 'cav_url_terminal', 'https://clube.seusite.com.br/terminal/' );
```

> `wp_mail` sem SMTP costuma cair em spam ou nem sair, principalmente para
> Gmail. Configure um SMTP (Brevo, Amazon SES, Postmark) antes de abrir os
> formulários ao público — senão ninguém recebe o acesso.

## Áreas exclusivas

Dois post types novos: **Conteúdos exclusivos** e **Sorteios**. Cada item tem
um seletor *Quem pode ver* na lateral, com três opções:

- **Membros** — só quem tem matrícula ativa
- **Parceiros** — só quem opera um estabelecimento
- **Membros e parceiros** — os dois

Quem não se encaixa não vê o item nem nas listagens, e o corpo do post fica
bloqueado se alguém chegar pelo link direto.

Sem shortcode: `[cav_conteudos]` e `[cav_sorteios]` já mostram só o que o
visitante pode ver. Com `publico="parceiros"` você monta uma página que é a
área do parceiro. Para proteger um trecho solto no Elementor:

```
[cav_restrito publico="parceiros"]
  ...qualquer conteúdo...
[/cav_restrito]
```

### Sorteios

No sorteio você define prêmio e janela de inscrição. O membro clica em
*Participar* e entra na lista — uma inscrição por pessoa, garantido no banco.
Fora da janela o botão não aparece.

A apuração é um botão no editor do sorteio. Ela usa `random_int`, sorteia
apenas entre inscritos **com matrícula ainda ativa** no momento da apuração,
grava ganhador e data, e não pode ser refeita.

> Sorteio aberto ao público no Brasil costuma exigir autorização prévia da
> SPA/Ministério da Fazenda quando é promoção comercial. Vale conferir com a
> Alliance antes de publicar o primeiro. O plugin executa a mecânica, não
> resolve a parte regulatória.

## Como funciona no balcão

1. Parceiro digita o CPF → tela responde **TEM BENEFÍCIO** (verde) ou
   **SEM BENEFÍCIO** (vermelho), com o primeiro nome para conferência.
2. Aparecem os benefícios ativos do estabelecimento. Parceiro toca em um
   → registra o uso.
3. O registro alimenta o painel do parceiro, o histórico do membro e o
   relatório em *Clube* no admin.

## Decisões de LGPD já embutidas

- A consulta devolve só status + primeiro nome. Nunca nome completo,
  telefone, e-mail ou plano.
- A tabela de log guarda **hash** do CPF consultado, nunca o número.
- Toda consulta fica logada com o operador e o IP.
- Limite de 120 consultas por hora por conta de parceiro (constante
  `CAV_Usos::RATE_LIMIT`).
- Cada estabelecimento tem login próprio — não compartilhe senha entre parceiros.

Falta você adicionar: o texto de consentimento no formulário de cadastro
("autorizo que estabelecimentos parceiros consultem meu CPF para verificar
elegibilidade"). O campo `cav_consentimento` já está reservado.

## Se você adotar o Voxel ou o GeoDirectory

O plugin registra o CPT `cav_parceiro` só quando ninguém aponta outro.
Para usar o post type do diretório, adicione no `functions.php` do tema filho:

```php
add_filter( 'cav_parceiro_post_type', fn() => 'places' );
```

Os benefícios passam a se vincular aos posts do diretório e o resto continua igual.

## O que ainda não está aqui

- Cadastro do membro pelo frontend (hoje é pelo admin)
- Sincronização com a lista de alunos ativos (CSV ou API)
- QR code na carteirinha
- Edição de benefícios pelo parceiro no frontend
