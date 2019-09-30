<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use yii\widgets\ActiveForm;


/* @var $this yii\web\View */
/* @var $model backend\models\Product */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Produtos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="product-view">

    <h1><?= Html::encode($this->title) ?></h1>






    <?php $form = ActiveForm::begin(['action'=>['product/buy?id='.$model->id], 'id' => 'product-form']); ?>

    <?= $form->field($modelform, 'quantity')->textInput(['type'=>'number','autofocus' => true]) ?>

    <?= $form->field($modelform, 'id')->hiddenInput(['value' => $model->id])->label(false) ?>

    <div class="form-group">
        <?= Html::submitButton('Adicionar ao carrinho', ['class' => 'btn btn-primary', 'name' => 'login-button'], ['buy', 'id' => $model->id]) ?>
    </div>

    <?php ActiveForm::end(); ?>

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
