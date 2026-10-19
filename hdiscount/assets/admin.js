/**
 * HDiscount - scripts do painel de administracao.
 *
 * Responsabilidades:
 *  - mostrar/ocultar os campos conforme o tipo de desconto;
 *  - gerar exemplos reais de calculo;
 *  - avisar sobre campos incompletos SEM impedir a publicacao.
 *
 * Importante: este arquivo nunca bloqueia o envio do formulario.
 * A validacao definitiva acontece no PHP, que explica o que falta.
 */
jQuery( document ).ready( function( $ ) {
    'use strict';

    var msg = window.hdiscount_admin || {};

    /**
     * Le um campo numerico, devolvendo null quando vazio ou invalido.
     *
     * @param  {string} sel
     * @return {number|null}
     */
    function numero( sel ) {
        var bruto = $( sel ).val();
        if ( typeof bruto === 'undefined' || bruto === null || bruto === '' ) {
            return null;
        }
        var n = parseFloat( String( bruto ).replace( ',', '.' ) );
        return isNaN( n ) ? null : n;
    }

    /**
     * Alterna a visibilidade dos campos conforme o tipo de desconto.
     */
    function toggleDiscountFields() {
        var type = $( '#hdiscount_type' ).val();

        $( '.hdiscount-field-pack_price' ).toggle( 'pack_price' === type );
        $( '.hdiscount-field-percent' ).toggle( 'percent' === type );
        $( '.hdiscount-field-buy_x_get_y' ).toggle( 'buy_x_get_y' === type );

        renderPreview();
    }

    /**
     * Monta a frase de exemplo e os calculos de exemplo.
     *
     * @return {{ texto: string, exemplos: Array }}
     */
    function montarResumo() {
        var type = $( '#hdiscount_type' ).val();
        var min  = numero( '#hdiscount_min_qty' );

        if ( ! min || min < 1 ) {
            return {
                texto: '',
                exemplos: [],
                erro: msg.i18n_min_invalid || 'A quantidade minima precisa ser 1 ou mais.'
            };
        }

        if ( 'pack_price' === type ) {
            var pack = numero( '#hdiscount_pack_price' );
            if ( ! pack || pack <= 0 ) {
                return {
                    texto: '',
                    exemplos: [],
                    erro: msg.i18n_pack_price_invalid || 'Informe o preco total do pack.'
                };
            }
            return {
                texto: 'A partir de ' + min + ' unidades: ' + brl( pack ) + ' pelo pack.',
                exemplos: [
                    { qtd: min - 1, texto: ( min - 1 ) + ' un = preco normal' },
                    { qtd: min,     texto: min + ' un = <strong>' + brl( pack ) + '</strong>' },
                    { qtd: min * 2, texto: ( min * 2 ) + ' un = <strong>' + brl( pack * 2 ) + '</strong>' },
                    { qtd: min * 2 + 1, texto: ( min * 2 + 1 ) + ' un = <strong>' + brl( pack * 2 ) + ' + 1 unidade normal' }
                ],
                erro: ''
            };
        }

        if ( 'percent' === type ) {
            var pct = numero( '#hdiscount_percent' );
            if ( ! pct || pct <= 0 || pct > 100 ) {
                return {
                    texto: '',
                    exemplos: [],
                    erro: msg.i18n_percent_invalid || 'O desconto precisa ser entre 0 e 100%.'
                };
            }
            return {
                texto: 'A partir de ' + min + ' unidades: ' + brl( pct ) + '% de desconto.',
                exemplos: [
                    { qtd: min - 1, texto: ( min - 1 ) + ' un = preco normal' },
                    { qtd: min,     texto: min + ' un = <strong>' + brl( pct ) + '% OFF</strong>' },
                    { qtd: min * 2, texto: ( min * 2 ) + ' un = <strong>' + brl( pct ) + '% OFF</strong>' }
                ],
                erro: ''
            };
        }

        if ( 'buy_x_get_y' === type ) {
            var buy = numero( '#hdiscount_buy_qty' );
            var get = numero( '#hdiscount_get_qty' );
            if ( ! buy || buy < 2 ) {
                return {
                    texto: '',
                    exemplos: [],
                    erro: msg.i18n_buy_invalid || '"Comprar" precisa ser 2 ou mais.'
                };
            }
            if ( ! get || get < 1 || get >= buy ) {
                return {
                    texto: '',
                    exemplos: [],
                    erro: msg.i18n_get_invalid || '"Pague" precisa ser maior que zero e menor que "Comprar".'
                };
            }
            return {
                texto: 'A partir de ' + buy + ' unidades: paga apenas ' + get + '.',
                exemplos: [
                    { qtd: buy - 1, texto: ( buy - 1 ) + ' un = preco normal' },
                    { qtd: buy,     texto: buy + ' un = paga <strong>' + get + '</strong>' },
                    { qtd: buy * 2, texto: ( buy * 2 ) + ' un = paga <strong>' + ( get * 2 ) + '</strong>' }
                ],
                erro: ''
            };
        }

        return { texto: '', exemplos: [], erro: '' };
    }

    /**
     * Desenha o resumo na tela.
     */
    function renderPreview() {
        var $box      = $( '#hdiscount-preview-box' );
        var $text     = $( '#hdiscount-badge-preview' );
        var $examples = $( '#hdiscount-examples' );
        var $erro     = $( '#hdiscount-preview-error' );

        if ( ! $box.length ) { return; }

        var resumo = montarResumo();

        $text.html( resumo.texto || '' );
        $examples.empty();

        if ( resumo.erro ) {
            $erro.text( resumo.erro ).show();
            $box.addClass( 'hdiscount-preview-box--error' );
        } else {
            $erro.hide().text( '' );
            $box.removeClass( 'hdiscount-preview-box--error' );
        }

        if ( ! resumo.exemplos.length ) { return; }

        $.each( resumo.exemplos, function( i, ex ) {
            $examples.append( $( '<li>' ).html( ex.texto ) );
        } );
    }

    /**
     * Formata numero no padrao brasileiro.
     *
     * @param  {number} v
     * @return {string}
     */
    function brl( v ) {
        var n = parseFloat( v ) || 0;
        return n.toLocaleString( 'pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        } );
    }

    // ---------------------------------------------------------------
    // Eventos
    // ---------------------------------------------------------------

    $( '#hdiscount_type' ).on( 'change', toggleDiscountFields );

    $( '#hdiscount_min_qty, #hdiscount_pack_price, #hdiscount_percent, ' +
       '#hdiscount_buy_qty, #hdiscount_get_qty' )
        .on( 'change keyup', renderPreview );

    toggleDiscountFields();

    /**
     * Confirmacao antes de excluir uma regra.
     */
    $( '#delete-action a, .submitdelete' ).on( 'click', function( e ) {
        if ( ! window.confirm( msg.i18n_confirm_delete || 'Confirma a exclusao desta regra?' ) ) {
            e.preventDefault();
        }
    } );

    /**
     * A data de fim precisa ser depois da data de inicio.
     * Apaga o campo, em vez de bloquear o envio.
     */
    $( '#hdiscount_start_date, #hdiscount_end_date' ).on( 'change', function() {
        var inicio = $( '#hdiscount_start_date' ).val();
        var fim    = $( '#hdiscount_end_date' ).val();

        if ( inicio && fim && inicio >= fim ) {
            window.alert( msg.i18n_invalid_dates || 'A data de fim deve ser posterior a data de inicio.' );
            $( '#hdiscount_end_date' ).val( '' );
        }
    } );

    /**
     * Mantem a quantidade maxima coerente com a minima.
     */
    $( '#hdiscount_max_qty' ).on( 'change', function() {
        var min = numero( '#hdiscount_min_qty' ) || 1;
        var max = numero( '#hdiscount_max_qty' ) || 0;

        if ( max > 0 && max < min ) {
            window.alert( msg.i18n_max_lt_min || 'A quantidade maxima nao pode ser menor que a minima.' );
            $( this ).val( '' );
        }
    } );
} );
/**
 * Modal do guia do HDiscount.
 *
 * Acessibilidade:
 *  - role="dialog" + aria-modal, com rotulo ligado ao titulo;
 *  - foco vai para o modal ao abrir e volta para o botao ao fechar;
 *  - Tab circula dentro do modal;
 *  - Esc e o clique no fundo fecham;
 *  - a rolagem do fundo fica travada enquanto o modal esta aberto.
 */
jQuery( document ).ready( function( $ ) {
    'use strict';

    var $modal = $( '#hdiscount-about-modal' );

    if ( ! $modal.length ) {
        return;
    }

    var $fundo   = $( 'body' );
    var $gatilho = null;
    var FOCAVEIS = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

    function abrir( event ) {
        $gatilho = $( event.currentTarget );

        $modal.prop( 'hidden', false );
        $fundo.addClass( 'hdiscount-modal-open' );

        var $primeiro = $modal.find( FOCAVEIS ).filter( ':visible' ).first();
        if ( $primeiro.length ) {
            $primeiro.trigger( 'focus' );
        } else {
            $modal.find( '.hdiscount-modal__box' ).attr( 'tabindex', '-1' ).trigger( 'focus' );
        }
    }

    function fechar() {
        $modal.prop( 'hidden', true );
        $fundo.removeClass( 'hdiscount-modal-open' );

        if ( $gatilho && $gatilho.length ) {
            $gatilho.trigger( 'focus' );
        }
        $gatilho = null;
    }

    // Abre pelo botao (delegado: funciona tambem em conteúdo ajax).
    $( document ).on( 'click', '[data-hdiscount-about]', abrir );

    // Fecha por clique no X ou no fundo.
    $( document ).on( 'click', '[data-hdiscount-about-close]', fechar );

    // Esc fecha.
    $( document ).on( 'keyup', function( e ) {
        if ( 27 === e.key && ! $modal.prop( 'hidden' ) ) {
            fechar();
        }
    } );

    // Mantem o foco dentro do modal enquanto ele estiver aberto.
    $( document ).on( 'keydown', function( e ) {
        if ( 9 !== e.key || $modal.prop( 'hidden' ) ) {
            return;
        }

        var $itens = $modal.find( FOCAVEIS ).filter( ':visible' );
        if ( ! $itens.length ) {
            return;
        }

        var primeiro = $itens.first()[0];
        var ultimo   = $itens.last()[0];

        if ( e.shiftKey && document.activeElement === primeiro ) {
            e.preventDefault();
            ultimo.focus();
        } else if ( ! e.shiftKey && document.activeElement === ultimo ) {
            e.preventDefault();
            primeiro.focus();
        }
    } );
} );
