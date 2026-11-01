=== HDiscount ===
Contributors: andersonhofelmann
Donate link: https://github.com/sponsors/anderhofelmann
Tags: woocommerce, discount, quantity discount, pack pricing, bulk discount, dynamic pricing
Requires at least: 5.0
Requires PHP: 7.0
Tested up to: 6.4
WC requires at least: 3.0
WC tested up to: 8.0
Stable tag: 1.6.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema de regras de desconto por quantidade (packs) para WooCommerce. Crie promoções do tipo "Leve X por R$ Y", "Compre X Leve Y" ou desconto percentual por volume, com controle total de período, produtos, variações e limites.

== Description ==

**HDiscount** permite que lojistas criem regras flexíveis de desconto por quantidade no WooCommerce, conhecidas como "preço de pack" ou "volume pricing".

### Funcionalidades Principais

**Tipos de Desconto:**
- **Preço Fixo do Pack** — Ex: "Leve 3 por R$ 100,00" (preço total do pack)
- **Desconto Percentual** — Ex: "20% OFF a partir de 3 unidades"
- **Compre X Leve Y** — Ex: "Compre 4, Leve 3" (paga 3, leva 4)

**Controle Total:**
- Período de vigência com data/hora de início e fim
- Seleção de produtos (simples e variáveis)
- Quantidade mínima e máxima do pack
- Opção: variações idênticas OU quaisquer variações do mesmo produto
- Limite: aplicar desconto apenas até a quantidade máxima (ex: comprando 4, só 3 entram na promo)

**Exibição:**
- Badge automático na página do produto ("Leve 3 por R$ 100,00")
- Preço riscado + preço promocional no carrinho
- Colunas informativas na lista de regras do admin

**Compatibilidade:**
- PHP 7.0 a 8.3+
- WooCommerce 3.0 a 8.0+
- WordPress 5.0 a 6.4+
- Funciona com produtos simples e variáveis
- Compatível com HPOS (High Performance Order Storage)

### Exemplo Prático

> O vendedor tem um produto que custa R$ 35,00. Se o comprador comprar 3, vira um pack com preço de R$ 100,00, independente da variação.

Configuração:
1. Cria regra "Preço Fixo do Pack"
2. Quantidade mínima: 3
3. Preço do pack: 100,00
4. "Quaisquer variações" (permite misturar cores/tamanhos)
5. Produtos: seleciona o(s) produto(s)
6. Salva → ativo imediatamente

### Para Desenvolvedores

- Código limpo, comentado e seguindo WordPress Coding Standards
- Hooks e filters para extensibilidade
- Cache de regras para performance
- Preparado para HPOS

== Installation ==

1. Faça upload da pasta `hdiscount` para `/wp-content/plugins/`
2. Ative o plugin em **Plugins > Plugins instalados**
3. Acesse **WooCommerce > HDiscount** no menu admin
4. Clique em "Adicionar Nova Regra" e configure sua promoção

== Frequently Asked Questions ==

= Funciona com produtos variáveis? =
Sim. Você escolhe se a regra vale apenas para a **mesma variação** (ex: 3 camisetas vermelhas P) ou para **quaisquer variações** (ex: 1 vermelha P + 1 azul M + 1 preta G = 3 no pack).

= Posso limitar para que apenas X unidades entrem na promoção? =
Sim. Use "Quantidade Máxima" + "Limitar Desconto à Quantidade Máxima". Ex: mín=3, máx=3, limite=sim → comprando 4, apenas 3 entram no preço do pack, a 4ª volta ao preço normal.

= O desconto aparece no carrinho e checkout? =
Sim. O preço original fica riscado e o preço promocional aparece em verde, tanto no preço unitário quanto no subtotal.

= Conflita com cupons? =
Não. O HDiscount altera o preço base do item no carrinho. Cupons são aplicados depois, sobre o valor já com desconto do pack.

= Funciona com HPOS (tabelas otimizadas de pedidos)? =
Sim, o plugin usa apenas APIs públicas do WooCommerce, compatível com HPOS.

== Screenshots ==

1. Lista de regras com colunas: Tipo, Produtos, Qtd Min/Max, Período, Status
2. Tela de edição: abas Configuração, Período, Produtos
3. Busca de produtos com autocomplete por nome/SKU
4. Badge exibido na página do produto
5. Carrinho mostrando preço promocional

== Changelog ==

= 1.6.2 =
* Correcao: na lista de regras, os links de filtro (Todas, Publicadas, Rascunhos...) sumiam e o PHP registrava avisos. O selo de versao tratava a lista de links como texto; agora entra como mais um item da lista

= 1.6.1 =
* Corrige o link "Visitar o site do plugin": apontava para uma pagina inexistente
  no GitHub (usuario escrito errado)
