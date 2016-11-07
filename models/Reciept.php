<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reciept".
 *
 * @property integer $RID
 * @property integer $IID
 * @property string $reciept_id
 * @property string $date
 * @property integer $total
 * @property integer $EID
 *
 * @property Employee $e
 * @property Invoice $i
 */
class Reciept extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'reciept';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['IID'], 'required'],
            [['IID', 'total', 'EID'], 'integer'],
            [['reciept_id'], 'string'],
            [['date'], 'safe'],
            [['EID'], 'exist', 'skipOnError' => true, 'targetClass' => Employee::className(), 'targetAttribute' => ['EID' => 'EID']],
            [['IID'], 'exist', 'skipOnError' => true, 'targetClass' => Invoice::className(), 'targetAttribute' => ['IID' => 'IID']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'RID' => 'Rid',
            'IID' => 'Iid',
            'reciept_id' => 'Reciept ID',
            'date' => 'Date',
            'total' => 'Total',
            'EID' => 'Eid',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getE()
    {
        return $this->hasOne(Employee::className(), ['EID' => 'EID']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getI()
    {
        return $this->hasOne(Invoice::className(), ['IID' => 'IID']);
    }
}