<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use kartik\mpdf\Pdf;
use app\Models\Invoice;
use app\Models\InvoiceDescription;
use app\Models\Viecle;
use app\Models\Quotation;
use app\Models\Customer;
use yii\helpers\Url;
use yii\db\Query;
use app\Models\Reciept;
use yii\helpers\ArrayHelper;
use yii\data\ActiveDataProvider;
use app\models\Claim;
use app\models\PaymentStatus;

class ReceiptController extends Controller{
    function num2thai($number){
        $t1 = array("ศูนย์", "หนึ่ง", "สอง", "สาม", "สี่", "ห้า", "หก", "เจ็ด", "แปด", "เก้า");
        $t2 = array("เอ็ด", "ยี่", "สิบ", "ร้อย", "พัน", "หมื่น", "แสน", "ล้าน");
        $zerobahtshow = 0; // ในกรณีที่มีแต่จำนวนสตางค์ เช่น 0.25 หรือ .75 จะให้แสดงคำว่า ศูนย์บาท หรือไม่ 0 = ไม่แสดง, 1 = แสดง
        (string) $number;
        $number = explode(".", $number);
        if(!empty($number[1])){
            if(strlen($number[1]) == 1){
                $number[1] .= "0";
            }else if(strlen($number[1]) > 2){
                if($number[1]{2} < 5){
                    $number[1] = substr($number[1], 0, 2);
                }else{
                    $number[1] = $number[1]{0}.($number[1]{1}+1);
                }
            }
        }

        for($i=0; $i<count($number); $i++){
            $countnum[$i] = strlen($number[$i]);
            if($countnum[$i] <= 7){
                $var[$i][] = $number[$i];
            }else{
                $loopround = ceil($countnum[$i]/6);
                for($j=1; $j<=$loopround; $j++){
                    if($j == 1){
                            $slen = 0;
                        $elen = $countnum[$i]-(($loopround-1)*6);
                    }else{
                        $slen = $countnum[$i]-((($loopround+1)-$j)*6);
                        $elen = 6;
                    }
                    $var[$i][] = substr($number[$i], $slen, $elen);
                }
            }

            $nstring[$i] = "";
            for($k=0; $k<count($var[$i]); $k++){
                if($k > 0) $nstring[$i] .= $t2[7];
                    $val = $var[$i][$k];
                    $tnstring = "";
                    $countval = strlen($val);
                for($l=7; $l>=2; $l--){
                    if($countval >= $l){
                        $v = substr($val, -$l, 1);
                        if($v > 0){
                            if($l == 2 && $v == 1){
                                $tnstring .= $t2[($l)];
                            }elseif($l == 2 && $v == 2){
                                $tnstring .= $t2[1].$t2[($l)];
                            }else{
                                $tnstring .= $t1[$v].$t2[($l)];
                            }
                        }
                    }
                }

                if($countval >= 1){
                    $v = substr($val, -1, 1);
                    if($v > 0){
                        if($v == 1 && $countval > 1 && substr($val, -2, 1) > 0){
                            $tnstring .= $t2[0];
                        }else{
                            $tnstring .= $t1[$v];
                        }
                    }
                }

                $nstring[$i] .= $tnstring;
            }
        }
        $rstring = "";
        if(!empty($nstring[0]) || $zerobahtshow == 1 || empty($nstring[1])){
            if($nstring[0] == "") $nstring[0] = $t1[0];
                $rstring .= $nstring[0]."บาท";
        }
        if(count($number) == 1 || empty($nstring[1])){
            $rstring .= "ถ้วน";
        }else{
            $rstring .= $nstring[1]."สตางค์";
        }
        return $rstring;
    }

    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
            'access' => [
                'class' => \yii\filters\AccessControl::className(),
                'only' => ['create','summary', 'dept'],
                'rules' => [
                    // allow authenticated users
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }


    private function getReceiptNumber($type = null) {
        if($type === 'General') {
            $number = Reciept::find()
                        ->joinWith('invoice')
                        ->where(['YEAR(reciept.date)' => date('Y'), 'MONTH(reciept.date)' => date('m'), 'invoice.type' => 'General'])
                        ->count() + 1;
        }
        else {
             $number = Reciept::find()
                        ->joinWith('invoice')
                        ->where(['YEAR(reciept.date)' => date('Y'), 'MONTH(reciept.date)' => date('m'), 'invoice.type' => null])
                        ->count() + 1;
        }
        $number = str_pad($number, 4, "0", STR_PAD_LEFT);
        $receiptId = $number . "/" . (( date('Y') + 543 ) - 2500);
        
        if ($type === 'General') 
            return $receiptId;
        else
            return "RE-" . $receiptId;
    }

    public function actionReport($iid = null, $dateIndex = null){
        // find date
        $request = Yii::$app->request;
        
        $dateLists = InvoiceDescription::find()->select(['date'])->distinct()->where(['IID' => $iid])->orderBy(['date' => SORT_DESC])->all();

        if( $dateIndex == null)
            $dateIndex = 0;

        $invoice = Invoice::findOne($iid);

        $query = InvoiceDescription::find()->where(['iid' => $iid, 'date' => $dateLists[$dateIndex]]);
        $descriptions = $query->all();

        // update 20170107
        $invoice->total != null ? $total = $invoice->total : $total = $query->sum('price');
        $invoice->total_vat != null ? $vat = $invoice->total_vat : $vat = $total * 0.07;
        $invoice->grand_total != null ? $grandTotal = $invoice->grand_total : $grandTotal = $total + $vat;

        /* Update Reciept Info */
        $reciept = Reciept::find()->where(['IID' => $iid])->one();

        if( count($reciept) == null ){
            $reciept = new Reciept();
            
            $type = $request->get('type');
            $reciept->reciept_id = $this->getReceiptNumber($type);
            $reciept->IID = $invoice->IID;
            $reciept->book_number = date('m') . '/' . ((date('Y') + 543) - 2500);

            $reciept->total = $grandTotal;
            $reciept->date = date('Y-m-d H:i:s');
            $reciept->EID = Yii::$app->user->identity->getId();

            if($reciept->validate() && $reciept->save()){
                // success
                $reciept = Reciept::find()->orderBy(['RID' => SORT_DESC])->one();
            }
            else{
                // failed
                var_dump( $reciept->errors );
                die();
            }    

        }
        
        // update payment status
        $paymentStatus = new PaymentStatus();
        $paymentStatus->RID = $reciept->RID;
        $paymentStatus->CLID = $reciept->invoice['CLID'];
        $paymentStatus->save();
        
        $content = $this->renderPartial('report', [
            'invoice' => $invoice,

            'descriptions' => $descriptions,
            'total' => $total,
            'vat' => $vat,
            'grandTotal' => $grandTotal,
            'thbStr' => $this->num2thai($grandTotal),
        ]);

        // setup kartik\mpdf\Pdf component
        $pdf = new Pdf([
        // set to use core fonts only
        'mode' => Pdf::MODE_UTF8,
        // A4 paper format
        'format' => Pdf::FORMAT_A4,
        // portrait orientation
        'orientation' => Pdf::ORIENT_PORTRAIT,
        // stream to browser inline
        'destination' => Pdf::DEST_BROWSER,
        // your html content input
        'content' => $content,
        // format content from your own css file if needed or use the
        // enhanced bootstrap css built by Krajee for mPDF formatting
        'cssFile' => '@app/web/css/pdf.css',
        // any css to be embedded if required
        //        'cssInline' => '.kv-heading-1{font-size:18px}',
        // set mPDF properties on the fly
        'options' => ['title' => 'ใบเสร็จรับเงิน/ใบกํากับภาษี'],
        // call mPDF methods on the fly
        'methods' => [
            //'SetHeader'=>['Krajee Report Header'],
            // 'SetFooter'=>['หน้า {PAGENO} / {nb}'],    //remove 20161117
            ]
        ]);

        $pdf->configure(array(
            'defaultfooterline' => '0',
            'defaultfooterfontstyle' => 'R',
            'defaultfooterfontsize' => '10',
        ));

        // return the pdf output as per the destination setting
        return $pdf->render();
    }

    public function actionSummaryReport($startDate = null, $endDate = null){


        $receipts = Reciept::find()->where(['between', 'UNIX_TIMESTAMP(date)', strtotime($startDate), strtotime($endDate)])->all();

        $mY_t = 0;
        foreach($receipts as $key => $receipt){
            $mY = date("m-Y", strtotime($receipt->date) ); // key
            if($mY != $mY_t){
               $mY_t = $mY;
               $month[$mY_t]= [];
            }
           array_push( $month[$mY_t], $key );
        }


        $content = $this->renderPartial('summary_report', [
            'receipts' => $receipts,
            'month' => $month,
        ]);

        // setup kartik\mpdf\Pdf component
        $pdf = new Pdf([
        // set to use core fonts only
        'mode' => Pdf::MODE_UTF8,
        // A4 paper format
        'format' => Pdf::FORMAT_A4,
        // portrait orientation
        'orientation' => Pdf::ORIENT_LANDSCAPE,
        // stream to browser inline
        'destination' => Pdf::DEST_BROWSER,
        // your html content input
        'content' => $content,
        // format content from your own css file if needed or use the
        // enhanced bootstrap css built by Krajee for mPDF formatting
        'cssFile' => '@app/web/css/pdf.css',
        // any css to be embedded if required
        //        'cssInline' => '.kv-heading-1{font-size:18px}',
        // set mPDF properties on the fly
        'options' => ['title' => 'ใบเสร็จรับเงิน/ใบกํากับภาษี'],
        // call mPDF methods on the fly
        'methods' => [
            //'SetHeader'=>['Krajee Report Header'],
            'SetFooter'=>['หน้า {PAGENO} / {nb}'],
            ]
        ]);

        $pdf->configure(array(
            'defaultfooterline' => '0',
            'defaultfooterfontstyle' => 'R',
            'defaultfooterfontsize' => '10',
        ));

        // return the pdf output as per the destination setting
        return $pdf->render();
    }

    public function actionSummary($startDate=null, $endDate=null){
        $request = Yii::$app->request;

        // query date (difference date)
        $receiptDates = (new Query)->select(["DATE_FORMAT(date, '%m-%Y') AS dt"])->from('reciept')->distinct()->all();
        $receiptDates = ArrayHelper::getColumn($receiptDates, 'dt');

        // post request/ search by condition
        $receipts = null;
        $month = [];
        if($request->post()){
            $startDate =  date_create( "01-" . $receiptDates[ $request->post('start-date') ] );
            $startDate = date_format($startDate, "Y-m-d");

            $endDate = date_create( "01-" . $receiptDates[ $request->post('end-date') ] );
            date_modify($endDate, 'last day of this month');
            $endDate = date_format($endDate, "Y-m-d");

            $receipts = Reciept::find()->where(['between', 'UNIX_TIMESTAMP(date)', strtotime($startDate), strtotime($endDate)])->all();

            $mY_t = 0;
            foreach($receipts as $key => $receipt){
                $mY = date("m-Y", strtotime($receipt->date) ); // key
                if($mY != $mY_t){
                   $mY_t = $mY;
                   $month[$mY_t]= [];
                }
               array_push( $month[$mY_t], $key );
            }
        }

        return $this->render('summary', [
            'receiptDate' => $receiptDates,
            'receipts' => $receipts,
            'month' => $month,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function actionDept($startDate=null, $endDate=null){
        Yii::$app->formatter->nullDisplay = '-';
        $request = Yii::$app->request;

        // query date (difference date)
        $invocieDates = (new Query)->select(["DATE_FORMAT(date, '%m-%Y') AS dt"])->from('invoice')->distinct()->all();
        $invoiceDates = ArrayHelper::getColumn($invocieDates, 'dt');

        $month = [];
        $dataProvider = null;
        if( $request->post() ){
            $startDate =  date_create( "01-" . $invoiceDates[ $request->post('start-date') ] );
            $startDate = date_format($startDate, "Y-m-d");

            $endDate = date_create( "01-" . $invoiceDates[ $request->post('end-date') ] );
            date_modify($endDate, 'last day of this month');
            $endDate = date_format($endDate, "Y-m-d");

            $dataProvider = new ActiveDataProvider([
                'query' => Invoice::find()->with('reciept')->where(['between', 'UNIX_TIMESTAMP(date)', strtotime($startDate), strtotime($endDate)])->orderBy(['IID' => SORT_DESC]),
                'pagination' => [
                'pageSize' => 20,
                ],
            ]);
        }
        else{
            $dataProvider = new ActiveDataProvider([
                'query' => Claim::find()->with('paymentStatus')->where(['not', ['create_time' => null]])->orderBy(['CLID' => SORT_DESC]),
                'pagination' => [
                'pageSize' => 25,
                ],
            ]);
        }


        return $this->render('dept', [
            'dataProvider' => $dataProvider,
            'invoiceDates' => $invoiceDates,
            'month' => $month,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function actionDeptReport(){
        var_dump( Yii::$app->request->get() );
    }
    
    public function actionMultipleClaim(){
        $invoice = new Invoice();
        $book_number = date('m') . "/" . (( date('Y') + 543 ) - 2500);
        $number = Reciept::find()->where(['YEAR(date)' => date('Y'), 'MONTH(date)' => date('m')])->count();
        $receiptId = ($number + 1) . "/" . (( date('Y') + 543 ) - 2500);
        
        $paymentStatus = PaymentStatus::find()->all();
        $paymentStatusCLID = ArrayHelper::getColumn($paymentStatus, 'CLID');
        $claim = Claim::find()->where(['not in', 'CLID', $paymentStatusCLID])->all();
        
        $insuranceCompany = Customer::find()->where(['type' => 'INSURANCE_COMP'])->all();
        
        return $this->render('multiple_claim',[
            'book_number' => $book_number,
            'receiptId' => $receiptId,
            'invoice' => $invoice,
            'claim' => $claim,
            'insuranceCompany' => $insuranceCompany,
        ]);
    }
    
    public function actionCreateMultiple(){
        $IID = null;
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $request = Yii::$app->request;
        
        // create invoice each claim
        $claims = $request->post('claims');
        $descriptions = $request->post('invoice');
        $CID = $request->post('CID');
        $dt = date('Y-m-d H:i:s');
        
        // invoice
        $invoice = new Invoice();
        $invoice->CID = $CID;
        $invoice->date = $dt;
        $invoice->EID = Yii::$app->user->identity->getId();

        if($invoice->validate() && $invoice->save()){
            $IID = Invoice::find()->orderBy(['IID' => SORT_DESC])->one()['IID'];
            // description
            foreach($descriptions as $description){
                $invoiceDescription = new InvoiceDescription();
                $invoiceDescription->IID = $IID;
                $invoiceDescription->description = $description['list'];
                $invoiceDescription->price = $description['price'];
                $invoiceDescription->date = $dt;

                if($invoiceDescription->validate() && $invoiceDescription->save()){
                }
                else{
                    return $invoiceDescription->errors;
                }
            }    
        }
        else{
            return $invoice->errors;
        }
        
        $receipt = new Reciept();
        $receipt->IID = $IID;
        
        $number = Reciept::find()->where(['YEAR(date)' => date('Y'), 'MONTH(date)' => date('m')])->count() + 1;
        $number = str_pad($number, 4, "0", STR_PAD_LEFT);
        $receiptId = $number . "/" . (( date('Y') + 543 ) - 2500);
        
        $receipt->reciept_id = "RE-" . $receiptId;
        
        $receipt->total = InvoiceDescription::find()->where(['IID' => $IID])->sum('price');
        
        $receipt->IID = $invoice->IID;
        $receipt->book_number = date('m') . '/' . ((date('Y') + 543) - 2500);
        
        $receipt->date = $dt;
        $receipt->EID = Yii::$app->user->identity->getId();
        
        if($receipt->validate() && $receipt->save()){
            //return true;    
            $receipt = Reciept::find()->orderBy(['RID' => SORT_DESC])->one();
        }
        else{
            return $receipt->errors;
        }
        
        if(isset($claims)){
            foreach($claims as $CLID){
                $paymentStatus = new PaymentStatus();
                $paymentStatus->RID = $receipt->RID;
                $paymentStatus->CLID = $CLID;

                if($paymentStatus->validate() && $paymentStatus->save()){

                }
                else{
                    return $paymentStatus->errors;
                }
            }
        }
        return ['status' => true, 'iid' => $IID, 'rid' => $receipt->RID];
    }
    
    public function actionViewMultipleClaim($rid){
        $receipt = Reciept::find()->with(['invoice', 'paymentStatus'])->where(['RID' => $rid])->one();
        
        $dateLists = InvoiceDescription::find()->select(['date'])->distinct()->where(['IID' => $receipt->IID])->orderBy(['date' => SORT_DESC])->all();
        $descriptions = InvoiceDescription::find()->where(['iid' => $receipt->IID, 'date' => $dateLists[0]])->all();
        
        return $this->render('view_multiple_claim', [
            'receipt' => $receipt,
            'descriptions' => $descriptions,
            'lastUpdate' => $dateLists[0],
        ]);
    }
    
    public function actionUpdateMultipleClaim($rid){
        $request = Yii::$app->request;
        $receipt = Reciept::find()->with(['invoice', 'paymentStatus'])->where(['RID' => $rid])->one();
        
        $dateLists = InvoiceDescription::find()->select(['date'])->distinct()->where(['IID' => $receipt->IID])->orderBy(['date' => SORT_DESC])->all();
        $descriptions = InvoiceDescription::find()->where(['iid' => $receipt->IID, 'date' => $dateLists[0]])->all();
        
        if($request->isAjax){
            \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            $descriptions = $request->post('invoice');
            $dt = date('Y-m-d H:i:s');
            foreach($descriptions as $description){
                $invoiceDescription = new InvoiceDescription();
                $invoiceDescription->IID = $receipt->IID;
                $invoiceDescription->description = $description['list'];
                $invoiceDescription->price = $description['price'];
                $invoiceDescription->date = $dt;
                
                if($invoiceDescription->validate() && $invoiceDescription->save()){
                    
                }
                else{
                    return $invoiceDescription->errors;
                }
            }
            return ['status' => true, 'IID' => $receipt->IID];
        }
        return $this->render('update_multiple_claim', [
            'receipt' => $receipt,
            'descriptions' => $descriptions,
            'lastUpdate' => $dateLists[0],
        ]);
    }
    
    public function actionSearch(){
        Yii::$app->formatter->nullDisplay = '-';
        $query = Reciept::find()->with('invoice')->orderBy(['RID' => SORT_DESC]);
        $dataProvider  = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 25,
            ],
        ]);
        return $this->render('search',[
            'dataProvider' => $dataProvider,
        ]);
    }
}
?>
