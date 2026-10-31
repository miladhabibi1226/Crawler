<?php

namespace App\TravelServices\Suppliers\Local\Logics\Tour;

use App\TravelServices\Suppliers\Local\Logics\Package\Availabilities as PackageAvailabilities;
use Carbon\Carbon;

trait Availabilities
{
    use PackageAvailabilities;
    protected function getContent()
    {
        $availabilityCollumnName = "{$this->travelService}_id";
        $bufferCollumnName = "{$this->travelService}_profile_id";
        $ts = ucfirst($this->travelService);
        $modelAvailability = "MatinUtils\NodeTracker\Models\\{$ts}Availability";
        $this->rawResult = $modelAvailability::where($availabilityCollumnName, $this->object->$bufferCollumnName)
            ->where('from', '>=', Carbon::now()->addDays($this->object->seller_info['registerLimit'] ?? 0))->get();
    }

    protected function extractor()
    {
        $this->items['priceInfo'] = $this->priceModifier($this->rawResult);

        $this->items['serviceInfo'] = $this->rawResult;
        lugInfo('Local availabilities extractor items', ['Items' => $this->items]);
    }

    protected function priceModifier($availabilities)
    {
        ///> no need to exchange prices, because local supplier's currency is node currency
        $availabilities = $availabilities->toArray();
        foreach ($availabilities as $key => $availability) {
            $availabilities[$key] = [
                "id" => $availability['id'],
                "adult_price" => $availability['adult_price'],
                "child_price" => $availability['child_price'],
                "infant_price" => $availability['infant_price'],
                "old_price" => $availability['old_price']
            ];
        }

        return $this->calculatePriceforAvailabilities([
            'priceInfo' => $availabilities,
        ]);
    }
}
