<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\Flight\Meals as FlightMeals;

trait Meals
{
    use FlightMeals;
    protected $responseSample = 'Samples/CloudFares/FlightMeals.json';
    protected function sessionHandling()
    {
        if (!$this->object->seller_info['bookingRequirements']['brands']) {
            return $this->addError('No Brands available');
        }
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        foreach (request('brands', []) as $brandindex) {
            if ($this->object->seller_info['brands'][$brandindex]['meals']) {
                $brandIndexes[] = $brandindex;
            }
        }
        if (empty($brandIndexes)) {
            $this->addError('None of the brands have meals');
            return false;
        }
        $depends = $this->seller->api->configs + $this->object->seller_info + ['brandIndexes' => $brandIndexes, 'request' => request()->all()];
        lugInfo('Meals data', [$depends]);
        $this->call = [
            'fields' => json_encode(["trace_id" => $depends['trace_id'], "result_id" => $depends['result_id'], "brands_id" => $brandIndexes]),
            'method' => 'POST',
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/meal-map',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Meals'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $results  = $this->rawResult['decoded']['data']['meals'] ?? [];
        if (empty($results)) {
            lugError('Empty meals result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('response is invalid');
            return false;
        }
        foreach ($results as $flight) {
            list($flightNumber, $legIndex, $segIndex) = $this->getFlightNumber($flight);
            $sellerInfo[$legIndex][$segIndex] = $flight['segment_ref'];
            $leg = ['label' => $flight['label'], 'flightNumber' => $flightNumber];
            foreach ($flight['meal_detail'] ?? [] as $mealGroup) {
                foreach ($mealGroup['meal_item_detail'] as $meal) {
                    $index = $this->getMealIndex($meal);
                    $leg['items'][] = [
                        'index' => $index,
                        'name' => $meal['name'],
                        'description' => $meal['description'],
                        'category' => $mealGroup['category'],
                        'available' => $meal['available_meal'],
                        'image' => $meal['image']
                    ];
                    $priceInfo[$index] = $this->getRawMealPrice($meal);
                }
            }
            $this->items['serviceInfo'][$legIndex][$segIndex] = $leg;
        }
        if (!empty($this->items)) {
            $this->items['priceInfo'] = $this->priceModifier($priceInfo);
            if (empty($this->items['priceInfo'])) {
                return $this->addError('Pricing Info Unavailable');
            }
        }
        $this->items['sellerInfo']['mealsSegments'] = $sellerInfo;
        $this->items['sellerInfo']['selectedBrands'] = request('brands');
        lugInfo('Meals extractor Items', ['items' => $this->items]);
    }

    protected function getMealIndex($meal)
    {
        return $meal['meal_id'];
    }

    protected function getRawMealPrice($meal)
    {
        return ['total' => $meal['price']['total']];
    }
}
