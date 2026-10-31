<?php

namespace App\TravelServices\Helpers;

trait QueryParams
{
    //> this trait is used in sabre valisate as well
    public function translateQueryParams($withCityAirports = false)
    {
        ///> clean this whole thing up
        $preferedDataReference = 'Amadeus';
        $queryParams = $this->queryParams;
        list($Cities, $airports) = $this->retrieveSupplierAssets($withCityAirports, $preferedDataReference);
        foreach ($queryParams['legs'] as $legIndex => $leg) {
            ///> translating origin buffer
            $origin['isCity'] = empty($leg['origin']['airport']);
            if ($origin['isCity']) {
                $originCity = $Cities[$leg['origin']['city']['id']] ?? null;
                if (empty($originCity)) {
                    $this->callException('The selected origin city is not defined for the supplier');
                }
                if ($withCityAirports) {
                    $origin['airports'] = $originCity->airportSuppliers->pluck('supplier_key')->toArray();
                }
                $origin['abb'] = $originCity->supplier_key;
            } else {
                $origin['abb'] = $airports[$leg['origin']['airport']['id']] ?? null;
                if (empty($origin['abb'])) {
                    $this->callException('The selected origin airport is not defined for the supplier');
                }
            }
            $leg['origin'] = $origin;

            ///> translating destination buffer
            $destination['isCity'] = empty($leg['destination']['airport']);
            if ($destination['isCity']) {
                $destinationCity = $Cities[$leg['destination']['city']['id']] ?? null;
                if (empty($destinationCity)) {
                    $this->callException('The selected destination city is not defined for the supplier');
                }
                if ($withCityAirports) {
                    $destination['airports'] = $destinationCity->airportSuppliers->pluck('supplier_key')->toArray();
                }
                $destination['abb'] = $destinationCity->supplier_key;
            } else {
                $destination['abb'] = $airports[$leg['destination']['airport']['id']] ?? null;
                if (empty($destination['abb'])) {
                    $this->callException('The selected destination airport is not defined for the supplier');
                }
            }
            $leg['destination'] = $destination;
            $queryParams['legs'][$legIndex] = $leg;
        }
        ///> we might want modify the passengers count for some reason, the modified numbers are in passengersTypeCount, so we'll replace the items with the ones in the queryParams
        $queryParams = array_merge($queryParams, $this->modifiedPassengersTypeCount ?? []);
        return $queryParams;
    }

    protected function retrieveSupplierAssets($withCityAirports, $preferedDataReference)
    {
        ///> getting all cities and airport from db together, bc when we have multiple legs we don't want to do multiple repeted queries
        foreach ($this->queryParams['legs'] as $legIndex => $leg) {
            if (empty($leg['origin']['airport'])) {
                $cityIds[] = $leg['origin']['city']['id'];
            } else {
                $airportIds[] = $leg['origin']['airport']['id'];
            }
            if (empty($leg['destination']['airport'])) {
                $cityIds[] = $leg['destination']['city']['id'];
            } else {
                $airportIds[] = $leg['destination']['airport']['id'];
            }
        }

        if (!empty($cityIds)) {
            $Cities = $this->seller->getSupplierCities($preferedDataReference)
                ->whereIn('city_id', $cityIds)->when($withCityAirports, function ($q) {
                    $q->with('airportSuppliers');
                })->get()->keyBy('city_id');
        }
        if (!empty($airportIds)) {
            $airports = $this->seller->getSupplierAirports($preferedDataReference)
                ->whereIn('airport_id', $airportIds)->pluck('supplier_key', 'airport_id');
        }
        return [$Cities ?? [], $airports ?? []];
    }
}
