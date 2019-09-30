<?php
use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\widgets\ActiveForm;


$this->title = 'Informação da encomenda:';
?>

    <h1><?= Html::encode($this->title) ?></h1>

<?= DetailView::widget([
    'model' => $order,
    'attributes' => [
        'id',
        'client_id',
        ['attribute'=>'total_price',
            'format'=>'html',
            'value'=>function ($order){
                return \common\helpers\FormatterHelper::displayNumber($order->total_price);
            }],
    ],
]) ?>