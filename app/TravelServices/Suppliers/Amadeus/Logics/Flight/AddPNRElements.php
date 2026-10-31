<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

trait AddPNRElements
{
    protected $method = 'PNR_AddMultiElements';
    public function canBeDone()
    {
        $this->supplierSession = $this->retrieveSession('sellFromRec');
        if (empty($this->supplierSession)) {
            $this->addError('Session Expired.');
            $this->addErrorToBook(trans('messages.session'));
            return false;
        }

        return true;
    }

    public function requestGenerator()
    {
        $depends = $this->headerData() + $this->getServiceInfo()['passengersCount'] + ['include' => 'AddPNRElements', 'passengers' => $this->passengersModifier(), 'session' => json_decode($this->supplierSession->extras, true)];
        lugInfo("Amadeus AddPNRElements Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.inseries', $depends)->render(),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'AddPNRElements'
        ];
        return $this->action();
    }

    protected function passengersModifier()
    {
        $passengers = $this->object->passenger_info;
        foreach ($passengers['adults'] as $adultIndex => $adult) {
            foreach ($passengers['infants'] ?? [] as $infantIndex => $infant) {
                $passengers['adults'][$adultIndex]['infantIndicator'] = strtoupper($infant['Last_name']) == strtoupper($adult['Last_name']) ? 2 : 3;
                $passengers['adults'][$adultIndex]['accompanyingInfant'] = $infant;
                unset($passengers['infants'][$infantIndex]);
                continue 2;
            }
        }
        return collect($passengers);
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded']['PNR_Reply'] ?? [];
        $travellerInfo = $result['travellerInfo'] ?? [];
        if (empty($travellerInfo)) {
            $this->addError('no travellerInfo');
            return false;
        }
        $this->repelSingleMode($travellerInfo);
        foreach ($travellerInfo as $travellers) {
            $passengerData = $travellers['passengerData'];
            $this->repelSingleMode($passengerData);
            foreach ($passengerData as $groupPassengers) {
                $passengers = $groupPassengers['travellerInformation']['passenger'];
                $this->repelSingleMode($passengers);
                foreach ($passengers as $passenger) {
                    $passengerType = $passenger['type'];
                    $passengerTypesCount[$passengerType] = ($passengerTypesCount[$passengerType] ?? 0) + 1;
                }
            }
        }
        $this->items = $passengerTypesCount;
        $serviceInfo = $this->getServiceInfo();
        if (($serviceInfo['passengersCount']['adults'] ?? 0) != ($this->items['ADT'] ?? 0)) {
            $this->addError('adult passengers count mismatch');
        }
        if (($serviceInfo['passengersCount']['children'] ?? 0) != ($this->items['CHD'] ?? 0)) {
            $this->addError('child passengers count mismatch');
        }
        if (($serviceInfo['passengersCount']['infants'] ?? 0) != ($this->items['INF'] ?? 0)) {
            $this->addError('infant passengers count mismatch');
        }
        lugInfo('Amadeus AddPNRElements extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['addPNRElements'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }

    protected function failed($error = null)
    {
        $this->failBook();
        $this->expireSupplierCache();
        $this->callSignOut('sellFromRec' . $this->object->reference_id);
        $err = [];
        foreach ($error ?? [] as $errorMessages) {
            if ($this->getErrorClass($errorMessages) != 'technical') {
                $err[] = $errorMessages;
            }
        }
        if (empty($this->getSupplierErrorMessage())) {
            $error = implode('. ', $err);
        } else {
            $error = $this->getSupplierErrorMessage();
        }
        return ['status' => false, 'message' => $error];
    }
}
