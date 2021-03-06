<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model app\models\Description */

$this->title = 'Update Description: ' . $model->DID;
$this->params['breadcrumbs'][] = ['label' => 'Descriptions', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->DID, 'url' => ['view', 'id' => $model->DID]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="description-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
