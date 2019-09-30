
<?php

use yii\helpers\Html;
/**
 * Created by PhpStorm.
 * User: J. Coelho
 * Date: 12/01/2019
 * Time: 11:05
 */

 echo \yii2mod\cart\widgets\CartGrid::widget([
    // Some widget property maybe need to change.
    'cartColumns' => [
        //'id',
        'name',
        'quantity',
        ['attribute'=>'price',
            'format'=>'html',
            'value'=>function ($model){
                return \common\helpers\FormatterHelper::displayNumber($model->price);
            }],
    ]
]);
?>

  <div class="form-group">
        <?= Html::a('Checkout', ['/cart/checkout'], ['class' => 'btn btn-primary']) ?>
    </div>

