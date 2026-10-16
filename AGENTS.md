# AGENTS.md — divi-child (tema SETCEB)

Instrucoes para agentes de IA e para quem mexer neste tema.

O `AGENTS.md` da pasta superior (`starfez/`) vale para todo o
ecossistema. Este arquivo cuida do que e especifico do tema.

---

## 1. Regra do tamanho de arquivo

**Nenhum arquivo novo ou estendido pode passar de 800 linhas.**

Acima disso o arquivo deixa de ser lido por gente e deixa de ser
navegado por agente. O custo aparece antes de parecer: em 800 linhas ja
nao da para ver o topo e o fim da mesma tela.

### Onde o limite ja foi estourado

| Arquivo | Linhas | Observacao |
|---|---:|---|
| `style.css` | 4207 | ver secao 2 |
| `includes/juridico.php` | 3445 | precisa ser partido |
| `includes/importer.php` | 1014 | passar de 800 |
| `page-associado.php` | 948 | passar de 800 |
| `juridico-admin.css` | 856 | passar de 800 |
| `associado.js` | 803 | no limite |

### O que fazer quando um arquivo passa de 800

Nao criar um segundo arquivo "para o resto" e deixar o original
intacto. Partir por responsabilidade, mantendo o que ja existe
funcionando:

1. **Listar o que o arquivo faz** e agrupar por dominio.
2. **Extrair** cada grupo para `includes/<dominio>-<parte>.php` (ou
   `<dominio>-<parte>.css` / `.js` para o front).
3. **`require_once` do novo arquivo** no ponto em que o original era
   carregado — ver `functions.php`, que ja faz isso por dominio.
4. **Nao renomear** as funcoes publicas. `setceb_sol_*` e API usada
   por outros lugares e por hooks.
5. **Nao misturar** dominio novo em arquivo antigo. Um modulo novo
   nasce em arquivo novo.

### Ao tocar `includes/juridico.php` (3445 linhas)

Este arquivo e o pior caso: 3445 linhas, 76 funcoes `setceb_sol_*`, e
concentra consulta, regra, view, painel do admin, AJAX e formulacao no
mesmo lugar. O `por_pagina` lido sem existir (ver secao 4) foi
encontrado em 3098 — dentro de um arquivo onde ninguem acha mais nada.

**O arquivo ainda nao foi partido.** A divida existe. As partes abaixo
sao o destino planejado, nao arquivos que existem hoje:

| Arquivo futuro | Leve para ca |
|---|---|
| `includes/juridico-dados.php` | `WP_Query`, leitura de post meta, `setceb_sol_obter()`, `setceb_sol_listagem_admin()`, calculo de status |
| `includes/juridico-formato.php` | `setceb_sol_data()`, `setceb_sol_tempo_relativo()`, helpers de texto |
| `includes/juridico-view.php` | `setceb_sol_render_*`, `setceb_sol_botao_voltar()`, markup de conversa e listagem |
| `includes/juridico-admin.php` | `setceb_sol_pagina_dashboard()`, cards e filtros do painel |
| `includes/juridico-ajax.php` | `wp_ajax_*`, `setceb_sol_ajax_conversa()` |
| `includes/juridico-form.php` | `admin_post`, nonces, gravacao |

Ate a divisao acontecer: **funcionalidade nova nao entra no arquivo de
3445 linhas.** Cria-se o arquivo da tabela acima com o que e novo, e o
antigo so encolhe. Nao se cria "juridico2.php" para o que sobra.

A divisao e uma mudanca grande e arriscada: mexe em 76 funcoes usadas
por hooks, pelo portal e pelo painel. Fazer em um passo, com os testes
rodando, e **so com confirmacao explicita** — nao fazer por conta
propria.

---

## 2. `style.css` tem 4207 linhas: tratar por secao, nao como arquivo unico

CSS nao tem `require`, entao nao da para partir em varios arquivos sem
alterar o `enqueue`. O que se faz enquanto ele nao for partido:

1. **Toda regra nova vai dentro de um bloco `@media` do dominio
   correto**, nunca em qualquer lugar do arquivo. Media query nao
   aumenta especificidade: a posicao no arquivo manda.
2. **Regra base de um elemento so no mobile** mora **dentro** do bloco
   `@media (max-width: ...)`, nunca como regra base depois dele.
   Elemento escondido no desktop nao tem estilo base.
