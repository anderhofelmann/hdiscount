#!/usr/bin/env bash
#
# Gera o ZIP de distribuicao do HDiscount.
#
#   ./bin/build-zip.sh
#
# O ZIP contem apenas a pasta hdiscount/, sem testes nem arquivos de
# desenvolvimento. E o mesmo arquivo enviado ao WordPress.
#
set -euo pipefail

RAIZ="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
NOME="hdiscount"
SAIDA="$RAIZ/$NOME.zip"

cd "$RAIZ"

if [ ! -d "$NOME" ]; then
    echo "erro: pasta $NOME nao encontrada em $RAIZ" >&2
    exit 1
fi

# Confere a sintaxe antes de empacotar.
while IFS= read -r arquivo; do
    php -l "$arquivo" >/dev/null || {
        echo "erro: erro de sintaxe em $arquivo" >&2
        exit 1
    }
done < <(find "$NOME" -name '*.php')

VERSAO="$(grep -m1 -oP '^\s*\*\s*Version:\s*\K[0-9.]+' "$NOME/hdiscount.php" || echo 'desconhecida')"

rm -f "$SAIDA"
zip -qr "$SAIDA" "$NOME" -x '*.DS_Store' '*/node_modules/*' '*/.git/*'

echo "ZIP gerado: $SAIDA"
echo "Versao: $VERSAO"