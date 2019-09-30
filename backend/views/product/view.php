<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model backend\models\Product */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Produtos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="product-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Gerar Código de Barras', ['generate-barcode', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Atualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Apagar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Tem a certeza?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'name',
            ['attribute'=>'price',
                'format'=>'html',
                'value'=>function ($model){
                    return \common\helpers\FormatterHelper::displayNumber($model->price);
                }],
            'location',
            [
                'attribute'=>'image',
                'value'=>Yii::getAlias('@imageurl').'/'.$model->image,
                'format'=>['image', ['width'=>200, 'height'=>200]]
            ],
            'description',
            'quantity',
        ],
    ]) ?>

</div>
