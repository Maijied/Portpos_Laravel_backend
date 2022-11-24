<?php

namespace App\Http\Controllers;

use App\Helper\ApiHelper;
use App\Models\OrderManagement;
use App\Models\PaymentInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Auth;
use Validator;

class OrderManagementController extends Controller
{
    public function getOrder()
    {
        try {
            return ApiHelper::jsonResponse('', 'OK', 200, ['data'=> PaymentInvoice::whereNotIn('status',['REFUND'])->with("oderDetails")->get()] );
        } catch (\Exception $e) {
            Log::info($e);
            return ApiHelper::jsonResponse('Something went wrong!', 'Not Found', 404, NULL);
        }
    }
    public function getRefundRequestData()
    {
        try {
            return ApiHelper::jsonResponse('', 'OK', 200, ['data'=> PaymentInvoice::where('status','REFUND')->with("oderDetails")->get()] );
        } catch (\Exception $e) {
            Log::info($e);
            return ApiHelper::jsonResponse('Something went wrong!', 'Not Found', 404, NULL);
        }
    }
    public function createOrder(Request $request)
    {
        //Validate Data
        $validation = Validator::make($request->all(), [
            'name'=>'required',
            'email'=>'required|email',
            'phone'=>'required|size:11',
            'street'=>'required',
            'city'=>'required',
            'state'=>'required',
            'zipcode'=>'required',
            'amount'=>'required',
            'product_name'=>'required',
            'product_details'=>'required',
        ]);
        if ($validation->fails()) {
            \Log::info($validation->errors());
            return ApiHelper::jsonResponse('All fields are required!', 'Not Found', 404, NULL);
        }
        $token = "Bearer ". base64_encode(env('PortPos_AppKey').":".md5(env('PortPos_SecretKey').time()));
        $orderCollection = ['order' => ['amount' => (float) $request->amount,
                                    'currency' => 'BDT',
                                    'redirect_url' => 'http://localhost:4200',
                                    'ipn_url' => 'http://localhost:4200/order-management',
                                    'reference' => 'Maizied'],
                          'product' => ['name' => $request->product_name,
                                        'description' => $request->product_details],
                          'billing' => ['customer' =>
                                             ['name' => $request->name,
                                              'email' => $request->email,
                                              'phone' => $request->phone,
                                              'address' => ['street' => $request->street,
                                                            'city' => $request->city,
                                                            'state' => $request->state,
                                                            'zipcode' => $request->zipcode,
                                                            'country' => 'BD']]
                          ]];
        $orderCollectionObject = json_encode($orderCollection);
        //Generate Invoice
        $portPosInvoiceLink = "https://api-sandbox.portwallet.com/payment/v2/invoice";
        $fetchInvoice = curl_init($portPosInvoiceLink);
        curl_setopt($fetchInvoice, CURLOPT_POST, 1);
        curl_setopt($fetchInvoice, CURLOPT_POSTFIELDS, $orderCollectionObject);
        curl_setopt($fetchInvoice, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($fetchInvoice, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: '.$token ));
        $invoice = curl_exec($fetchInvoice);
        curl_close($fetchInvoice);
        $invoiceDecode = json_decode($invoice);

        $addressesCollection = ['street' => $request->street, 'city' => $request->city, 'state' => $request->state, 'zipcode' => $request->zipcode];
        if ($invoiceDecode->result == "success"){
            //Generate Invoice End
            try {
                DB::beginTransaction();
                //Insert Order Data
                $insertOrder = new OrderManagement();
                $insertOrder->name = $request->name;
                $insertOrder->email = $request->email;
                $insertOrder->phone = $request->phone;
                $insertOrder->address = json_encode($addressesCollection);
                $insertOrder->amount = $request->amount;
                $insertOrder->currency = $invoiceDecode->data->order->currency;
                $insertOrder->product_name = $request->product_name;
                $insertOrder->product_details = $request->product_details;
                $insertOrder->refund = 0;
                $insertOrder->save();
                //Insert Invoice Data
                $insertInvoiceData = new PaymentInvoice();
                $insertInvoiceData->order_id = $insertOrder->id;
                $insertInvoiceData->invoice_id = $invoiceDecode->data->invoice_id;
                $insertInvoiceData->reference = $invoiceDecode->data->reference;
                $insertInvoiceData->type = $invoiceDecode->data->order->type;
                $insertInvoiceData->has_emi = $invoiceDecode->data->order->has_emi;
                $insertInvoiceData->discount_availed = $invoiceDecode->data->order->discount_availed;
                $insertInvoiceData->is_recurring_payment = $invoiceDecode->data->order->is_recurring_payment;
                $insertInvoiceData->status = $invoiceDecode->data->order->status;
                $insertInvoiceData->payment_url = $invoiceDecode->data->action->url;
                $insertInvoiceData->save();
                DB::commit();
                return ApiHelper::jsonResponse('Order Added & PortPos Invoice Generated Successfully..!!', 'OK', 200, NULL);
            } catch (\Exception $e) {
                Log::info($e);
                DB::rollback();
                return ApiHelper::jsonResponse('Something went wrong!', 'Not Found', 404, NULL);
            }
        }else{
            return ApiHelper::jsonResponse('Something went wrong!', 'Not Found', 404, NULL);
        }

    }
    public function updateOrderStatus(Request $request)
    {
        $orderInfo = PaymentInvoice::where('id', $request->id)->first();
        $token = "Bearer ". base64_encode(env('PortPos_AppKey').":".md5(env('PortPos_SecretKey').time()));
        $portPosIPN_URl = 'https://api-sandbox.portwallet.com/payment/v2/invoice/ipn/'.$request->invoice_id.'/'.$request->oder_details['amount'];

        //Check Status
        try {
            $fetchStatus = curl_init($portPosIPN_URl);
            curl_setopt($fetchStatus, CURLOPT_POST, 0);
            curl_setopt($fetchStatus, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($fetchStatus, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: '.$token ));
            $fetchedIPN = curl_exec($fetchStatus);
            curl_close($fetchStatus);
            $decodeIPN = json_decode($fetchedIPN);
            $status = $decodeIPN->data->order->status;
            //Check Status End
            if($status == 'ACCEPTED') {
                $orderInfo->status = $decodeIPN->data->order->status;
                $orderInfo->update();
                return ApiHelper::jsonResponse('Status Updated!', 'OK', 200, NULL);
            }elseif($status == 'PENDING') {
                return ApiHelper::jsonResponse('Still Pending!', 'OK', 200, NULL);
            }elseif($status == 'EXPIRED'){
                $orderInfo->status = $decodeIPN->data->order->status;
                $orderInfo->update();
                return ApiHelper::jsonResponse('Link Expired!', 'OK', 200, NULL);
            }elseif($status == 'REJECTED'){
                $orderInfo->status = $decodeIPN->data->order->status;
                $orderInfo->update();
                return ApiHelper::jsonResponse('Payment Rejected!', 'OK', 200, NULL);
            }else {
                $orderInfo->status = $decodeIPN->data->order->status;
                $orderInfo->update();
                return ApiHelper::jsonResponse('Status Updated With Error!', 'OK', 200, NULL);
            }
        } catch (\Exception $e) {
            \Log::info($e);
            return ApiHelper::jsonResponse('Something went wrong!', 'Not Found', 404, NULL);
        }
    }
    public function refundRequest(Request $request)
    {
        if ($request->status == 'ACCEPTED'){
            try {
                DB::beginTransaction();
                $invoiceInfo = PaymentInvoice::where('id', $request->id)->first();
                $orderInfo = OrderManagement::where('id',$invoiceInfo->order_id)->first();
                $token = "Bearer ". base64_encode(env('PortPos_AppKey').":".md5(env('PortPos_SecretKey').time()));

                $invoiceId = $invoiceInfo->invoice_id;
                $refundCollection = ['refund' => ['amount' => (float) $orderInfo->amount]];
                $refundObject = json_encode($refundCollection);
                $refundLink = "https://api-sandbox.portwallet.com/payment/v2/invoice/refund/{$invoiceId}";
                //Initiate refund
                $fetchRefundData = curl_init($refundLink);
                curl_setopt($fetchRefundData, CURLOPT_POST, 1);
                curl_setopt($fetchRefundData, CURLOPT_POSTFIELDS, $refundObject);
                curl_setopt($fetchRefundData, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($fetchRefundData, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: '.$token ));
                $resultStatus = curl_exec($fetchRefundData);
                curl_close($fetchRefundData);
                $decodeRefundData = json_decode($resultStatus);
                if ($decodeRefundData->result == 'success'){
                    $invoiceInfo->status = "REFUND";
                    $invoiceInfo->update();
                    $orderInfo->refund = 1;
                    $orderInfo->update();
                }
                DB::commit();
                return ApiHelper::jsonResponse('Refund request initiated successfully!', 'OK', 200, NULL);
            } catch (\Exception $e) {
                \Log::info($e);
                DB::rollback();
                return ApiHelper::jsonResponse('Something went wrong!', 'Not Found', 404, NULL);
            }
        }else{
            return ApiHelper::jsonResponse('Order is not refundable!', 'Not Found', 404, NULL);
        }
    }
}
