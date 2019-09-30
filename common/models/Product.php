<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;
use yii2mod\cart\models\CartItemInterface;

/**
 * This is the model class for table "product".
 *
 * @property int $id
 * @property string $name
 * @property string $location
 * @property string $image
 * @property string $description
 * @property int $quantity
 */
class Product extends ActiveRecord implements CartItemInterface
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


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'quantity' ], 'required'],
            [['quantity'], 'integer'],
            [['price'], 'number'],
            [['name',  'image', 'description'], 'string', 'max' => 255],
            [['location'], 'string', 'max' => 50],

        ];
    }

    
    public function upload()
    {
        if ($this->validate()) {
            $this->image->saveAs('uploads/' . $this->image->baseName . '.' . $this->image->extension);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Nome',
            'location' => 'Localização',
            'image' => 'Imagem',
            'description' => 'Descrição',
            'quantity' => 'Quantidade',
            'price' => 'Preço'
        ];
    }
}
