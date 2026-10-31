<?php

namespace App\TravelServices\Logics\Flight;

use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\ServiceInfo;

trait Meals
{
    public function canBeDone()
    {
        if ($this->needsSessionHandling() && !$this->sessionHandling()) {
            $this->addError('Unable to Get Session');
            return false;
        }
    }

    protected function getContent()
    {
        // $this->fromFile = 1;
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path($this->responseSample));
            $this->rawResult['http_code'] = 200;
        } else {
            if ($this->requestGenerator() === false) {
                $this->addError("Supplier connection error");
                return false;
            }
        }

        $this->decodeRawResult();

        if ($this->haveSupplierResponseError()) {
            $this->addError("Supplier has error");
            return false;
        }
    }

    protected function priceModifier($rawPrice)
    {
        return $this->calculatePrice(['priceInfo' => $rawPrice]);
    }

    protected function decodeRawResult() ///> json suppliers use this, XML suppliers rewrite this
    {
        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
    }

    protected function needsSessionHandling()
    {
        return !in_array($this->supplierName, ['Local', 'Quantum', 'Avtra', 'GRS', 'Faranegar', 'Nira', 'Travelport', 'IRIX']);
    }

    protected function calculatePrice($items)
    {
        ///> by management's request, no rules or margins will apply to meals and seats's price
        $this->seller->currency; ///> to load currency object, to send with seller
        $priceCalculatorata = $items + [
            'queryParams' => $this->queryParams,
            'seller' => $this->seller,
            'nodeCurrency' => node()->currency,
            'exchanges' => $this->getExchanges()
        ];
        $itemsPriceInfo = dataFromMSS('priceCalculator/supplier/flight/meals', $priceCalculatorata);
        return $itemsPriceInfo ?? [];
    }

    protected function mapper()
    {
        $sellerInfo = array_merge($this->object->seller_info, $this->items['sellerInfo']) ;
        $sellerInfo['afters']['meals'] = true;
        $sellerInfo['bookingRequirements']['meals'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->price_info = array_merge($this->object->price_info,  ['meals' => $this->items['priceInfo'] ?? []]);
        $this->object->update();
        if (!empty($this->items['serviceInfo'])) {
            ServiceInfo::insert([
                'reference_id' => $this->object->service_info,
                'type' => 'meals',
                'data' => json_encode($this->items['serviceInfo']),
                'created_at' => Carbon::now()
            ]);
        }
    }
}
