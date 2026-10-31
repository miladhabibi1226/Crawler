<?php

namespace App\TravelServices\Suppliers\IRIX\Logics\Hotel;

use App\TravelServices\Handler;
use Carbon\Carbon;

trait BookAndIssue
{
    
    public function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path('Samples/IRIX/HotelSearch.json'));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                $this->failBook();
                return false;
            }
        }
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            $this->failBook();
            return false;
        }
    }
    
    public function requestGenerator()
    {
        $depends = ['serviceInfo' => $this->object->service_info] + ['sellerInfo' => $this->object->seller_info] + $this->seller->api->configs + ['passengers' => $this->object->passenger_info];
        lugInfo("IRIX Hotel Book Data ({$this->seller->alias})", [$depends, $this->queryParams]);
        $offerIndex = explode(':', $depends['serviceInfo']['selectedCombination'][0]['code'])[0];
        $this->call = [
            'fields' => json_encode($this->getFields($depends)),
            'method' => "POST",
            'url' =>  "{$depends['url']}/api/hotels/v1/search/results/{$depends['sellerInfo']['srkToken']}/hotels/{$depends['sellerInfo']['hotelCode']}/offers/{$offerIndex}/book?token={$depends['sellerInfo']['resultsToken']}",
            'apiMethod' => 'HotelBook'
        ];
        return $this->action();
    }

    public function extractor()
    {
        $result = $this->rawResult['decoded'] ?? $this->addError('Book response is empty');
        if (empty($result['id'])) {
            $this->failBook();
            return $this->addError('IRIX book response doese not contain id');
        }
        $this->items['bookingResult'] = [
            'pnr' => $result['id'],
            'issued_at' => Carbon::now(),
        ];
        lugInfo('IRIX Hotel Book extractor Items', ['items' => $this->items]);
    }

    protected function getFields($depends)
    {
        ///> passengers
        $roomsTokens = "";
        $rooms = [];
        $counter = 0;
        foreach ($this->object->price_info['selectedCombination']['sellerPrice']['rawPrice']['roomTokens'] as $index => $selectedRooms) {
            $roomsTokens = $selectedRooms;
            $travelers = [];
            foreach ($depends['passengers'][$index] as $passengerType => $passengerCount) {
                foreach ($passengerCount as $pass => $passenger) {
                    $travelers[] = [
                        "reference" => "{$counter}1-{$pass}1",
                        "type" => $passengerType,
                        "lead" => ($passengerType == 'adult' && $index == 0 && $pass == 0) ? true : false,
                        "title" => strtolower($passenger['title']),
                        "firstName" => $passenger['First_name'],
                        "lastName" => $passenger['Last_name'],
                        "birthDate" => $passenger['Date of birth']
                    ];
                    if ($passengerType == 'adult' && $index == 0 && $pass == 0) {
                        $travelers[0] += [
                            "email" => $depends['passengers'][0]['adult'][0]['Email']
                        ];
                    }
                }
                $counter++;
            }
            $rooms[] = [
                "packageRoomToken" => $roomsTokens,
                "travelers" => $travelers
            ];
        }
        return [
            "clientRef" => $this->object['reference_id'],
            "availabilityToken" => $depends['sellerInfo']['availabilityToken'],
            "payment" => [
                "method" => "credit"
            ],
            "rooms" => $rooms
        ];
    }

    protected function mapper()
    {
        ///> 1- voucher remarks info that we put in seller_info in previous api(availability and pricing),
        ///> 2- we should transfer remarks from seller_info into booking_info and remove it from seller_info in this api(book api)
        $this->items['bookingResult']['remarks'] = $this->object->seller_info['remarks'];
        $this->object->booking_info = $this->items['bookingResult'];
        $newSellerInfoAfterRemoveRemarks = $this->removeRemarksFromSellerInf($this->object->seller_info);
        $this->object->seller_info = $newSellerInfoAfterRemoveRemarks;
        $this->object->status = 'issued';
        $this->object->booked_at = Carbon::now();
        $this->object->pnr = $this->items['bookingResult']['pnr'] . ':';
        $this->object->update();
        $this->itemIssued($this->items['bookingResult']['pnr'] ?? []);
    }

    protected function removeRemarksFromSellerInf($sellerInfo) {
        $newSellerInfo = [];
        foreach($sellerInfo as $index => $info) {
            if($index != 'remarks')  {
                $newSellerInfo[$index] = $info;
            }
        }
        return $newSellerInfo;
    }

    protected function haveSupplierResponseError()
    {
        $rawError = $this->rawResult['decoded']['status'] == 'OK'? true:false;
        if (!$rawError) {
            lugWSResError("IRIX HotelBook response contains error ({$this->seller->alias})", ['supplierError: ' => $this->rawResult['decoded'], 'supplier response: ' => $this->rawResult['body']]);
            $this->supplierConversaion($this->rawResult['decoded']);
            return $this->rawResult['decoded']['detail'] ?? 'Error Happen';
        }
        $this->supplierConversaion();
        return false;
    }

    protected function done()
    {
        $this->sellerTransaction();
        return ['status' => true];
    }
}
