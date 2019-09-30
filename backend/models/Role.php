<?php

namespace backend\models;

use Yii;
use common\models\User;
/**
 * This is the model class for table "role".
 *
 * @property int $id
 * @property string $role_name
 * @property int $role_value
 */
class Role extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'role';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['role_name', 'role_value'], 'required'],
            [['role_value'], 'integer'],
            [['role_name'], 'string', 'max' => 45],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'role_name' => 'Role Name',
            'role_value' => 'Role Value',
        ];
    }

    public function getUsers()
    {
        return $this->hasMany(User::className(), ['role_id' => 'id']);
    }

    /**
     * get role relationship
     *
     */

    public function getRole()
    {
        return $this->hasOne(Role::className(), ['id' => 'role_id']);
    }

    /**
     * get role name
     *
     */

    public function getRoleName()
    {
        return $this->role ? $this->role->role_name : '- no role -';
    }

    /**
     * get list of roles for dropdown
     */

    public static function getRoleList()
    {
        $droptions = Role::find()->asArray()->all();
        return ArrayHelper::map($droptions, 'id', 'role_name');
    }
}
