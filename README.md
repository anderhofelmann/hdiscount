# HDiscount

Plugin de WooCommerce para criar **regras de desconto por quantidade** — o famoso "leve X por R$ Y".

Feito para lojas que vendem em pack: um produto de R$ 35,00 que, comprando 3, sai por R$ 100,00.

**Desenvolvido por Anderson Höfelmann.**

---

## O que ele faz

| Recurso | Exemplo |
|---|---|
| **Preço fixo do pack** | Leve 3 por R$ 100,00 |
| **Desconto percentual** | 20% OFF a partir de 3 unidades |
| **Compre X leve Y** | Compre 4, pague 3 |
| **Período de vigência** | De 25/12 às 00:00 até 31/12 às 23:59 |
| **Teto de quantidade** | Mín 3, máx 3: comprando 4, só 3 entram no pack |
| **Variações** | Aplicar ao produto inteiro, ou cor por cor |

### Como o desconto aparece

Os produtos mantêm o **preço normal**. O desconto entra só no total, como um cupom:

```
Subtotal                R$ 210,00
Desconto (Pack Inverno) -R$ 10,00
Total                   R$ 200,00
```

Isso evita os preços "médios" que deixa o cliente confuso (ex.: "2 × R$ 34,00") e mantém cada variação na sua linha.

### Regras de arredondamento

Com "3 por R$ 100" e 5 unidades (2 + 2 + 1 em cores diferentes):

| Quantidade | Total |
|---|---|
| 2 | R$ 70,00 (sem promoção) |
| 3 | R$ 100,00 |
| 4 | R$ 135,00 (1 pack + 1 avulsa) |
| 5 | R$ 170,00 (1 pack + 2 avulsas) |
| 6 | R$ 200,00 (2 packs) |
| 9 | R$ 300,00 (3 packs) |

 packs fechados + excedentes pelo preço normal. Descontos de vários produtos somam numa linha só.

## Instalação

1. Baixe o [ZIP mais recente](https://github.com/SEU-USUARIO/hdiscount/releases)
2. **Plugins → Adicionar Novo → Enviar Plugin**
3. Instale, ative e acesse **WooCommerce → HDiscount**

Requer WooCommerce ativo, PHP 7.0+ e WordPress 5.0+.

## Como usar

1. Vá em **WooCommerce → HDiscount → Adicionar Nova Regra**
2. Dê um **nome** à regra (aparece para o cliente no total: "Desconto (Pack Inverno)")
3. Escolha o **tipo** de desconto e preencha os valores
4. Busque os **produtos** — ao escolher uma variação com a extensão ligada, entra o produto pai
5. Ajuste o **período** e publique

## Arquitetura

```
hdiscount/            plugin distribuível (é isso que vira ZIP)
├── hdiscount.php     bootstrap, verificação de dependências
├── includes/
│   ├── class-hdiscount-cart-logic.php   cálculo e taxas negativas
│   ├── class-hdiscount-admin.php        CPT, meta boxes, salvamento
│   ├── class-hdiscount-ajax.php         busca de produtos
│   ├── class-hdiscount-version.php      versão e avisos
│   └── class-hdiscount-autoloader.php
└── assets/            JS e CSS

tests/                suíte de testes (não vai para o ZIP)
bin/build-zip.sh      gera o ZIP de distribuição
```

O plugin **verifica dependências antes de carregar** e envolve cada classe em `try/catch`. Se algo falhar, ele mostra um aviso no admin em vez de derrubar o site.

## Desenvolvimento

```bash
# Rodar tudo: sintaxe, compatibilidade PHP 7 e testes
./tests/run.sh

# Só os testes do carrinho
php tests/test-cart.php

# Gerar o ZIP de distribuição
./bin/build-zip.sh
```

Os testes rodam contra um simulador de WordPress/WooCommerce (`tests/wp-mock.php`), sem precisar de uma instalação real. São ~250 verificações cobrindo cálculo, preço de referência, arredondamento, sessão e o HTML do painel.

Antes de publicar, **teste num ambiente de homologação** — a suíte automatizada não substitui um WooCommerce de verdade.

## Contribuições

Issues e pull requests são bem-vindos. Correções de cálculo e de compatibilidade têm prioridade.

## Licença

GPL-2.0-or-later. Veja [LICENSE](hdiscount/LICENSE).