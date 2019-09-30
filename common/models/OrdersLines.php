<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "orders_lines".
 *
 * @property int $id
 * @property int $id_order
 * @property int $id_product
 * @property int $quantity
 * @property string $product_name
 * @property string $product_price
 *
 * @property Orders $order
 * @property Product $product
 */
class OrdersLines extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'orders_lines';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_order', 'id_product', 'quantity', 'product_name', 'product_price'], 'required'],
            [['id_order', 'id_product', 'quantity'], 'integer'],
            [['product_price'], 'number'],
            [['product_name'], 'string', 'max' => 255],
            [['id_order'], 'exist', 'skipOnError' => true, 'targetClass' => Orders::className(), 'targetAttribute' => ['id_order' => 'id']],
            [['id_product'], 'exist', 'skipOnError' => true, 'targetClass' => Product::className(), 'targetAttribute' => ['id_product' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_order' => 'Id Order',
            'id_product' => 'Id Product',
            'quantity' => 'Quantity',
            'product_name' => 'Product Name',
            'product_price' => 'Product Price',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Orders::className(), ['id' => 'id_order']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getProduct()
    {
        return $this->hasOne(Product::className(), ['id' => 'id_product']);
    }
}
