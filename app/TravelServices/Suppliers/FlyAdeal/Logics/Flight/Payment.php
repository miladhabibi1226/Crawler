<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;
use Illuminate\Support\Str;

trait Payment
{
    use BeforeBook;
    protected $responseSample = 'Samples/FlyAdeal/Payment.json';
    public function sessionHandling()
    {
        return $this->authorizeRequest($this->object->seller_info['bookingSession'] ?? '');
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth + ['price' => $this->object->price_info['sellerPrice']];
        lugInfo('Payment data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'timeout' => 20,
            'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/nsk/v4/booking/payments",
            'apiMethod' => 'Payment'
        ];
        return $this->action();
    }

    public function extractor()
    {
        ///> if payment is successful data should be null
        if (!empty($this->rawResult['decoded']['data'])) {
            lugError('payment has problems', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('payment has problems');
        }
    }

    protected function getFields($depends)
    {
        return [
            "paymentMethodCode" => "AG",
            "amount" => round($depends['price']['details']['bySellerCurrency']['payable'], 2), ///> it's the same as the latest balanceDue. we checked in tripsell
            "currencyCode" => $depends['resultCurrency'],
            "installments" => 1,
            "paymentFields" => [
                "ACCTNO" => Str::before($depends['username'], '-'),
            ]
        ];
    }

    protected function handleSignOut()
    {
        ///> signout will be called in bookAndIssue failed
    }
}
