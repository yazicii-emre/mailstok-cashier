<?php

namespace Acelle\Cashier\Controllers;

use Acelle\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Acelle\Cashier\Services\ShopierPaymentGateway;
use Acelle\Library\Facades\Billing;
use Acelle\Model\Setting;
use Acelle\Model\Invoice;
use Acelle\Library\TransactionResult;

// Yeni Eklenenler
use Acelle\Cashier\Controllers\Api\ShopierApiController;

use Acelle\Model\SubscriptionLog;
use Acelle\Library\Facades\SubscriptionFacade;

use Shopier\Models\ShopierResponse;


class ShopierController extends Controller
{
    public function settings(Request $request)
    {
        $gateway = Billing::getGateway('shopier');

        if ($request->isMethod('post')) {
            // make validator
            $validator = \Validator::make($request->all(), [
                'api_key' => 'required',
                'api_secret' => 'required',
            ]);


            // test service
            $validator->after(function ($validator) use ($gateway, $request) {
                try {
                    $shopier = new ShopierPaymentGateway('production', $request->api_key, $request->api_secret);
                    $shopier->test();
                } catch (\Exception $e) {
                    $validator->errors()->add('field', 'Cannot connect to ' . $gateway->getName() . '. Error: ' . $e->getMessage());
                }
            });

            // redirect if fails
            if ($validator->fails()) {
                return response()->view('cashier::shopier.settings', [
                    'gateway' => $gateway,
                    'errors' => $validator->errors(),
                ], 400);
            }

            // save settings
            Setting::set('cashier.shopier.api_key', $request->api_key);
            Setting::set('cashier.shopier.api_secret', $request->api_secret);

            // enable if validated
            if ($request->enable_gateway) {
                Billing::enablePaymentGateway($gateway->getType());
            }

            $request->session()->flash('alert-success', trans('cashier::messages.gateway.updated'));
            return redirect()->action('Admin\PaymentController@index');
        }

        return view('cashier::shopier.settings', [
            'gateway' => $gateway,
        ]);
    }

    public function getCheckoutUrl($invoice)
    {

        /* Normal Sayfa içerisnide Açmak için kullanılıyor */

        return action("\Acelle\Cashier\Controllers\ShopierController@checkout", [
            'invoice_uid' => $invoice->uid,
        ]);
    }

    public function getPaymentService()
    {
        return Billing::getGateway('shopier');
    }



    public function checkout(Request $request, $invoice_uid = null)
    {


        $service = $this->getPaymentService();
        $invoice = Invoice::findByUid($request->invoice_uid);


        if (!$invoice->isNew()) {
            throw new \Exception('Invoice is not new');
        }

        // Free plan, no charge
        if ($invoice->total() == 0) {
            $invoice->checkout($service, function ($invoice) {
                return new TransactionResult(TransactionResult::RESULT_DONE);
            });

            return redirect()->away(Billing::getReturnUrl());
        }


        return view('cashier::shopier.checkout', [
            'service' => $service,
            'invoice' => $invoice,
        ]);
    }


    public static function payWithShopier($invoice)
    {
        try {


            $customer = $invoice->customer;


            // Use invoice's built-in total calculation
            $totalAmount = $invoice->total();

            // Convert USD to TRY with proper formatting
            // $currencyService = new \Acelle\Services\CurrencyRateService();
            // $convertUsdToTry = $currencyService->convertUsdToTry($totalAmount);

            $billingAddress = $customer->billingAddresses()

                ->join('countries', 'billing_addresses.country_id', '=', 'countries.id')
                ->select(
                    'billing_addresses.address as address',
                    'billing_addresses.city as city',
                    'countries.name as country',
                    'billing_addresses.postal_code as postcode'
                )
                ->first();


            if (!$billingAddress) {
                throw new \Exception('Billing address not found');
            }

            // Create Shopier instance first to get random number
            $shopier = new ShopierApiController();

            // Get random number directly from shopier params


            $addressArray = $billingAddress->toArray();

            return $shopier->setShopierPayment(
                'TR',
                [
                    'id' => $invoice->customer_id,
                    'name' => $invoice->billing_first_name,
                    'surname' => $invoice->billing_last_name,
                    'email' => $invoice->billing_email,
                    'phone' => $invoice->billing_phone,
                ],
                $addressArray,
                $invoice->uid,
                $totalAmount,
                $invoice->title,
                $invoice
            )->redirectPaymentPage(true); // iframe'i false yapıyoruz
        } catch (\Exception $e) {
            \Log::error('Shopier Payment Error: ' . $e->getMessage(), [
                'invoice_id' => $invoice->uid,
                'exception' => $e
            ]);
            throw $e;
        }
    }


