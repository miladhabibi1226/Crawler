<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics;

use App\TravelServices\Suppliers\Navitaire\Logics\Common as NavitaireCommon;
use MatinUtils\NodeTracker\Models\AirLineSupplier;
use MatinUtils\NodeTracker\Models\SupplierService;

trait Common
{
    use NavitaireCommon;

    public function __construct($action, $licence, $object, $travelService = null)
    {
        parent::__construct($action, $licence, $object, $travelService);
        $this->supplierName = 'FlyAdeal';
        $this->defaultHeader = ['Content-Type:application/json', "Ocp-Apim-Subscription-Key:{$this->seller->api->configs['subscriptionKey']}"];
        $this->dataReference = types('supplierDataReference', 'FlyAdeal');
        $this->supplierSearchValidity = 15;
        static $airline;
        if (empty($airline[$this->seller->supplier_service_id])) {
            $airlineId = SupplierService::select('entity_id')->find($this->seller->supplier_service_id)->entity_id;
            $airline[$this->seller->supplier_service_id] = AirLineSupplier::where('data_reference', $this->dataReference)->where('airline_id', $airlineId)->pluck('supplier_key')->first();
        }
        $this->Airline = $airline[$this->seller->supplier_service_id];
    }

    public function AddInfantDocuments()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (FlyAdeal) - Add Infant Documents)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (FlyAdeal) - Add Infant Documents)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (FlyAdeal) - Add Infant Documents)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (FlyAdeal) - Add Infant Documents)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (FlyAdeal) - Add Infant Documents)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function tripSell()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (FlyAdeal) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (FlyAdeal) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (FlyAdeal) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (FlyAdeal) - Trip Sell)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (FlyAdeal) - Trip Sell)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function payment()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (FlyAdeal) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (FlyAdeal) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errorAddInfantDocumentss);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (FlyAdeal) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (FlyAdeal) - Payment)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (FlyAdeal) - Payment)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function bookingDetails()
    {
        $this->canBedone();
        if ($this->checkErrors()) {
            lugError('Failed: CanBeDone ' . $this->seller->alias . ' (FlyAdeal) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Failed: Get content ' . $this->seller->alias . ' (FlyAdeal) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->extractor();
        if ($this->checkErrors()) {
            lugError('Failed: Extractor ' . $this->seller->alias . ' (FlyAdeal) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        $this->mapper();
        if ($this->checkErrors()) {
            lugError('Failed: Mapper ' . $this->seller->alias . ' (FlyAdeal) - Booking Details)', ['Data' => $this->getErrors(), 'object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: ' . $this->seller->alias . ' (FlyAdeal) - Booking Details)', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    public function signOut()
    {
        $this->getContent();
        if ($this->checkErrors()) {
            lugError('Get Content ' . $this->seller->alias . ' (FlyAdeal-Flight) -> Sign Out', ['Data' => $this->getErrors(), 'Object' => $this->object->toArray()]);
            return $this->failed($this->errors);
        }

        lugInfo('Done: (FlyAdeal-Flight) Sign Out', ['Data' => $this->object->toArray()]);
        return $this->done();
    }

    protected function haveSupplierResponseError()
    {
        $rawResults = $this->multi ? $this->rawResult : [$this->rawResult];
        foreach ($rawResults as $key => $rawResult) {
            if (preg_match_all('/"errors":/', $rawResult['body'], $errors)) {
                preg_match_all('/"rawMessage":"(.*?)"/', $rawResult['body'], $rawErrors);
                if (!empty($rawErrors[1])) {
                    lugWSResError("FlyAdeal response contains error ({$this->seller->alias})", ['supplierError: ' => $rawErrors[1], 'supplier response: ' => $rawResult['body']]);
                    $this->supplierConversaion(implode('; ', $rawErrors[1]) ?? "FlyAdeal response contains error");
                    return implode('; ', $rawErrors[1]) ?? "FlyAdeal response contains error";
                } else {
                    preg_match_all('/"message":"(.*?)"/', $rawResult['body'], $rawErrors);

                    if (!empty($rawErrors[1])) {
                        lugWSResError("FlyAdeal response contains error ({$this->seller->alias})", ['supplierError: ' => $rawErrors[1], 'supplier response: ' => $rawResult['body']]);
                        $this->supplierConversaion(implode('; ', $rawErrors[1]) ?? "FlyAdeal response contains error");
                        return implode('; ', $rawErrors[1]) ?? "FlyAdeal response contains error";
                    }
                }
                $rawErrors = [];
                foreach ($rawResult['decoded']['errors'] ?? [] as $error) {
                    $rawErrors[] = $error['rawMessage'] ?? $error['Message'];
                }
                if (!empty($rawErrors)) {
                    lugWSResError("FlyAdeal response contains error ({$this->seller->alias})", ['supplierError: ' => $rawErrors, 'errors: ' => $rawErrors]);
                    $this->supplierConversaion(implode('; ', $rawErrors) ?? "FlyAdeal response contains error");
                    return implode('; ', $rawErrors) ?? "FlyAdeal response contains error";
                }
            }
            if (!in_array($rawResult['http_code'], [200, 201])) {
                lugWSResError("FlyAdeal response is invalid ({$this->seller->alias})", ['rawResult' => $rawResult]);
                $this->supplierConversaion('invalid response status: ' . $rawResult['http_code']);
                return 'FlyAdeal response is invalid';
            }
        }
        return false;
    }
}