3. **Esconder no desktop** sempre em `@media (min-width: 1201px)` no
   fim do bloco do dominio. Nunca `display: none` na regra base.
4. **Toda secao fecha com o proprio bloco responsivo**, na ordem
   base -> `min-width` (esconder no desktop) -> `max-width: 1200` ->
   `700` -> `480`.

Esteja ciente de que a secao do juridico esta no **fim** do arquivo e o
resto do portal no meio. Regra base escrita depois de um media query
sobrescreve o media query: e a causa de bugs ja vistas neste tema
(ver secao 5).

---

## 3. Componente reutilizavel antes de markup novo

Toda vez que um bloco visual ou um trecho de interface se repete, ele
vira **um** componente, nao uma segunda copia.

### Como decidir

| Situacao | O que fazer |
|---|---|
| Mesmo bloco em 2 lugares | Extrair o segundo, apontar para o mesmo |
| Diferente mas com a mesma forma (header de modulo X e Y) | Um componente com parametro |
| Vai ter um terceiro caso amanha | Componente agora, nao "quando precisar" |
| So acontece uma vez | Deixa como esta |

### Componentes que ja existem e nao devem ser duplicados

- **Cabecalho de modulo** — `setceb_modulo_cabecalho()` em
  `page-associado.php`. Todos os 9 modulos usam. Rotulo do botao
  "Voltar" e dividido em dois niveis (`.assoc-mod__back-full` /
  `.assoc-mod__back-short`) para o mobile; nao criar outro botao de
  voltar.
- **Botao de voltar da conversa** — `setceb_sol_botao_voltar()`. Um so,
  reusado pelas duas panes (nova e protocolo).
- **Ano de referencia** — `.assoc-year` / `.assoc-year__btn`.
- **Indicadores do painel do advogado** — `.setceb-sol-card`.
- **Botoes** — `.assoc-btn`, `.assoc-btn--boletos`. Nao criar
  `<button class="button-novo">` com cor hardcoded.
- **Badge de status** — `.assoc-badge`, com `--setceb-sol-cor` como
  variavel. Status novo = uma entrada em `setceb_sol_status()`, nao um
  CSS novo.

### Regra do `width: 100%`

`.assoc-year__btn` e `.assoc-btn--boletos` recebem `width: 100%`
**na barra de ferramentas** (bloco de 700px). A mesma
`.assoc-year__btn` aparece no cabecalho do modulo, onde 100% nao faz
sentido: o filtro divide a linha com o botao de voltar e o titulo.
Ao aplicar `width` a um componente, confirmar **onde** ele aparece.

---

## 4. Dominio juridico: contrato de dados

Toda funcao que devolve `array()` tem esse contrato escrito, e o
consumidor pode ler **todas** as chaves sem `??`.

Ler chave que a funcao nao devolve gera `Warning: Undefined array key`
na tela do admin. Ja aconteceu com `por_pagina`. Hoje
`audit-contrato-php.py` pega esse caso; nao desligue o lint.

Ao devolver um array:

1. Listar **todas** as chaves no `@return` do docblock.
2. Devolver **todas** no `return array()`.
3. Se um valor pode nao existir, devolver com padrao (`0`, `''`,
   `array()`) — nunca deixar a chave ausente.

Na duvida: `$total = (int) ( $dados['total'] ?? 0 );` e melhor que
`$total = (int) $dados['total'];` **se** a chave puder faltar. Se ela
sempre existe, o contrato e que ela exista.

---

## 5. Armadilhas conhecidas deste tema

Nao descobrir de novo. Cada uma custou uma rodada de correcao.

1. **`box-sizing: border-box` so vale para `body.login`.** Em todo o
   resto do tema vale `content-box`: `width: 100%` + padding estoura a
   caixa. No portal do associado ha uma regra propria que declara
   `border-box` dentro do bloco de 1200px.
2. **Media query nao aumenta especificidade.** Regra base escrita
   depois vence. Foi a causa do bloco de mobile do juridico inteiro
   estar morto (18 inversoes) e do cabecalho da conversa com 89px.
