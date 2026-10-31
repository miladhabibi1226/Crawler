<?php

namespace App\TravelServices\Suppliers\CityNet\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    protected function canBeDone()
    {
        $this->authorizeRequest();
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/CityNet/FlightFareRules.json'));;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }
        foreach ($this->rawResult as $key => $rawResult) {
            $this->rawResult[$key]['decoded'] = json_decode($rawResult['body'], true);
        }

        $this->haveSupplierResponseError();
    }

    public function requestGenerator()
    {
        ///> in CityNet we have to acall fareRules for each passenger type seperately
        // $this->LugTheRequest = true;
        $this->multi = true;
        $this->LugTheResponse = 0;
        $depends = $this->seller->api->configs + $this->object->seller_info;
        // lugInfo('FareRules data', [$depends]);
        $this->call['ADT'] = [
            'fields' => json_encode(['PassengerType' => 'ADT', 'AirItinerary' => $depends['bookingRequestData']]),
            'url' => $this->cleanUrlSlash($depends['url']) . '/flights/rules',
            'apiMethod' => 'Fare Rules'
        ];
        if (!empty($this->queryParams['children'])) {
            $this->call['CHD'] = [
                'fields' => json_encode(['PassengerType' => 'CHD', 'AirItinerary' => $depends['bookingRequestData']]),
                'url' =>  $depends['url'] . '/flights/rules',
                'apiMethod' => 'Fare Rules'
            ];
        }
        if (!empty($this->queryParams['infants'])) {
            $this->call['INF'] = [
                'fields' => json_encode(['PassengerType' => 'INF', 'AirItinerary' => $depends['bookingRequestData']]),
                'url' =>  $depends['url'] . '/flights/rules',
                'apiMethod' => 'Fare Rules'
            ];
        }
        return $this->action();
    }

    public function extractor()
    {
        foreach ($this->rawResult as $passegerType =>  $rawResult) {
            $fareRulesResult = $rawResult['decoded']['Items'] ?? [];

            if (empty($fareRulesResult)) {
                continue;
            }
            foreach ($fareRulesResult as $fareRule) {
                $item = [];
                $item['group'] = [
                    'passengerType' => $passegerType,
                    'airline' => $fareRule['Airline'] . ' - ' . $fareRule['MarketAirline'],
                    'airports' => $fareRule['DepartureLocationCode'] . ' - ' . $fareRule['ArrivalLocationCode']
                ];
                foreach ($fareRule['DetailRules'] ?? [] as $subSection) {
                    $item['details'][] = [
                        'title' => $subSection['Subtitle'] ?? '',
                        'text' => $subSection['Text'] ?? '',
                    ];
                }
                $this->items[] = $item;
            }
        }
        lugInfo('CityNet FareRules extractor Items', ['Items' => $this->items]);
    }

    protected function haveSupplierResponseError()
    {
        foreach ($this->rawResult as $key => $rawResult) {
            $rawError = $this->rawResult[$key]['decoded']['Name'] ?? null;
            if (!empty($rawError)) {
                $err = $rawError;
                if (is_string($this->rawResult[$key]['decoded']['Items'])) {
                    $err .= '; ' . $this->rawResult[$key]['decoded']['Items'];
                }
                lugWSResError("CityNet ({$this->seller->alias}) response contains error", ['Name' => $err, 'supplier errors: ' => $this->rawResult[$key]['decoded']['Items'], 'supplier response: ' => $this->rawResult]);
            } else {
                if ($this->rawResult[$key]['http_code'] != 200) {
                    $err = "CityNet ({$this->seller->alias}) response is invalid: " . $this->rawResult[$key]['http_code'];
                    lugWSResError($err, ['rawResult' => $this->rawResult]);
                }
            }
        }
        $this->supplierConversaion($err ?? null);
        return $err ?? false;
    }
}
