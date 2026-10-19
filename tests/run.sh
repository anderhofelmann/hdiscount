#!/usr/bin/env bash
#
# HDiscount - verificacao completa antes de publicar.
#
#   ./tests/run.sh
#
set -u

RAIZ="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
PLUGIN="$RAIZ/hdiscount"
FALHAS=0

echo "=============================================================="
echo " HDiscount - verificacao completa"
echo "=============================================================="
echo

# 1) Sintaxe de todos os arquivos PHP
echo "[1/8] Sintaxe PHP"
while IFS= read -r f; do
    if ! php -l "$f" >/dev/null 2>&1; then
        echo "  FALHA  ${f#$RAIZ/}"
        php -l "$f" 2>&1 | sed 's/^/         /'
        FALHAS=$((FALHAS+1))
    else
        echo "  ok     ${f#$RAIZ/}"
    fi
done < <(find "$PLUGIN" -name '*.php' | sort)
echo

# 2) Ausencia de sintaxe exclusiva do PHP 8
echo "[2/8] Compatibilidade com PHP 7.0"
ACHADO=0
while IFS= read -r linha; do
    echo "  FALHA  $linha"
    ACHADO=1
    FALHAS=$((FALHAS+1))
done < <(grep -rnE '\?\?=|\bstr_contains\b|\bstr_starts_with\b|\bstr_ends_with\b|\bmatch[[:space:]]*\(|\bfn[[:space:]]*\(|\breadonly\b|public (int|string|bool|float) \$' \
    --include='*.php' "$PLUGIN" 2>/dev/null)
[ $ACHADO -eq 0 ] && echo "  ok     nenhuma sintaxe exclusiva do PHP 8"
echo

# 3) Referencias a funcoes internas do PHP removidas
echo "[3/8] Funcoes PHP obsoletas"
ACHADO=0
while IFS= read -r linha; do
    echo "  FALHA  $linha"
    ACHADO=1
    FALHAS=$((FALHAS+1))
done < <(grep -rnE '(^|[^>[:alnum:]_])(each|create_function|money_format|ereg|mysql_query)[[:space:]]*\(' \
    --include='*.php' "$PLUGIN" 2>/dev/null | grep -vF '.each(')
[ $ACHADO -eq 0 ] && echo "  ok     nenhuma funcao removida"
echo

# 4) Suite de testes
echo "[4/8] Testes de carrinho"
if php "$RAIZ/tests/test-cart.php" >/tmp/hd-cart.log 2>&1; then
    tail -1 /tmp/hd-cart.log | sed 's/^/  /'
else
    grep -E 'FALHA|RESULTADO' /tmp/hd-cart.log | sed 's/^/  /'
    FALHAS=$((FALHAS+1))
fi
echo

# 5) Testes de inicializacao
echo "[5/8] Testes de inicializacao"
if php "$RAIZ/tests/test-bootstrap.php" >/tmp/hd-boot.log 2>&1; then
    tail -1 /tmp/hd-boot.log | sed 's/^/  /'
else
    grep -E 'FALHA|RESULTADO' /tmp/hd-boot.log | sed 's/^/  /'
    FALHAS=$((FALHAS+1))
fi
echo

# 6) Testes do painel
echo "[6/8] Testes do painel"
if php "$RAIZ/tests/test-admin.php" >/tmp/hd-admin.log 2>&1; then
    tail -1 /tmp/hd-admin.log | sed 's/^/  /'
else
    grep -E 'FALHA|RESULTADO' /tmp/hd-admin.log | sed 's/^/  /'
    FALHAS=$((FALHAS+1))
fi
echo

# 7) Testes do atualizador
echo "[7/8] Testes do atualizador"
if php "$RAIZ/tests/test-updater.php" >/tmp/hd-upd.log 2>&1; then
    tail -1 /tmp/hd-upd.log | sed 's/^/  /'
else
    grep -E 'FALHA|RESULTADO' /tmp/hd-upd.log | sed 's/^/  /'
    FALHAS=$((FALHAS+1))
fi
echo

# 8) Verificacoes de seguranca
echo "[8/8] Seguranca"
if php "$RAIZ/tests/test-security.php" >/tmp/hd-sec.log 2>&1; then
    tail -1 /tmp/hd-sec.log | sed 's/^/  /'
else
    grep -E 'FALHA|RESULTADO' /tmp/hd-sec.log | sed 's/^/  /'
    FALHAS=$((FALHAS+1))
fi
echo

echo "=============================================================="
if [ $FALHAS -eq 0 ]; then
    echo " TUDO OK - seguro para publicar"
else
    echo " $FALHAS problema(s) encontrado(s)"
fi
echo "=============================================================="
exit $FALHAS