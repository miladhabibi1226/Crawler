<?php

namespace App\TravelServices\Logics\Flight;

use App\TravelServices\Logics\AfterSearch;
use App\TravelServices\Logics\Availabilities;
use Carbon\Carbon;
use MatinUtils\NodeTracker\Models\ServiceInfo;

trait Brands
{
    use AfterSearch, Availabilities;
    protected $items = [];

    public function extractor()
    {
        $results  = $this->rawResult['decoded']['data'] ?? [];
        if (empty($results)) {
            lugError('Empty brand result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('response is invalid');
            return false;
        }
        foreach ($results as $legIndex => $legBrands) {
            foreach ($this->legBrands($legBrands) as $brand) {
                $index = $this->getBrandIndex($brand);
                $this->items['serviceInfo'][$legIndex][] = [
                    'index' => $index,
                    'title' => $brand['title'],
                    'services' => $this->getBrandServices($brand)
                ];
                $priceInfo[$index] = $this->getRawBrandPrice($brand);
            }
        }
        if (!empty($this->items)) {
            $this->items['priceInfo'] = $this->priceModifier($priceInfo);
            if (empty($this->items['priceInfo'])) {
                return $this->addError('Pricing Info Unavailable');
            }
        }
        $this->items['sellerInfo'] = $this->sellerInfoData($results);
        lugInfo('BrandedFares extractor Items', ['items' => $this->items]);
    }

    protected function sellerInfoData($results)
    {
        $sellerInfo['bookingRequirements'] = ['brands' => !empty($this->items)];
        return $sellerInfo;
    }

    protected function priceModifier($rawPrice)
    {
        ///? quantum supplier doesm't use this
        return $this->calculatePriceforAvailabilities([
            'priceInfo' => $rawPrice,
            'conditions' => $this->object->price_info['exceptionInfo']
        ]);
    }

    protected function mapper()
    {
        $sellerInfo = array_merge($this->object->seller_info,  $this->items['sellerInfo']);
        $sellerInfo['afters']['brands'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->price_info = array_merge($this->object->price_info,  ['brands' => $this->items['priceInfo'] ?? []]);
        $this->object->update();
        if (!empty($this->items['serviceInfo'])) {
            ServiceInfo::insert([
                'reference_id' => $this->object->service_info,
                'type' => 'brands',
                'data' => json_encode($this->items['serviceInfo']),
                'created_at' => Carbon::now()
            ]);
        }
    }
}
