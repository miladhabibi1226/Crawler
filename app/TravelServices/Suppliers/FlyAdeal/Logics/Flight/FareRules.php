<?php

namespace App\TravelServices\Suppliers\FlyAdeal\Logics\Flight;

use App\TravelServices\Suppliers\Navitaire\Logics\Flight\FareRules as NavitaireFareRules;

trait FareRules
{
    use NavitaireFareRules;
    protected function getFareRulesUrl()
    {
        return '/api/nsk/v1/fareRules/';
    }

    public function extractor()
    {
        foreach ($this->object->seller_info['bookingData'] as $fare) {
            $fareRulesResult = $this->rawResult[$fare['fareAvailabilityKey']]['decoded']['data'] ?? [];
            if (empty($fareRulesResult)) {
                continue;
            }
            $item = [];
            $item['group'] = [
                'airline' => 'F3',
                'airports' => $fare['origin'] . ' - ' . $fare['destination']
            ];
            foreach ($fareRulesResult as $result) {
                $item['details'][] = [
                    'title' => 'Rules',
                    'text' => $result['content'] ?? '',
                ];
            }
            $this->items[] = $item;
        }
        // lugInfo('FlyAdeal FareRules extractor Items', ['Items' => $this->items]);
    }
}
