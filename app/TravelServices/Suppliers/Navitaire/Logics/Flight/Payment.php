<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics\Flight;

use App\TravelServices\Logics\BeforeBook;

trait Payment
{
    use BeforeBook;
    protected $responseSample = 'Samples/Navitaire/Payment.json';
    public function sessionHandling()
    {
        return $this->authorizeRequest($this->object->seller_info['bookingSession'] ?? '');
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        lugInfo('Payment data', [$depends]);
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'timeout' => 20,
            'url' =>  $this->cleanUrlSlash($auth['url']) . "/api/jz/v1/Booking/Payment/AddPaymentToBooking",
            'apiMethod' => 'Payment'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $Result = $this->rawResult['decoded']['data']['paymentAddv2']['payment'] ?? [];
        if (empty($Result)) {
            lugError('empty breakdown', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('empty Result');
        }
        if (!$Result['addedToState'] || $Result['status'] != 'Pending') {
            lugError('payment has problems', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->failBook();
            return $this->addError('payment has problems');
        }
    }

    protected function getFields($depends)
    {
        return [
            "amount" => $this->object->price_info['sellerPrice']['rawPrice']['balanceDue'],
            "paymentFields" => ["ACCTNO" => $depends['organization']],
            "currencyCode" => $depends['resultCurrency'],
            "installments" => '1',
            "paymentMethodCode" => 'AG',
        ];
    }

    protected function handleSignOut()
    {
        ///> signout will be called in bookAndIssue failed
    }
}
