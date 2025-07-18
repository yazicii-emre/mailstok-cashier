<?php

namespace MailStok\Cashier\Services;

use MailStok\Cashier\Cashier;
use MailStok\Library\Contracts\PaymentGatewayInterface;
use Carbon\Carbon;
use Sample\PayPalClient;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use MailStok\Model\Invoice;
use MailStok\Library\TransactionResult;
use MailStok\Model\Transaction;

class PaypalPaymentGateway implements PaymentGatewayInterface
{
    public $clientId;
    public $secret;
    public $client;
    public $environment;
    public $active=false;

    public const TYPE = 'paypal';

    public function __construct($environment, $clientId, $secret)
    {
        $this->environment = $environment;
        $this->clientId = $clientId;
        $this->secret = $secret;

        if ($this->environment == 'sandbox') {
            $this->client = new PayPalHttpClient(new SandboxEnvironment($this->clientId, $this->secret));
        } else {
            $this->client = new PayPalHttpClient(new ProductionEnvironment($this->clientId, $this->secret));
        }

        $this->validate();
    }

    public function getName() : string
    {
        return trans('cashier::messages.paypal');
    }

    public function getType() : string
    {
        return self::TYPE;
    }

    public function getDescription() : string
    {
        return trans('cashier::messages.paypal.description');
    }

    public function getShortDescription() : string
    {
        return trans('cashier::messages.paypal.short_description');
    }

    public function validate()
    {
        if (!$this->environment || !$this->clientId || !$this->secret) {
            $this->active = false;
        } else {
            $this->active = true;
        }
        
    }

    public function isActive() : bool
    {
        return $this->active;
    }

    public function getSettingsUrl() : string
    {
        return action("\MailStok\Cashier\Controllers\PaypalController@settings");
    }

    public function getCheckoutUrl($invoice) : string
    {
        return action("\MailStok\Cashier\Controllers\PaypalController@checkout", [
            'invoice_uid' => $invoice->uid,
        ]);
    }

    public function autoCharge($invoice)
    {
        throw new \Exception('Paypal payment gateway does not support auto charge!');
    }

    public function getAutoBillingDataUpdateUrl($returnUrl='/') : string
    {
        throw new \Exception('
            Paypal gateway does not support auto charge.
            Therefor method getAutoBillingDataUpdateUrl is not supported.
            Something wrong in your design flow!
            Check if a gateway supports auto billing by calling $gateway->supportsAutoBilling().
        ');
    }

    public function allowManualReviewingOfTransaction() : bool
    {
        return false;
    }

    public function supportsAutoBilling() : bool
    {
        return false;
    }

    public function verify(Transaction $transaction) : TransactionResult
    {
        throw new \Exception("Payment service {$this->getType()} should not have pending transaction to verify");
    }
    
    public function charge($invoice, $options=[])
    {
        $invoice->checkout($this, function($invoice) use ($options) {
            try {
                // charge invoice
                $this->doCharge($invoice, $options);

                return new TransactionResult(TransactionResult::RESULT_DONE);
            } catch (\Exception $e) {
                return new TransactionResult(TransactionResult::RESULT_FAILED, $e->getMessage());
            }
        });
    }

    /**
     * Check if service is valid.
     *
     * @return void
     */
    public function test()
    {
        try {
            $response = $this->client->execute(new OrdersGetRequest('ssssss'));
        } catch (\Exception $e) {
            $result = json_decode($e->getMessage(), true);
            if (isset($result['error']) && $result['error'] == 'invalid_client') {
                throw new \Exception($e->getMessage());
            }
        }
        
        return true;
    }
    
    /**
     * Create a new subscriptionParam.
     *
     * @param  mixed              $token
     * @param  SubscriptionParam  $param
     * @return void
     */
    public function doCharge($invoice, $options=[])
    {
        // check order ID
        $this->checkOrderID($options['orderID']);
    }

    /**
     * Swap subscription plan.
     *
     * @param  Subscription  $subscription
     * @return date
     */
    public function checkOrderID($orderID)
    {
        // Check payment status
        $response = $this->client->execute(new OrdersGetRequest($orderID));

        /**
         *Enable the following line to print complete response as JSON.
        */
        //print json_encode($response->result);
        print "Status Code: {$response->statusCode}\n";
        print "Status: {$response->result->status}\n";
        print "Order ID: {$response->result->id}\n";
        print "Intent: {$response->result->intent}\n";
        print "Links:\n";
        foreach ($response->result->links as $link) {
            print "\t{$link->rel}: {$link->href}\tCall Type: {$link->method}\n";
        }
        // 4. Save the transaction in your database. Implement logic to save transaction to your database for future reference.
        print "Gross Amount: {$response->result->purchase_units[0]->amount->currency_code} {$response->result->purchase_units[0]->amount->value}\n";

        // To print the whole response body, uncomment the following line
        // echo json_encode($response->result, JSON_PRETTY_PRINT);

        // if failed
        if ($response->statusCode != 200 || $response->result->status != 'COMPLETED') {
            throw new \Exception('Something went wrong:' . json_encode($response->result));
        }
    }

    public function getMinimumChargeAmount($currency)
    {
        return 0;
    }
}