* Atualizacao automatica mais segura: so oferece versoes de releases do proprio
  repositorio, por HTTPS e com SHA-256 publicado; o hash agora e conferido no
  arquivo que sera de fato instalado (antes, release sem hash era aceita)
* A consulta de atualizacao nao envia mais o endereco do site ao GitHub
* A lista de regras escapa a saida de tipo e status; salvar limita o percentual
  entre 0 e 100 e o preco do pack a valores nao negativos

= 1.6.0 =
* O guia do plugin virou um modal, aberto pelo botao "Sobre o HDiscount"
* Elimina a aba que exigia permissao de edicao e devolvia erro de acesso
* Botao na lista de regras, no topo da tela de edicao e na linha do plugin
  na tela Plugins
* Modal acessivel: foco preso dentro dele, retorno do foco ao fechar,
  fecha com Esc ou clicando no fundo

= 1.5.1 =
* Correcao: a aba "Sobre" exigia permissao de edicao e devolvia
  "sem permissao" para quem so tem acesso de leitura ao painel
* A aba agora abre para qualquer usuario logado e o atalho usa a URL correta
  conforme o menu onde a pagina foi registrada

= 1.5.0 =
* Icono proprio do plugin (etiqueta de preco com o simbolo de desconto)
* Menu do WooCommerce passa a usar o icone de desconto nativo do WordPress
  nas versoes 5.3 ou mais novas, com reserva para as anteriores
* Logotipo na aba Sobre

= 1.4.3 =
* Reforco da compatibilidade PHP 7: o verificador agora detecta funcoes
  exclusivas do PHP 8 (str_contains, get_debug_type, array_is_list e afins),
  alem da sintaxe
* CI ampliado para PHP 7.0 ate 8.4

= 1.4.2 =
* Versao visivel nas telas do plugin: nas abas da lista de regras e no topo
  da tela de edicao, para conferir rapidamente qual versao esta instalada

= 1.4.1 =
* A aba "Sobre" agora tem prioridade correta no menu e, se o menu nao existir,
  e presa ao menu do WooCommerce
* Atalho para a aba "Sobre" no topo da lista de regras
* Atualizador verifica o SHA-256 do pacote contra o hash publicado na release
  e recusa a instalacao se o arquivo tiver sido alterado
* Atualizador so aceita pacotes em HTTPS

= 1.4.0 =
* Nova aba "Sobre" em HDiscount > Sobre, com o guia de uso antes da primeira regra
* Seguranca: o endpoint AJAX agora exige permissao de edicao, alem do nonce
* Seguranca: todos os arquivos bloqueiam acesso direto pela guarda ABSPATH
* Seguranca: a desinstalacao apaga tambem os transients do atualizador
* Link do autor passou a apontar para o GitHub

= 1.3.1 =
* Painel avisa quando a lista de produtos da mistura tem precos diferentes:
  o preco do pack e fixo, entao a economia muda conforme quais itens entram
* Confirmacao visual quando todos os produtos tem o mesmo preco

= 1.3.0 =
* Nova opcao "Misturar Produtos no Pack": as unidades do pack podem vir de
  produtos diferentes. Com "Camiseta" e "Calca" na regra, comprar 1 + 2 fecha
  o pack de 3
* Correcao: com produtos de precos diferentes no mesmo pack, a unidade avulsa
  pagava um preco medio, e nao o dela
* Atualizacao automatica a partir das releases do GitHub: o painel avisa
  quando ha versao nova e instala pelo proprio WordPress

= 1.2.6 =
* Escolher uma variacao agora adiciona o PRODUTO PAI na lista, quando a opcao
  "Aplicar a todas as variacoes" esta ligada
  - Antes entrava so a variacao e o produto pai nao aparecia na lista
* A busca mostra o aviso "inclui todas as variacoes" nas variacoes

= 1.2.5 =
* Correcao: quando a mesma regra valia para mais de um produto no carrinho,
  o desconto era sobrescrito em vez de somado
  - Ex: BLUSA 0001 (3 un) e BLUSA 0002 (3 un) com "3 por R$100" davam
    desconto de R$5,00 em vez de R$10,00

= 1.2.4 =
* Correcao: ao abrir uma regra ja salva, os produtos apareciam sem o nome
  (so a etiqueta "variavel"). O redesenho da lista agrupada depende de
  atributos que nao estavam no HTML

= 1.2.3 =
* A lista de produtos agora mostra o produto pai primeiro e as variacoes dele
  logo abaixo, recuadas e ligadas por uma linha
* Correcao: ao reabrir uma regra, apenas o primeiro produto era listado
  (os IDs gravados sao separados por virgula e lidos como tal)

= 1.2.2 =
* A linha de desconto no total passou a usar o nome da regra:
  "Desconto (BLUSA Pack Inverno)   -R$ 15,00"
