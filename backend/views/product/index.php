<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel backend\models\ProductSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Produtos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="product-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Criar Produto', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
           // ['class' => 'yii\grid\SerialColumn'],

            //'id',
            ['attribute'=>'name',
                'contentOptions' => ['style' => 'width:200px; white-space: normal;'],
            ],
            ['attribute'=>'location',
                'contentOptions' => ['style' => 'width:100px; white-space: normal;'],
            ],
            ['attribute'=>'description',
                'contentOptions' => ['style' => 'width:275px; white-space: normal;'],
            ],
            ['attribute'=>'image',
                'format'=>['image', ['width'=>200, 'height'=>200]],
                'value'=>function($model){
                return Yii::getAlias('@imageurl').'/'.$model->image;
                }
                ],

            ['attribute'=>'quantity',
                'contentOptions' => ['style' => 'width:10px; white-space: normal;'],
            ],
            ['attribute'=>'price',
                'contentOptions' => ['style' => 'width:100px; white-space: normal;'],
                'format'=>'html',
                'value'=>function ($model){
                    return \common\helpers\FormatterHelper::displayNumber($model->price);
                }],
            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>
</div>
