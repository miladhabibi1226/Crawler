<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

use App\TravelServices\Logics\Flight\Captcha as FlightCaptcha;

trait Captcha
{
    use FlightCaptcha;
    public function canBeDone()
    {
        $this->authorizeRequest();
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info;
        lugInfo('Book data', $depends);
        $this->call = [
            'fields' => json_encode(['AirItinerary' => $depends['bookingRequestData']]),
            'url' => $this->cleanUrlSlash($auth['url']) . '/flights/captcha',
            'apiMethod' => 'captcha'
        ];
        return $this->action();
    }

    protected function getCaptcha($result)
    {
        ///> for return flights the response is different for normal items and oneway combination items
        ///> but we have filtered oneWay combination items so here we are only handling normal flights
        return $result['Items'][0]['link_captcha'] ?? null;
    }
}
