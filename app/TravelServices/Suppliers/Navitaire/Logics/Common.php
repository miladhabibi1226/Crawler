<?php

namespace App\TravelServices\Suppliers\Navitaire\Logics;

use App\Models\SupplierSession;
use App\TravelServices\Logics\Common as LogicsCommon;
use MatinUtils\NodeTracker\Models\AirLineSupplier;
use MatinUtils\NodeTracker\Models\SupplierService;

trait Common
{
    use LogicsCommon, Errors;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'Navitaire';
        $this->defaultHeader = ['Content-Type:application/json', "AgentID:{$this->seller->api->configs['username']}"];
        $this->dataReference = types('supplierDataReference', 'Navitaire');
        $this->supplierSearchValidity = 15;
        static $airline;
        if (empty($airline[$this->seller->supplier_service_id])) {
            $airlineId = SupplierService::select('entity_id')->find($this->seller->supplier_service_id)->entity_id;
            $airline[$this->seller->supplier_service_id] = AirLineSupplier::where('data_reference', $this->dataReference)->where('airline_id', $airlineId)->pluck('supplier_key')->first();
        }
        $this->Airline = $airline[$this->seller->supplier_service_id];
    }

    public function addPassengers()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Navitaire) - Add Passengers)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Navitaire) - Add Passengers)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Navitaire) - Add Passengers)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Navitaire) - Add Passengers)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Navitaire) - Add Passengers)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function tripSell()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Navitaire) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Navitaire) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Navitaire) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Navitaire) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Navitaire) - Trip Sell)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function payment()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Navitaire) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Navitaire) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Navitaire) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Navitaire) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Navitaire) - Payment)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function bookingDetails()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (Navitaire) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (Navitaire) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (Navitaire) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (Navitaire) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (Navitaire) - Booking Details)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function signOut()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (Navitaire-Flight) -> Sign Out', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: (Navitaire-Flight) Sign Out', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function checkForPriceChange($totalPrice)
    {
        ///> currently we will fail the booking if the price has changed
        $searchAmount = $this->object->price_info['sellerPrice']['details']['bySellerCurrency']['payable']  ?? 0;
        $this->items['searchAmount'] = round(floatval($searchAmount), 5);
        $this->items['resultAmount'] = round(floatval($this->exchangeSellerPrices($totalPrice)), 5);
        if ($this->items['searchAmount'] != $this->items['resultAmount']) {
            lugError('Price Change', $this->items + ['balanceDue' => $totalPrice, 'exchangeData' => $this->exchangeData, 'exchangedresult' => $this->items['resultAmount'], $this->object->price_info['sellerPrice']['rawPrice']]);
            $this->addError("Price Has Changed");
            $this->failBook();
            $this->addErrorToBook(trans('messages.priceChanged'));
        }
    }

    protected function authorizeRequest($session = null)
    {
        if (!empty($session)) {
            $this->supplierSession = SupplierSession::whereSession($session)->first();
            if (empty($this->supplierSession)) {
                return false;
            }
        } else {
            if (empty($this->latestSession)) {
                $this->findOrCreateSupplierSession();
            }
        }
        $this->latestSession = $this->supplierSession?->session ?? 'null';
        $this->defaultHeader[] = "Authorization:$this->latestSession";
        return $this->latestSession;
    }

    protected function exchangeSellerPrices($amount)
    {
        $exchanged = $amount * $this->exchangeData['exchangeRate'];
        $roundFactor = strlen(trim($this->exchangeData['roundingFactor'], '0')) - 1;
        return round($exchanged, $roundFactor);
    }

    protected function haveSupplierResponseError()
    {
        $rawResults = $this->multi ? $this->rawResult : [$this->rawResult];
        foreach ($rawResults as $key => $rawResult) {
            if (preg_match_all('/"errors":/', $rawResult['body'], $errors)) {
                preg_match_all('/"rawMessage": "(.*?)"/', $rawResult['body'], $rawErrors);
                if (!empty($rawErrors[1])) {
                    lugWSResError("Navitaire response contains error ({$this->seller->alias})", ['supplierError: ' => $rawErrors[1], 'supplier response: ' => $rawResult['body']]);
                    $this->supplierConversaion(implode('; ', $rawErrors[1]) ?? "Navitaire response contains error");
                    return implode('; ', $rawErrors[1]) ?? "Navitaire response contains error";
                } else {
                    preg_match_all('/"message": "(.*?)"/', $rawResult['body'], $rawErrors);
                    if (!empty($rawErrors[1])) {
                        lugWSResError("Navitaire response contains error ({$this->seller->alias})", ['supplierError: ' => $rawErrors[1], 'supplier response: ' => $rawResult['body']]);
                        $this->supplierConversaion(implode('; ', $rawErrors[1]) ?? "Navitaire response contains error");
                        return implode('; ', $rawErrors[1]) ?? "Navitaire response contains error";
                    }
                }
                $rawErrors = [];
                foreach ($rawResult['decoded']['errors'] ?? [] as $error) {
                    $rawErrors[] = implode('; ', $error);
                }
                if (!empty($rawErrors)) {
                    lugWSResError("Navitaire response contains error ({$this->seller->alias})", ['supplierError: ' => $rawErrors, 'supplier response: ' => $rawResult['body']]);
                    $this->supplierConversaion(implode('; ', $rawErrors) ?? "Navitaire response contains error");
                    return implode('; ', $rawErrors) ?? "Navitaire response contains error";
                }
            }
            if (!in_array($rawResult['http_code'], [200, 201])) {
                lugWSResError("Navitaire response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
                $this->supplierConversaion('invalid response status: ' . $rawResult['http_code']);
                return 'Navitaire response is invalid';
            }
        }
        return false;
    }
}
