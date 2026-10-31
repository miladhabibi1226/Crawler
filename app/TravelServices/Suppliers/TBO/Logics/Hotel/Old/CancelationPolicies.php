<?php

namespace App\TravelServices\Suppliers\TBO\Logics\Hotel\Old;


trait CancelationPolicies
{
    protected function getContent()
    {
        if ($this->fromFile) {
            $xml = new \SimpleXMLElement(file_get_contents(storage_path('Samples/TBO/CancelationPolicies.xml')));
            $this->rawResult[1]['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }

        foreach ($this->rawResult as $roomIndex => $rawResult) {
            $xml = new \SimpleXMLElement($rawResult['body']);
            $this->rawResult[$roomIndex]['body'] = json_decode(json_encode($xml->xpath('//s:Body')), true);
        }
    }

    public function requestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->object->seller_info + $auth;
        $roomIndexes = array_keys($this->object->service_info['rooms']);
        $this->multi = true;
        foreach ($roomIndexes as $roomIndex) {
            $depends['roomIndex'] = $roomIndex;
            $this->call[$roomIndex] = [
                'fields' => view('TBO.CancelationPolicies', $depends)->render(),
                'url' => $auth['url']
            ];
        }
        return $this->action();
    }

    public function extractor()
    {
        foreach ($this->rawResult as $roomIndex => $rawResult) {
            $policies = $rawResult['body'][0]['HotelCancellationPolicyResponse']['CancelPolicies'] ?? [];
            $this->repelSingleMode($policies['CancelPolicy']);
            $cancelPolicies = [];
            foreach ($policies['CancelPolicy'] as  $cancelPolicy) {
                $cancelPolicies[] = [
                    'fromDate' => $cancelPolicy['@attributes']['FromDate'],
                    'toDate' => $cancelPolicy['@attributes']['ToDate'],
                    'cancellationCharge' => $cancelPolicy['@attributes']['CancellationCharge'],
                    'currency' => $cancelPolicy['@attributes']['ChargeType'] == 'Percentage' ? null : getCurrency($cancelPolicy['@attributes']['Currency']),
                ];
            }
            $this->items[$roomIndex] = [
                'lastCancellationDeadline' => $policies['LastCancellationDeadline'] ?? "",
                'defaultPolicy' => $policies['DefaultPolicy'] ?? "",
                'autoCancellationText' => $policies['AutoCancellationText'] ?? "",
                'policies' => $cancelPolicies,
            ];
        }
    }

    protected function mapper()
    {
        $price = $this->object->price_info;
        foreach ($this->items as $roomIndex => $item) {
            $price['rooms'][$roomIndex]['cancelationPolicies'] =  $item;
        }
        $this->object->price_info = $price;
        $seller = $this->object->seller_info;
        $seller['cancelationPolicies'] = true;
        $this->object->seller_info = $seller;
        $this->object->update();
    }

    protected function haveSupplierResponseError()
    {
        foreach ($this->rawResult as $roomIndex => $rawResult) {
            preg_match('/<StatusCode>(.*?)<\/Description>/ims', $rawResult['body'], $rawErrors);
            if (empty($rawErrors[1]) || intval($rawErrors[1]) != 1) {
                ///> log the error in database
                // $this->addErrors('TBO response contain error ', $rawErrors[1] ?? $this->rawResult);
                unset($this->rawResult[$roomIndex]);
            }
        }
        return $this->checkErrors();
    }
}
