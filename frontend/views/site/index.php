<?php



/* @var $this yii\web\View */


use yii\bootstrap\Carousel;
use yii\bootstrap\Html;

$this->title = 'Frontend';
?>

<div class="site-index">

    <div class="carousel-inner" role="listbox" style=" width:1000px; height: auto !important; margin: auto">
    <?php

    echo Carousel::widget([


        'items' => [
            ['content'=>  Html::img('@web/images/banho1.jpg')],
            ['content'=>  Html::img('@web/images/banho2.jpg')],
            ['content'=>  Html::img('@web/images/banho3.jpg')],
            ['content'=>  Html::img('@web/images/cozinha1.jpg')],
            ['content'=>  Html::img('@web/images/cozinha2.jpg')],
            ['content'=>  Html::img('@web/images/cozinha3.jpg')],
            ['content'=>  Html::img('@web/images/cozinha4.jpg')],
            ['content'=>  Html::img('@web/images/cozinha5.jpg')],
            ['content'=>  Html::img('@web/images/quarto1.jpg')],
            ['content'=>  Html::img('@web/images/quarto2.jpg')],
            ['content'=>  Html::img('@web/images/quarto3.jpg')],
            ['content'=>  Html::img('@web/images/quarto4.jpg')],
            ['content'=>  Html::img('@web/images/quarto5.jpg')],
            ['content'=>  Html::img('@web/images/quarto6.jpg')],
            ['content'=>  Html::img('@web/images/quarto7.jpg')],
            ['content'=>  Html::img('@web/images/sala1.jpg')],
            ['content'=>  Html::img('@web/images/sala2.jpg')],
            ['content'=>  Html::img('@web/images/sala3.jpg')],
            ['content'=>  Html::img('@web/images/sala4.jpg')],
        ],



    ]);

    ?>

</div>
</div>
