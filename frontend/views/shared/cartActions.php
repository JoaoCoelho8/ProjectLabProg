<?php

use frontend\helpers\LinkHelper;
use yii\helpers\Url;

// ADD TO CART
$ajaxUrl = '/cart/addtocart';
$addToCart = <<<EOT
    function addedToCartGlobal(html, total) {
        // Replace Mini Cart
        $('#cart').html(html);
        // Animation to Open/Select/Hover Mini Cart
        // $('.cart div.dropdown').addClass('open');
        // $('.cart-xs').addClass('open');
        // Move to Top
        $('html, body').animate({scrollTop: $("#cart").offset().top}, 500);
        // Update Quantity
        var cartQuant = $('.cartQuant');
        if (cartQuant.length > 0) {
            cartQuant.html(total);
            cartQuant.show();
        }
    }

    // Validates stock and sends to server
    $(".addNewProduct").click(function (e) {
        var cartProduct = $(this).closest('.infoProduct').find('input').serializeArray();

        $.getJSON("{$ajaxUrl}", cartProduct, function (response) {
            if (response.status == "ok") {
                //addedEcommerceAnalytics();
                addedToCartGlobal(response.html, response.total);
            } else {
                alert(response.msg);
            }
        });

    });
EOT;

$this->registerJs($addToCart, $this::POS_END);

// REMOVE FROM CART
$confirmRemoveProductAjax = Yii::t('app', 'Remover produto do Carrinho?');
$actionUrlAjax = '/cart/remove';

$lang = Yii::$app->language;
$removeProductMiniCart = <<<EOT
    $(document).on('click', '.removeThisProductMinicart', function(e) {
        e.stopPropagation();
        e.preventDefault();
        if (confirm("{$confirmRemoveProductAjax}")) {
            var removeProduct = $('.removeProductFromMinicart');
            var index = $(this).data('index');
            var actionUrl = "{$actionUrlAjax}/" + index + "?referer=true";
            removeProduct.prop('action', actionUrl);
            removeProduct.submit();
        }
    });
EOT;

$this->registerJs($removeProductMiniCart, $this::POS_END);
