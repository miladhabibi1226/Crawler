<?php

namespace App\TravelServices\Suppliers\MystiFly\Logics\Flight;

use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;

trait FareRules
{
    use FlightFareRules;
    protected $revalidateFareSourceCode, $PaxNameCharacterLimit, $RequiredFieldsToBook;
    public function canBeDone()
    {
        $this->authorizeRequest();
        if ($this->sendRevalidateCurl() === false) {
            $this->addError("Revalidate curl is unsuccessful");
            return false;
        }
        $this->getRevalidateFareSourceCode();
    }

    protected function sendRevalidateCurl()
    {
        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        $this->call = [
            'fields' => json_encode(["FareSourceCode" => $this->object->seller_info['FareSourceCode'], "Target" => "Test"]),
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v1/Revalidate/Flight",
            'apiMethod' => 'Revalidate'
        ];
        return $this->action();
    }

    protected function getRevalidateFareSourceCode()
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
        $validateResult = $this->rawResult['decoded']['Data'] ?? '';

        if (empty($validateResult) || empty($validateResult['PricedItineraries'][0]['AirItineraryPricingInfo']['FareSourceCode'])) {
            lugError('response is empty or there is no revalidateFareSourceCode', [$this->rawResult]);
            $this->addError('response is empty or there is no revalidateFareSourceCode');
            $this->failBook();
            return false;
        }
        if (count($validateResult['PricedItineraries']) > 1) {
            lugError(' MystiFly revalidate items are more than 1', [$this->rawResult]);
            $this->addError('MystiFly revalidate items are more than 1');
            $this->failBook();
            return false;
        }

        $this->revalidateFareSourceCode = $validateResult['PricedItineraries'][0]['AirItineraryPricingInfo']['FareSourceCode'];
        $this->PaxNameCharacterLimit = $validateResult['PricedItineraries'][0]['PaxNameCharacterLimit'] ?? null;
        $this->RequiredFieldsToBook = $validateResult['PricedItineraries'][0]['RequiredFieldsToBook'] ?? ["DOB"];
        return true;
    }

    protected function getContent()
    {
        if ($this->fromFile) {
            $this->rawResult['decoded'] = json_decode(file_get_contents(storage_path('Samples/MystiFly/FlightFareRules.json')), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Request generator unsuccessful");
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        $this->call = [
            'fields' => json_encode(["FareSourceCode" => $this->revalidateFareSourceCode, "Target" => "Test"]),
            'url' =>  $this->cleanUrlSlash($this->seller->api->configs['url']) . "/api/v1/FlightFareRules",
            'apiMethod' => 'FareRules'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $this->items = [];
        $fareRulesResult = $this->rawResult['decoded']['Data']['FareRules'] ?? [];
        $commons = [];
        foreach ($fareRulesResult as $object) {
            if (empty($object['Airline'])) {
                if (empty($object['RuleDetails'])) {
                    continue;
                }
                foreach ($object['RuleDetails'] as $rule) {
                    $commons[] = [
                        'title' => $rule['Category'],
                        'text' => $rule['Rules']
                    ];
                }
            }
        }

        foreach ($fareRulesResult as $key => $object) {
            $item = [];
            if (empty($object['Airline'])) {
                if (count($fareRulesResult) == 1) {
                    $this->items[$key]['group'] = [];
                    $this->items[$key]['details'] = $commons;
                }
                continue;
            }

            $item['group'] = [
                'airline' => $object['Airline'],
                'cities' => $object['CityPair'],
            ];
            foreach ($object['RuleDetails'] as $rule) {
                $item['details'][] = [
                    'title' => $rule['Category'],
                    'text' => $rule['Rules']
                ];
            }
            $item['details'] = array_merge($item['details'], $commons);
            $this->items[] = $item;
        }

        // lugInfo('MystiFly fareRules extractor Items', ['items' => $this->items]);
    }

    protected function sellerInfoData() ///> rewritten in MystiFly
    {
        $sellerinfo = $this->object->seller_info;
        $sellerinfo['afters']['fareRules'] = true;
        $sellerinfo['validateResult']['PaxNameCharacterLimit'] = $this->PaxNameCharacterLimit;
        $sellerinfo['validateResult']['RequiredFieldsToBook'] = $this->RequiredFieldsToBook;
        return $sellerinfo;
    }
}
