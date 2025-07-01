<?php

namespace Acelle\Cashier\Services;

use Acelle\Cashier\Cashier;
use Acelle\Library\Contracts\PaymentGatewayInterface;
use Carbon\Carbon;
use Acelle\Model\Invoice;
use Acelle\Library\TransactionResult;
use Acelle\Model\Transaction;
use Illuminate\Support\Facades\Http;

class ShopierPaymentGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl = 'https://www.shopier.com/api';
    public $active = false;

    public const TYPE = 'shopier';

    public function __construct( $apiKey, $apiSecret)
    {

        if (empty($apiKey) || empty($apiSecret)) {
            throw new \InvalidArgumentException("API key and secret key must be provided");
        }


        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;

        $this->validate();
    }

    public function getapiKey()
    {
        return $this->apiKey;
    }


    public function getapiSecret()
    {
        return $this->apiSecret;
    }

    public function getName(): string
    {
        return trans('cashier::messages.shopier');
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getDescription(): string
    {
        return trans('cashier::messages.shopier.description');
    }

    public function getShortDescription(): string
    {
        return trans('cashier::messages.shopier.short_description');
    }

    public function validate()
    {
        if (!$this->apiKey || !$this->apiSecret) {
            $this->active = false;
        } else {
            $this->active = true;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getSettingsUrl(): string
    {
        return action("\Acelle\Cashier\Controllers\ShopierController@settings");
    }

    public function getCheckoutUrl($invoice): string
    {

        return action("\Acelle\Cashier\Controllers\ShopierController@checkout", [
            'invoice_uid' => $invoice->uid,
        ]);
    }

    public function autoCharge($invoice)
    {
        throw new \Exception('Shopier payment gateway does not support auto charge!');
    }

    public function getAutoBillingDataUpdateUrl($returnUrl = '/'): string
    {
        throw new \Exception('
            Shopier gateway does not support auto charge.
            Therefore method getAutoBillingDataUpdateUrl is not supported.
            Something wrong in your design flow!
            Check if a gateway supports auto billing by calling $gateway->supportsAutoBilling().
        ');
    }

    public function allowManualReviewingOfTransaction(): bool
    {
        return false;
    }

    public function supportsAutoBilling(): bool
    {
        return false;
    }

    public function verify(Transaction $transaction): TransactionResult
    {
        throw new \Exception("Payment service {$this->getType()} should not have pending transaction to verify");
    }

    public function charge($invoice, $options = [])
    {
        $invoice->checkout($this, function ($invoice) use ($options) {
            try {
                $this->doCharge($invoice, $options);
                return new TransactionResult(TransactionResult::RESULT_DONE);
            } catch (\Exception $e) {
                return new TransactionResult(TransactionResult::RESULT_FAILED, $e->getMessage());
            }
        });
    }

    public function test()
    {
        try {
            $response = $this->createTestPayment();
            if (!$response->successful()) {
                throw new \Exception('API connection failed');
            }
        } catch (\Exception $e) {
            throw new \Exception('Shopier API connection failed: ' . $e->getMessage());
        }

        return true;
    }

    private function createTestPayment()
    {
        $data = [
            'API_key' => $this->apiKey,
            'website_index' => 1,
            'platform_order_id' => 'test_' . time(),
            'total_order_value' => 1,
            'currency' => 'TRY',
        ];

        $signature = $this->generateSignature($data);
        $data['signature'] = $signature;

        return Http::post($this->baseUrl . '/payment/test', $data);
    }

    public function doCharge($invoice, $options = [])
    {
        $data = [
            'API_key' => $this->apiKey,
            'website_index' => 1,
            'platform_order_id' => $invoice->uid,
            'product_name' => $invoice->title,
            'buyer_name' => $invoice->customer->name,
            'buyer_email' => $invoice->customer->email,
            'buyer_phone' => $invoice->customer->phone ?? '',
            'total_order_value' => $invoice->total(),
            'currency' => 'TRY',
            'callback_url' => action("\Acelle\Cashier\Controllers\ShopierController@callback"),
        ];

        $signature = $this->generateSignature($data);
        $data['signature'] = $signature;

        $response = Http::post($this->baseUrl . '/payment', $data);

        if (!$response->successful() || !isset($response['payment_url'])) {
            throw new \Exception('Payment initialization failed: ' . $response->body());
        }

        return $response['payment_url'];
    }

    private function generateSignature($data)
    {
        return base64_encode(hash_hmac('sha256', json_encode($data), $this->apiSecret, true));
    }

    public function getMinimumChargeAmount($currency)
    {
        return 0;
    }

    public function verifyCallback($request)
    {
        $signature = $request->header('X-Shopier-Signature');
        $calculatedSignature = $this->generateSignature($request->all());

        return hash_equals($signature, $calculatedSignature);
    }
}
