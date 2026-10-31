<?php

namespace App\TravelServices\Suppliers\Travelport\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;

    protected function canBeDone() {}

    protected function getContent()
    {
        // $this->fromFile = true;
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/Travelport/FlightRules.json')), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        foreach ($this->rawResult as $index => $rawResult) {
            $xml = preg_replace('/<(\/?)(\w+):(\w+)/', '<$1$3', $rawResult['body']);
            $xml = new \SimpleXMLElement($xml);
            $this->rawResult[$index]['decoded'] = json_decode(json_encode($xml->xpath('//Body')), true)[0] ?? [];
        }

    }

    protected function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $auth + $this->object->seller_info;
        foreach ($this->object->seller_info['requestInfo'] as $legIndex => $leg) {
            foreach ($leg['segments'] as $segIndex => $segment) {
                foreach ($segment['fareInfo'] as $fareInfo) {
                    $pType = $fareInfo['@attributes']['PassengerTypeCode'];
                    $passengerType = ['ADT' => 'adults', 'CNN' => 'children', 'INF' => 'infants'][$pType];
                    if (empty($this->queryParams[$passengerType])) {
                        continue;
                    }
                    $org = $fareInfo['@attributes']['Origin'];
                    $des = $fareInfo['@attributes']['Destination'];

                    $this->call[$org . '-' . $des . '/' . $pType] = [
                        $depends = $auth + ['fareInfo' => $fareInfo] +$this->object->seller_info,
                        'fields' => $fields = $this->minifyXML(view('Travelport.FlightRule', $depends)->render()),
                        'url' =>   $this->cleanUrlSlash($auth['url']),
                        'headers' => [
                            "Content-length: " . strlen($fields),
                        ],
                        'timeout' => 15,
                        'apiMethod' => 'Fare Rules'
                    ];
                }
            }
        }
        $this->multi = true;
        return $this->action();
    }

    protected function extractor()
    {
        foreach ($this->rawResult as $orgDespassengerType =>  $rawResult) {
            //this is for short_response :
            $fareRulesResult = $rawResult['decoded']['AirFareRulesRsp']['FareRule']['FareRuleShort'] ?? '';
            if (empty($fareRulesResult)) {
                continue;
            }
            foreach ($fareRulesResult as $key => $object) {
                $parts = explode('/', $orgDespassengerType);
                $orgDes = $parts[0];
                $passengerType = $parts[1];
                $item = [];
                $item['group'] = [
                    'passengerType' => $passengerType,
                    'airports' =>  $orgDes
                ];
                $details = [];
                $details['title'] = $this->getCategory($object['@attributes']['Category']);
                $details['text'] = $object['FareRuleNameValue']['@attributes']['Value'];
                $item['details'][] = $details;
                $this->items[] = $item;
            }
        }
        // lugInfo('Travelport fareRules extractor Items', ['items' => $this->items]);
    }

    protected function getCategory($id)
    {
        return  [
            1 => 'Eligibility',
            2 => 'Day/Time',
            3 => 'Seasonality',
            4 => 'Flight Application',
            5 => 'Advance Reservations/Ticketing',
            6 => 'Minimum Stay',
            7 => 'Maximum Stay',
            8 => 'Stopovers',
            9 => 'Transfers',
            10 => 'Combinations',
            11 => 'Blackout Dates',
            12 => 'Surcharges',
            13 => 'Accompanied Travel',
            14 => 'Travel Restrictions',
            15 => 'Sales Restrictions',
            16 => 'Penalties',
            17 => 'HIP/Mileage Exceptions',
            18 => 'Ticket Endorsements',
            19 => 'Children Discounts',
            20 => 'Tour Conductor Discounts',
            21 => 'Agent Discounts',
            22 => 'All Other Discounts',
            23 => 'Miscellaneous Provisions',
            24 => 'n/a',
            25 => 'Fare by Rule',
            26 => 'Groups',
            27 => 'Tours',
            28 => 'Visit Another Country',
            29 => 'Deposits',
            31 => 'Voluntary Changes',
            33 => 'Voluntary Refunds',
            35 => 'Negotiated Fare Restrictions',
            50 => 'Application and Other Conditions'
        ][$id] ?? $id;
    }

    protected function haveSupplierResponseError()
    {
        foreach ($this->rawResult as $key => $rawResult) {
            if (!empty($rawResult['decoded']['Fault'])) {
                $rawError =  $rawResult['decoded']['Fault']['detail']['ErrorInfo']['Description'];
                lugWSResError("Travelport response contains error ({$this->seller->alias})", ['supplierError: ' => $rawError ?? $rawResult, 'supplier response: ' => $rawResult['body']]);
                $this->supplierConversaion($rawError);
                unset($this->rawResult[$key]);
            }
            if ($rawResult['http_code'] != 200) {
                lugWSResError("Travelport response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
                $this->supplierConversaion('invalid response status: ' . $rawResult['http_code']);
                unset($this->rawResult[$key]);
            }
            $this->supplierConversaion();
            return false;
        }
    }
}
