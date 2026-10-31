<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\Flight\Brands as FlightBrands;

trait Brands
{
    use FlightBrands {
        FlightBrands::sellerInfoData as commonSellerInfoData;
    }
    protected $responseSample = 'Samples/CloudFares/FlightBrandedFare.json';
    protected function sessionHandling()
    {
        return $this->findOrCreateSupplierSession();
    }

    public function requestGenerator()
    {
        $depends = $this->seller->api->configs + $this->object->seller_info;
        lugInfo('BrandedFares data', [$depends]);
        $this->call = [
            'fields' => json_encode(["trace_id" => $depends['trace_id'], "result_id" => $depends['result_id']]),
            'method' => 'POST',
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/get-brand-fares',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'BrandedFares'
        ];

        return $this->action();
    }

    protected function sellerInfoData($results)
    {
        $sellerInfo = $this->commonSellerInfoData($results);
        foreach ($results as $legIndex => $legBrands) {
            foreach ($this->legBrands($legBrands) as $brand) {
                $sellerInfo['brands'][$this->getBrandIndex($brand)] = [
                    'meals' => $brand['ancillary_service']['available_meal'],
                    'seats' => $brand['ancillary_service']['available_seat']
                ];
            }
        }
        return $sellerInfo;
    }


    protected function legBrands($legInfo)
    {
        return $legInfo['brand_legs'] ?? [];
    }

    protected function getBrandIndex($brand)
    {
        return $brand['brand_id'] ?? [];
    }

    protected function getBrandServices($brand)
    {
        if (empty($brand['fare_services'])) {
            return [];
        }
        foreach ($brand['fare_services'] as $service) {
            if (in_array($service['InclusionType'], ['1', 'included'])) {
                $title = $service['Title'];
                if (strlen($service['Description'] ?? '') > 1) {
                    $title .= ": {$service['Description']}";
                }
                $services[] = $title;
            }
        }
        return $services ?? [];
    }

    protected function getRawBrandPrice($brand)
    {
        $rawPriceFromSupplier = $brand['price'];
        ///> cloudFares doesn't send perPassenger amounts for brands so we have to calculate it ourself
        list($breakDowns['adult']['base'], $breakDowns['child']['base'], $breakDowns['infant']['base']) = $this->calculatePerPassenger($rawPriceFromSupplier['base']);
        list($breakDowns['adult']['tax'], $breakDowns['child']['tax'], $breakDowns['infant']['tax']) = $this->calculatePerPassenger($rawPriceFromSupplier['taxes']);
        list($breakDowns['adult']['total'], $breakDowns['child']['total'], $breakDowns['infant']['total']) = $this->calculatePerPassenger($rawPriceFromSupplier['total']);
        $totalToCheck = $baseToCheck = 0;
        foreach ($breakDowns as $passengerType => $priceDetails) {
            $typeCount = $this->queryParams[['adult' => 'adults', 'child' => 'children', 'infant' => 'infants'][$passengerType]];
            if ($typeCount < 1) {
                unset($breakDowns[$passengerType]);
                continue;
            }
            $PriceBreakDowns[$passengerType]  = [
                'baseFare' => round($priceDetails['base'], 2),
                'tax' => round($priceDetails['tax'], 2),
                'totalFare' => round($priceDetails['total'], 2),
                'commission' => 0,
                'payable' => round($priceDetails['total'], 2),
                'passengerTypeTotal' => [
                    'baseFare' => $b = round($priceDetails['base'], 2) * $typeCount,
                    'tax' => round($priceDetails['tax'], 2) * $typeCount,
                    'totalFare' => round($priceDetails['total'], 2) * $typeCount,
                    'commission' => 0,
                    'payable' => $t = round($priceDetails['total'], 2) * $typeCount,
                    'passengersCount' => $typeCount,
                ]
            ];
            $totalToCheck += $t;
            $baseToCheck += $b;
        }
        if (abs(($totalToCheck) - $rawPriceFromSupplier['total']) > 1 || abs($baseToCheck != $rawPriceFromSupplier['base']) > 1) {
            lugError('Error in calculation', ['totalToCheck' => $totalToCheck, 'baseToCheck' => $baseToCheck, 'rawPriceFromSupplier' => $rawPriceFromSupplier, 'og breakDowns' => $breakDowns, 'final BreakDowns' => $PriceBreakDowns]);
            $this->addError('Error in calculation');
        }
        return [
            'breakDowns' => $PriceBreakDowns,
            'payable' => $rawPriceFromSupplier['total'],
            'baseFare' => $rawPriceFromSupplier['base'],
            'diff' => $rawPriceFromSupplier['total'] - $rawPriceFromSupplier['base'],
            'raw' => $rawPriceFromSupplier,
            'note' => 'breakDowns are not provided by the supplier, they are calculated by us'
        ];
    }

    protected function calculatePerPassenger($amount)
    {
        ///> by management's order this is how perpassenger amount will be calculated: Chaild 70% of ADT and Infant 20%
        $adult = $amount / ($this->queryParams['adults'] + (0.7 * $this->queryParams['children']) + (0.2 * $this->queryParams['infants']));
        return [$adult, 0.7 * $adult, 0.2 * $adult];
    }
}