3. **Medir elemento flex nao mede texto.** `.assoc-mod__body` e item
   `flex: 0 1 auto`: encolhe ate o proprio texto, entao
   `clientWidth == scrollWidth` **mesmo com o texto cortado**. Para
   saber se o titulo cabe, medir o espaco real que sobra no
   `.assoc-mod__main` (ver `medir-titulo-real.js`).
4. **jsdom nao tem motor de layout.** Nao da para medir largura,
   altura, `position` ou cascata de media query. So responsividade
   precisa de Chromium de verdade (Playwright ja instalado).
5. **`dashicons` nao existe no preview.** O harness monta o HTML fora
   do WordPress e a fonte nao e enfileirada: icones aparecem como
   caixa vazia. Nao e bug. Para conferir se um icone esta presente,
   ler `display` por estilo computado, nao olhar o screenshot.
6. **Preview de HTML desatualizado.** O harness gera
   `html-structure/*.html`; depois de mudar um template, regerar com
   `SETCEB_QA_HTML=<dir> php setceb-qa.php <cenario>` antes de medir.
7. **A fonte nao existe no preview** — o preview usa a fonte do
   navegador, nao a Montserrat do tema. Folga de texto medida no
   preview e indicativa, nao definitiva.

---

## 6. Como validar

Nada de "parece certo". Rodar tudo antes de commit:

```bash
QA=/private/var/folders/17/vjxws1pd1jnb_l576lgb7lp00000gn/T/opencode

# sintaxe
php -l page-associado.php && php -l includes/juridico.php
for j in associado juridico-admin header; do node --check $j.js; done

# CSS: parseia e conta regra (tinycss2 ja instalado)
python3 -c "import tinycss2; [print(f, len(tinycss2.parse_stylesheet(open(f).read(), skip_comments=True, skip_whitespace=True))) for f in ['style.css','juridico-admin.css']]"

# harness PHP, 9 cenarios
export SETCEB_TEMPLATE="$PWD/page-associado.php"
for c in padrao juridico admin sol-lista sol-na-url sol-nova-url \
         aviso-juridico sem-solicitacoes sem-perfil; do
  php "$QA/setceb-qa.php" "$c" | grep Resumo
done
```

Os testes de navegador e lint vivem em `$QA` (fora do repositorio: sao
ferramentas de QA, nao entrega):

| Arquivo | O que trava |
|---|---|
| `test-mobile.js` | fluxo do juridico no iPhone 12, formularios so por clique |
| `test-desktop.js` | grid sem coluna fixa, conversa entra na tela ao clicar |
| `test-cascata-mobile.js` | cada valor de mobile, em 7 larguras |
| `test-wizard.js` | wizard de 2 passos (jsdom) |
| `test-admin-lista.js` | listagem e AJAX do advogado (jsdom) |
| `check-structure.php` | 10 paineis irmaos e balanceados |
| `audit-cascata.py` | regra base vencendo media query |
| `audit-contrato-php.py` | leitura de chave nao devolvida |
| `audit-largura.py` | `width` fixo acima de 320px no mobile |

Regra: **mudou comportamento, mudou teste.** Se a mudanca nao tem
assercao que falharia antes, ainda nao esta coberta.

---

## 7. Git

- Bump em `Version:` do `style.css` a cada alteracao de tema.
- Commit pequeno e com **por que**, nao so o que. Números medidos
  valem mais que adjetivo.
- Branch: `setceb`.
- Deploy e manual: subir para
  `/home/setcebco/public_html/wp-content/themes/divi-child-setceb`.
  Depois, **Ctrl+Shift+R** no celular. Cache de CSS e a causa numero
  um de "mas eu ja subi".

---

## 8. O que nao fazer

- Nao criar arquivo novo sem antes procurar se ja existe (secao 3).
- Nao passar de 800 linhas (secao 1).
- Nao mexer em `style.css` fora do bloco do dominio.
- Nao escrever `style="..."` com cor ou medida no PHP quando existe
  classe CSS. Variavel CSS (`--setceb-sol-cor`) e o caminho.
- Nao renomear funcao `setceb_*` ou `setceb_sol_*` publica.
- Nao usar `?sol_status=` na URL do associado: a chave nao existe no
  tema. O filtro por status so existe no painel do advogado
  (`setceb_sol_status()` e outra coisa).
- Nao criar migration nem alterar dado sem confirmacao.
