# Changelog

Todas as mudancas do HDiscount.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e o versionamento segue [SemVer](https://semver.org/lang/pt-BR/).

## [1.2.6] - 2026-10-18

- Escolher uma variacao agora adiciona o PRODUTO PAI na lista, quando a opcao "Aplicar a todas as variacoes" esta ligada - Antes entrava so a variacao e o produto pai nao aparecia na lista
- A busca mostra o aviso "inclui todas as variacoes" nas variacoes

## [1.2.5] - 2026-10-18

- Correcao: quando a mesma regra valia para mais de um produto no carrinho, o desconto era sobrescrito em vez de somado - Ex: BLUSA 0001 (3 un) e BLUSA 0002 (3 un) com "3 por R$100" davam desconto de R$5,00 em vez de R$10,00

## [1.2.4] - 2026-10-18

- Correcao: ao abrir uma regra ja salva, os produtos apareciam sem o nome (so a etiqueta "variavel"). O redesenho da lista agrupada depende de atributos que nao estavam no HTML

## [1.2.3] - 2026-10-18

- A lista de produtos agora mostra o produto pai primeiro e as variacoes dele logo abaixo, recuadas e ligadas por uma linha
- Correcao: ao reabrir uma regra, apenas o primeiro produto era listado (os IDs gravados sao separados por virgula e lidos como tal)

## [1.2.2] - 2026-10-18

- A linha de desconto no total passou a usar o nome da regra: "Desconto (BLUSA Pack Inverno)   -R$ 15,00"
- Se a regra nao tiver nome, mostra a descricao dela (ex: "Leve 3 por R$ 100,00")

## [1.2.1] - 2026-10-18

- O desconto agora aparece apenas no TOTAL do carrinho, como um cupom - Os produtos ficam com o preco normal, sem preco medio e sem valores riscados - No total aparece uma linha "Promocao: Leve 3 por R\$ 100,00 (5 un)" com o valor descontado
- Revertida a divisao das linhas do carrinho: cada variacao continua na sua linha
- Total continua correto: 5 un x R\$35,00 = R\$175,00 - R\$5,00 = R\$170,00

## [1.2.0] - 2026-10-18

- O carrinho agora separa as linhas: unidades do pack com o preco do pack, e unidades avulsas com o preco normal - Antes: "2 x R$34,00" (preco medio, confuso) - Agora: "3 x R$33,33 = R$100,00" (pack) e "1 x R$35,00" (avulsa)
- As partes voltam a se juntar antes de cada calculo, entao mexer na quantidade no carrinho continua funcionando
- Se a promocao terminar ou for desativada, o carrinho volta a uma linha so e com o preco normal

## [1.1.6] - 2026-10-18

- A conta do desconto aparece uma unica vez (por produto), em vez de repetir em cada variacao, o que sugeria um pack por linha
- A conta agora mostra o total final, explicando o preco unitario medio

## [1.1.5] - 2026-10-18

- Correcao: o preco original deixava de ser lido de volta do carrinho, o que contaminava a conta permanentemente em quem ja tinha usado a 1.1.4 - Sintoma: produto de R$35,00 aparecia como R$34,00 e o total fechava em R$134,00 em vez de R$135,00
- Dados do HDiscount gravados na sessao sao limpos ao carregar o carrinho, entao o preco cadastrado volta a valer sem intervention manual

## [1.1.4] - 2026-10-18

- O carrinho agora mostra a conta do desconto, evitando que o preco unitario medio (ex: R$34,00 em 5 unidades de "3 por R$100") pareca erro de preco - 3 un: "1 pack de 3 por R$ 100,00" - 5 un: "1 pack de 3 por R$ 100,00 + 2 avulsas a R$ 35,00"

## [1.1.3] - 2026-10-18

- Correcao critica: o desconto se aplicava em cima dele mesmo a cada recalculo do carrinho (o WordPress recalcula varias vezes por request), haciendo o total cair de R$135,00 para R$133,75 e depois R$133,44
- Correcao: ao baixar a quantidade e perder a promocao, o preco voltava ao valor rebaixado em vez do preco normal

## [1.1.2] - 2026-10-18

- Rotulo da promocao no carrinho passou a ser apenas "Promoção"
- Linha do carrinho nao pode mais aparecer duplicada
- Preco original riscado fica em HTML no display e texto puro no value

## [1.1.1] - 2026-10-18

- Atalho "Voltar para as Regras de Descontos" dentro da caixa Publicar

## [1.1.0] - 2026-10-18

- Selecao de produto pai com opcao "Aplicar a todas as variacoes" - Antes, escolher uma variacao acabava aplicando a regra em todas elas sem avisar - Agora e possivel escolher produto inteiro ou variacao por variacao
- Busca de produtos diferencia produto variavel de variacao, mostrando o produto pai
- Aviso quando a extensao esta desligada e ha produto variavel na lista
- Painel mostra preview do calculo com exemplos de 1x, 2x e 3x o pack
- Correcao: publicacao da regra nao era mais bloqueada por validacao do navegador
- Correcao: preco de pack maior que o preco regular nunca e cobrado acima do valor cheio
- Correcao: pacote de 3 por R$100 nao fechava em R$99,99 por arredondamento
- Correcao: 4 unidades em "3 por R$100" cobram R$135,00 (pack + excedente ao preco normal)
- Correcao: campos de produto inexistente ou regra sem produtos nao quebram o carrinho
- Seguranca: regras nao sao mais expostas via REST API
- Seguranca: campos condicionais sem restricao HTML5 (campo invisivel invalido travava o formulario)
- Admin: salvamento treats checkbox desmarcado, tipos e status invalidos sao descartados
- Versionamento visivel no rodape do painel

## [1.0.0] - 2026-10-18

- Lançamento inicial
- Três tipos de desconto: Pack Price, Percent, Buy X Get Y
- Controle de período, variações, limites de quantidade
- Badge no produto, preços no carrinho
- Admin intuitivo com campos comentados
- Compatível PHP 7.0+ / WC 3.0+ == Upgrade Notice =

## [1.0.0] - 2026-10-18


[1.2.6]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.2.6
[1.2.5]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.2.5
[1.2.4]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.2.4
[1.2.3]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.2.3
[1.2.2]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.2.2
[1.2.1]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.2.1
[1.2.0]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.2.0
[1.1.6]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.1.6
[1.1.5]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.1.5
[1.1.4]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.1.4
[1.1.3]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.1.3
[1.1.2]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.1.2
[1.1.1]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.1.1
[1.1.0]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.1.0
[1.0.0]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.0.0
[1.0.0]: https://github.com/SEU-USUARIO/hdiscount/releases/tag/v1.0.0
