<?php
namespace frontend\models;
use yii\db\ActiveRecord;
use yii2mod\cart\models\CartItemInterface;

/**
 * Created by PhpStorm.
 * User: J. Coelho
 * Date: 12/01/2019
 * Time: 10:23
 */

class ProductModel extends ActiveRecord implements CartItemInterface
{

    public function getPrice()
    {
        return $this->price;
    }

    public function getLabel()
    {
        return $this->name;
    }

    public function getUniqueId()
    {
        return $this->id;
    }
}