* Se a regra nao tiver nome, mostra a descricao dela (ex: "Leve 3 por R$ 100,00")

= 1.2.1 =
* O desconto agora aparece apenas no TOTAL do carrinho, como um cupom
  - Os produtos ficam com o preco normal, sem preco medio e sem valores riscados
  - No total aparece uma linha "Promocao: Leve 3 por R\$ 100,00 (5 un)" com o valor descontado
* Revertida a divisao das linhas do carrinho: cada variacao continua na sua linha
* Total continua correto: 5 un x R\$35,00 = R\$175,00 - R\$5,00 = R\$170,00

= 1.2.0 =
* O carrinho agora separa as linhas: unidades do pack com o preco do pack,
  e unidades avulsas com o preco normal
  - Antes: "2 x R$34,00" (preco medio, confuso)
  - Agora: "3 x R$33,33 = R$100,00" (pack) e "1 x R$35,00" (avulsa)
* As partes voltam a se juntar antes de cada calculo, entao mexer na
  quantidade no carrinho continua funcionando
* Se a promocao terminar ou for desativada, o carrinho volta a uma linha so
  e com o preco normal

= 1.1.6 =
* A conta do desconto aparece uma unica vez (por produto), em vez de repetir
  em cada variacao, o que sugeria um pack por linha
* A conta agora mostra o total final, explicando o preco unitario medio

= 1.1.5 =
* Correcao: o preco original deixava de ser lido de volta do carrinho, o que
  contaminava a conta permanentemente em quem ja tinha usado a 1.1.4
  - Sintoma: produto de R$35,00 aparecia como R$34,00 e o total fechava em
    R$134,00 em vez de R$135,00
* Dados do HDiscount gravados na sessao sao limpos ao carregar o carrinho,
  entao o preco cadastrado volta a valer sem intervention manual

= 1.1.4 =
* O carrinho agora mostra a conta do desconto, evitando que o preco unitario
  medio (ex: R$34,00 em 5 unidades de "3 por R$100") pareca erro de preco
  - 3 un: "1 pack de 3 por R$ 100,00"
  - 5 un: "1 pack de 3 por R$ 100,00 + 2 avulsas a R$ 35,00"

= 1.1.3 =
* Correcao critica: o desconto se aplicava em cima dele mesmo a cada recalculo
  do carrinho (o WordPress recalcula varias vezes por request), haciendo o
  total cair de R$135,00 para R$133,75 e depois R$133,44
* Correcao: ao baixar a quantidade e perder a promocao, o preco voltava ao
  valor rebaixado em vez do preco normal

= 1.1.2 =
* Rotulo da promocao no carrinho passou a ser apenas "Promoção"
* Linha do carrinho nao pode mais aparecer duplicada
* Preco original riscado fica em HTML no display e texto puro no value

= 1.1.1 =
* Atalho "Voltar para as Regras de Descontos" dentro da caixa Publicar

= 1.1.0 =
* Selecao de produto pai com opcao "Aplicar a todas as variacoes"
  - Antes, escolher uma variacao acabava aplicando a regra em todas elas sem avisar
  - Agora e possivel escolher produto inteiro ou variacao por variacao
* Busca de produtos diferencia produto variavel de variacao, mostrando o produto pai
* Aviso quando a extensao esta desligada e ha produto variavel na lista
* Painel mostra preview do calculo com exemplos de 1x, 2x e 3x o pack
* Correcao: publicacao da regra nao era mais bloqueada por validacao do navegador
* Correcao: preco de pack maior que o preco regular nunca e cobrado acima do valor cheio
* Correcao: pacote de 3 por R$100 nao fechava em R$99,99 por arredondamento
* Correcao: 4 unidades em "3 por R$100" cobram R$135,00 (pack + excedente ao preco normal)
* Correcao: campos de produto inexistente ou regra sem produtos nao quebram o carrinho
* Seguranca: regras nao sao mais expostas via REST API
* Seguranca: campos condicionais sem restricao HTML5 (campo invisivel invalido travava o formulario)
* Admin: salvamento treats checkbox desmarcado, tipos e status invalidos sao descartados
* Versionamento visivel no rodape do painel

= 1.0.0 =
* Lançamento inicial
* Três tipos de desconto: Pack Price, Percent, Buy X Get Y
* Controle de período, variações, limites de quantidade
* Badge no produto, preços no carrinho
* Admin intuitivo com campos comentados
* Compatível PHP 7.0+ / WC 3.0+

== Upgrade Notice =

= 1.0.0 =
Primeira versão estável. Requer WooCommerce ativo.

== Credits ==

Desenvolvido por **Anderson Höfelmann**.

== License ==

GPL v2 or later. Veja `LICENSE` para detalhes.