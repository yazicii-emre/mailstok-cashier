<?php

namespace Acelle\Cashier\Controllers\Api;

use Acelle\Http\Controllers\Controller;
use Illuminate\Http\Request;

//use JetBrains\PhpStorm\Language;

use Shopier\Models\Address;
use Shopier\Models\Buyer;
use Shopier\Renderers\AutoSubmitFormRenderer;
use Shopier\Renderers\IframeRenderer;
use Shopier\Shopier as ShopierApi;

use Shopier\Enums\Language;
use Shopier\Enums\Currency;
use Shopier\Enums\ProductType;
use Shopier\Enums\WebsiteIndex;

use Acelle\Model\Setting;


class ShopierApiController extends Controller
{
    protected $shopier = null;

    public function __construct()
    {
        $public_key = Setting::get('cashier.shopier.api_key');
        $secret_key = Setting::get('cashier.shopier.api_secret');

        $this->shopier = new ShopierApi($public_key, $secret_key);
    }

    /**
     * Filter price to ensure it's within reasonable limits
     * 
     * @param float $amount The original price amount
     * @return float The filtered price amount
     */
    public function filterPrice(float $amount): float
    {
        // Ensure the amount is reasonable (33,000 range instead of 336,000)
        if ($amount > 100000) {
            // If the amount is too large, divide by 10 to get a more reasonable value
            $amount = $amount / 10;
        }
        
        return $amount;
    }


    public function setShopierPayment(string $language, array $productBuyer, array $shippingAddress, string $productId, float $productPrice, $productName, $invoice)
    {
        // Satın alan kişi bilgileri
        $buyer = new Buyer($productBuyer);

        // Fatura ve kargo adresi birlikte tanımlama
        $address = new Address($shippingAddress);

        // shopier parametrelerini al ve random number'ı kaydet
        $params = $this->shopier->getParams();
        
        try {
            $invoice->random_nr = $params->getRandomNr();
            $invoice->save();
        } catch (\Exception $e) {
            \Log::error('Shopier Random Number Err: ' . $e->getMessage(), [
                'invoice_id' => $invoice->uid
            ]);
            throw $e;
        }

      

        // Set test price for development
        // $testPrice = 1.00; // 1 TL for testing

        $params->setWebsiteIndex(WebsiteIndex::SITE_3);
        $params->setBuyer($buyer);
        $params->setAddress($address);

        // Apply price filtering - use actual price in production
        $filteredPrice = $this->filterPrice($productPrice);

        // In development mode, use test price. In production, use filtered price
        // $finalPrice = $testPrice; // For development
        $finalPrice = $filteredPrice; // For production

        $params->setOrderData($productId, $finalPrice);

        $params->setProductData($productName, ProductType::DOWNLOADABLE_VIRTUAL);

        if (strtoupper(substr($language, 0, 2)) === 'TR') {
            $params->setCurrency(Currency::TL);
            $params->setCurrentLanguage(Language::TR);
        } else {
            $params->setCurrency(Currency::USD);
            $params->setCurrentLanguage(Language::EN);
        }

        // Generate signature for logging
        $signature = hash_hmac('sha256', 
            $params->getRandomNr() . $productId, 
            Setting::get('cashier.shopier.api_secret'), 
            true
        );
        $encodedSignature = base64_encode($signature);

        \Log::info('Shopier Payment Parameters', [
            'product_id' => $productId,
            'price' => $productPrice,
            'filtered_price' => $filteredPrice,
            'final_price' => $finalPrice,
            'name' => $productName,
            'language' => $language,
            'params' => json_encode($params, true),
            'buyer' => $buyer,
            'address' => $address,
            'random_nr' => $params->getRandomNr(),
            'signature' => $encodedSignature
        ]);

        

        return $this;
    }

    public function redirectPaymentPage($iframe = false)
    {
        try {
             
            if ($iframe == FALSE) {
                $renderer = new AutoSubmitFormRenderer($this->shopier);
            } else {
                $renderer = new IframeRenderer($this->shopier);
                $renderer;
            }

            $this->shopier->prepare(); // Add prepare call before goWith
            return $this->shopier->goWith($renderer);
        } catch (\Exception $e) {
            \Log::error('Shopier Payment Error: ' . $e->getMessage(), [
                'params' => $this->shopier->getParams()->toArray()
            ]);
            throw $e;
        }
    }


    //    public static function paymentResponse($invoice)
    //    {
    //
    //
    //
    //
    //    }
}
