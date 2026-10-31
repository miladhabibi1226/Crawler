<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

use App\TravelServices\Logics\Flight\Seats as FlightSeats;

trait Seats
{
    use FlightSeats;
    protected $responseSample = 'Samples/CloudFares/FlightSeats.json';
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
            if ($this->object->seller_info['brands'][$brandindex]['seats']) {
                $brandIndexes[] = $brandindex;
            }
        }
        $depends = $this->seller->api->configs + $this->object->seller_info + ['brandIndexes' => $brandIndexes ?? [], 'request' => request()->all()];
        lugInfo('Seats data', [$depends]);
        if (empty($brandIndexes)) {
            $this->addError('None of the brands have seats');
            return false;
        }
        $this->call = [
            'fields' => json_encode(["trace_id" => $depends['trace_id'], "result_id" => $depends['result_id'], "brands_id" => $brandIndexes]),
            'method' => 'POST',
            'url' => $this->cleanUrlSlash($depends['url']) . '/api/b2b/v1/flight/seat-map',
            'headers' => ['Authorization:Bearer ' . $this->latestSession],
            'apiMethod' => 'Seats'
        ];

        return $this->action();
    }

    public function extractor()
    {
        $results  = $this->rawResult['decoded']['data']['seat_map'] ?? [];
        if (empty($results)) {
            lugError('Empty seats result', ['$this->rawResult[decoded]' => $this->rawResult['decoded']]);
            $this->addError('response is invalid');
            return false;
        }
        foreach ($results as $flight) {
            list($flightNumber, $legIndex, $segIndex) = $this->getFlightNumber($flight);
            if ($flightNumber != $flight['flight_number']) {
                lugError("flightNumber mismatch", [$flightNumber, $flight, $this->getServiceInfo(), $legIndex, $segIndex]);
                return $this->addError('Invalid response');
            }
            $sellerInfo[$legIndex][$segIndex] = $flight['segment_ref'];
            $seatMap[$legIndex][$segIndex] = [
                'title' => $flight['label'],
                'flightNumber' => $flight['flight_number'],
                'columns' => $flight['columns_name']
            ];
            foreach ($flight['rows'] as $rowIndex => $row) {
                $seatMap[$legIndex][$segIndex]['rows'][$rowIndex] = ['rowNumber' => $row['row_number']];
                foreach ($row['columns'] as $colIndex => $seat) {
                    $index = $seat['seat_code'];
                    $priceInfo[$flight['flight_number']][$row['row_number']][$index] = $this->getRawSeatPrice($seat);
                    $seatMap[$legIndex][$segIndex]['rows'][$rowIndex]['seats'][$colIndex] = [
                        'index' => $index,
                        'column' => $seat['column'],
                        'status' => empty($seat['availability']) ? 'unavailable' : $seat['availability'], ///> reserved / available / ""
                        'availableForAdults' => $seat['can_select_for_adt'],
                        'availableForChildren' => $seat['can_select_for_chd'],
                        'availableForInfants' => $seat['can_select_for_inf'],
                    ];
                }
            }
        }
        $this->items['serviceInfo'] = $seatMap;
        $this->items['priceInfo'] = $this->priceModifier($priceInfo);
        if (empty($this->items['priceInfo'])) {
            return $this->addError('Pricing Info Unavailable');
        }
        $this->items['sellerInfo']['seatsSegments'] = $sellerInfo;
        $this->items['sellerInfo']['selectedBrands'] = request('brands');
        lugInfo('Seats extractor Items', ['items' => $this->items]);
    }

    protected function getRawSeatPrice($seat)
    {
        $priceInfo = ['adult' => $seat['adult']['price']['total']];
        if (!empty($this->queryParams['children'])) {
            $priceInfo['child'] = $seat['child']['price']['total'];
        }
        if (!empty($this->queryParams['infants'])) {
            $priceInfo['infant'] = $seat['infant']['price']['total'];
        }
        return $priceInfo;
    }
}
