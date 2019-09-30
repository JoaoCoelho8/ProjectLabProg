<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\ProductSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Produtos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="product-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?php $form = ActiveForm::begin(['id' => 'product-form']); ?>

    <?= $form->field($searchForm, 'pesquisa')->textInput(['autofocus' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton('Pesquisar', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
    </div>

    <?php ActiveForm::end(); ?>

    <?php
    foreach ($products as $product){ ?>
        <div class="column" style="background-color:white; border: double; alignment: center">
            <img style="width: 200px; height: auto;" src="<?= Yii::getAlias('@imageurl').'/'.$product->image; ?>">
            <h2>
            <a href="/product/view?id=<?= $product->id?>"><?= $product->name ?></a>
            </h2>
        </div>

    <?php } ?>


</div>
