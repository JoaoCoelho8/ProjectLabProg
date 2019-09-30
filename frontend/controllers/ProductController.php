<?php

namespace frontend\controllers;

use common\models\OrdersLines;
use frontend\models\ProductForm;
use frontend\models\searchForm;
use Yii;
use common\models\Product;
use common\models\search\ProductSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use frontend\models\ProductModel;
/**
 * ProductController implements the CRUD actions for Product model.
 */
class ProductController extends Controller{

    /*public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'buy' => ['post'],
                ],
            ],
        ];
    }
*/
    public function actionIndex()
    {
        $searchModel = new ProductSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);


        $pesquisa= new searchForm();
       // print_r(Yii::$app->request->post());
        //die();
        $search=Yii::$app->request->post('searchForm')['pesquisa'];
        if($search){

            $products= Product::find()->where(['like', 'name', $search])->all();
            //print_r($products);
            //die();
        }else{
            $products= Product::find()->all();
        }
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'products' => $products,
            'searchForm'=> $pesquisa
        ]);
    }

    public function actionView($id)
    {
        $modelform = new ProductForm();
        $modelform->quantity=1;
        return $this->render('view', [
            'model' => $this->findModel($id),
            'linha' => 1,
        'modelform'=>$modelform]);
    }

    protected function findModel($id)
    {
        if (($model = Product::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }

    public function actionBuy($id){
        //print_r(Yii::$app->request->post('ProductForm'));
        //die();
        //$id=Yii::$app->request->post('id');
        $model= $this->findModel($id);
        $quantity=Yii::$app->request->post('ProductForm')['quantity'];

        if($model->quantity>=$quantity){




            // access the cart from "cart" subcomponent
            $cart = \Yii::$app->cart;

// Product is an AR model implementing CartProductInterface
            $product = Product::findOne($id);

            $model->quantity=$quantity;
// add an item to the cart
            $cart->add($model);
            $model= $this->findModel($id);
          //  $model->quantity= $quantity['quantity'];
            //$model->save();
            Yii::$app->session->setFlash('successo', "Success");
        }else{
            Yii::$app->session->setFlash('erro', "error");
        }
        $modelform = new ProductForm();
        return $this->render('view', ['model'=>$model, 'modelform'=>$modelform]);
    }
}