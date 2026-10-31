<?php

namespace App\TravelServices\Suppliers\Avtra\Logics\Flight;

use Carbon\Carbon;

trait Issue
{
    use BookAndIssue;
    protected $responseSample = 'Samples/Avtra/FlightIssue.xml';
    protected $latestSession;

    public function canBeDone()
    {
        if ($this->object->status != 'on_hold') {
            $this->addError('Invalid Booking status: ' . $this->object->status);
            return false;
        }
        if ($this->bookingType == 'bookOnly') {
            if (Carbon::create($this->object->expire_on) < Carbon::now()) {
                $this->object->status = 'expired';
                $this->object->update();
                $this->addError('The booking has been expired');
                return false;
            }
        }
        return true;
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + [
            'bookingInfo' => $this->object->booking_info,
            'passengers' => $this->object->passenger_info,
            'sellerInfo' => $this->object->seller_info,
            'time' => Carbon::now()->toIso8601String(),
            'sellerCurrency' => $this->seller->currency->abb,
            'price' => [
                'payable' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable'],
                'baseFare' => $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['baseFare']
            ]
        ];
        lugInfo('Issue Data', $depends);
        $this->call = [
            'fields' => view('Avtra.FlightIssue', $depends)->render(),
            'url' => $this->cleanUrlSlash($auth['url']) . 'modifybooking/confirm',
            'cookies' => null,
            'apiMethod' => 'confirm'
        ];
        return $this->action();
    }

    protected function mapper()
    {
        $bookingInfo = [
            'pnr' => $this->items['pnr'],
            'tickets' => $this->items['tickets'],
            'supplierBookingStatus' => $this->items['supplierBookingStatus'],
            'issued_at' => $this->items['issued_at']
        ];

        $this->object->booking_info = $bookingInfo;
        $this->object->status =  'issued';
        $this->object->update();
    }
}