    public function callback(Request $request)
    {


        \Log::info('Shopier Callback Başlatıldı', [
            'request_data' => $request->all(),
        ]);

        try {
            // Validate callback IP (Shopier'in IP listesini kontrol et)
            // $allowedIPs = config('shopier.allowed_ips', ['*']);
            // if (!in_array('*', $allowedIPs) && !in_array($request->ip(), $allowedIPs)) {
            //     throw new \Exception('Unauthorized IP address: ' . $request->ip());
            // }


            // Request validation
            $this->validateCallbackRequest($request);

            // Get and validate invoice
            $invoice = $this->getAndValidateInvoice($request->input('platform_order_id'));

            // Validate signature and prepare response
            $shopierResponse = $this->validateSignature($request, $invoice);

            // Process payment
            return $this->processPayment($request, $invoice, $shopierResponse);
        } catch (\Exception $e) {
            \Log::error('Shopier Callback Hatası', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->handleCallbackError($e);
        }
    }

    private function validateCallbackRequest(Request $request)
    {

        $validator = \Validator::make($request->all(), [
            'platform_order_id' => 'required|string',
            'API_key' => 'required|string',
            'status' => 'required|string',
            'signature' => 'required|string',
            'random_nr' => 'required|string',
            'payment_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            throw new \Exception('Invalid callback parameters: ' . implode(', ', $validator->errors()->all()));
        }
    }

    private function getAndValidateInvoice($invoiceUid)
    {
        $invoice = Invoice::findByUid($invoiceUid);





        if (!$invoice) {
            throw new \Exception('Invalid invoice ID: ' . $invoiceUid);
        }

        if (!$invoice->subscription) {
            throw new \Exception('No subscription found for invoice: ' . $invoiceUid);
        }

        // Check if the subscription is new
        if (!$invoice->subscription->isNew()) {
            throw new \Exception('Subscription is not new for invoice: ' . $invoiceUid);
        }

        return $invoice;
    }

    private function validateSignature(Request $request, Invoice $invoice)
    {
        $_POST = [
            'platform_order_id' => $request->input('platform_order_id'),
            'API_key' => $request->input('API_key'),
            'status' => $request->input('status'),
            'installment' => $request->input('installment'),
            'payment_id' => $request->input('payment_id'),
            'random_nr' => $request->input('random_nr'),
            'signature' => $request->input('signature')
        ];



        $shopierResponse = ShopierResponse::fromPostData();

        \Log::info('Shopier İmza Kontrolü', [
            'invoice_id' => $invoice->uid,
            'request_data' => $_POST,
            'api_secret' => Setting::get('cashier.shopier.api_secret')
        ]);

        if (!$shopierResponse->hasValidSignature(Setting::get('cashier.shopier.api_secret'))) {
            throw new \Exception('Invalid signature for invoice: ' . $invoice->uid);
        }

        return $shopierResponse;
    }

    private function processPayment(Request $request, Invoice $invoice, $shopierResponse)
    {
        $subscription = $invoice->subscription;

        \DB::beginTransaction();

        try {
            // Create pending transaction if not exists
            if (!$invoice->getPendingTransaction()) {
                $invoice->createPendingTransaction($this->getPaymentService());
            }

            if ($request->input('status') !== 'success') {
                $reason = trans('messages.shopier.reject.reason');
                $invoice->reject($reason);


                // Segment hesaplamalarını doğru şekilde yapmak için veritabanındaki hesaplanmış değeri kullanıyoruz
                // Bu, her seferinde yeniden hesaplama yapmak yerine tutarlı bir değer sağlar
                SubscriptionFacade::log($subscription, SubscriptionLog::TYPE_PAY_FAILED, $invoice->uid, [
                    'amount' => format_price($invoice->lastInvoiceItem()->first()->total(), $invoice->currency->format),
                    'reason' => $reason,
                    'data' => $request->all()
                ]);



                return redirect()->action('SubscriptionController@index')
                    ->with('error', trans('messages.shopier.payment_failed'));
            }


            // Debug amaçlı dd kaldırıldı
            // return dd($totalAmount);


            // Remove subscription->activate() since approve() handles it
            $invoice->approve();

            // Send IP assignment notification to admin if applicable
            if ($invoice->customer && $invoice->customer->canUseDedicatedIpAddress()) {
                \Acelle\Model\Admin::sendIpAssignmentEmailToAdmin($invoice);
            }

            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollBack();
            return $this->handleCallbackError($e);
        }

        return redirect()->action('SubscriptionController@index')
            ->with('success', trans('messages.subscription.set_active.success'));
    }

    private function handleCallbackError(\Exception $e)
    {
        return redirect()->action('SubscriptionController@index')
            ->with('error', trans('messages.error') . ': ' . $e->getMessage());
    }

    public function autoBillingDataUpdate(Request $request)
    {
        return redirect()->away(Billing::getReturnUrl());
    }
}
