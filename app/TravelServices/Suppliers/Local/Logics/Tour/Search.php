<?php

namespace App\TravelServices\Suppliers\Local\Logics\Tour;

use App\TravelServices\Suppliers\Local\Logics\Package\Search as PackageSearch;
use Carbon\Carbon;

trait Search
{
    use PackageSearch;
    protected function serviceQuery($commonQuery)
    {
        return $commonQuery->when(!empty($this->queryParams['destination_city']['id']), function ($profileQuery) {
            $profileQuery->where('places', 'like', "%{$this->queryParams['destination_city']['id']}:%"); ///> wrong. if destination_city=15, Result=> 15: 115: 215: 315:...
        });
    }

    protected function cityLandmarks($places)
    {
        $places = explode(';', $places);
        foreach ($places as $city) {
            $value = explode(':', $city); //> val[0] = cityId, val[1]=lndmarkIds
            $cityObject = $this->getCityWithCountry($value[0]);
            if (empty($value[1])) {
                $cityObject['landmarks'] = '';
            } else {
                $landmarkIds = explode(',', $value[1]);
                foreach ($landmarkIds as $landmarkId) {
                    $landmarks = $this->getLandmark($landmarkId) ?? null;
                }
                $cityObject['landmarks'] = $landmarks;
            }
            $CityLandmark[] = $cityObject;
        }
        return $CityLandmark;
    }

    protected function serviceModifier($profile)
    {
        $availabilities = $this->availabilities->where($this->travelService . '_id', $profile->id)->where('from', '>', Carbon::now()->addDays($profile->register_limit ?? 0)->format('Y-m-d'))->take(4)->toarray();
        if (!empty($availabilities)) {
            $travelStyles = [];
            $travelStyleIds = explode(',', $profile->travel_style);
            foreach ($travelStyleIds as $travelStyleId) {
                $travelStyles[$travelStyleId] = types($this->travelService . 'TravelStyle', $travelStyleId);
            }
            $triplenght = [
                'minutes' => $profile->trip_lenght,
                'hours' => $profile->trip_lenght > 60 ?  $profile->trip_lenght / 60 : 0,
                'days' => $profile->trip_lenght >= 1440 ?  $profile->trip_lenght / 1440 : 0
            ];
            return $profile->info + [
                'luxotaCode' => $this->seller->luxotaCode->code,
                'query_params' => $this->queryParams,
                'referenceId' => $profile->reference_id,
                'title' => $profile->title,
                'description' => $profile->description,
                'itinerary' => $profile->itinerary,
                'ageRange' => $profile->age_range,
                'group_size' => $profile->group_size,
                'minParticipants' => $profile->min_participants,
                'lodgingLevel' => $profile->lodging_level,
                'travelStyle' => $travelStyles,
                'tripLenght' => $triplenght,
                'images' => $profile->images,
                'guiding_method' => types($this->travelService . 'GuidingMethod', $profile->guiding_method),
                'places' => $this->cityLandmarks($profile->places),
                'document' => $profile->document ?? '',
                'featuredImage' => $profile->featured_image ?? '',
                'operator' => $this->getUser($profile->operator)->search_title,
                'departureFrom' => $profile->city?->toArray() ?? [],
                'subtitle' => $profile->subtitle ?? '',
                'availabilities' => array_values($availabilities)
            ];
        }
    }

    protected function getBufferData($item, $onholdable, $now)
    {
        return [
            'reference_id' => createReferenceId(),
            'search_query_id' => $this->object->id,
            'seller_id' => $this->seller->id,
            $this->travelService . '_profile_id' => $item['attachs']['profileId'],
            'seller_info' => json_encode($item['sellerInfo']),
            'service_info' => json_encode($item['serviceInfo']),
            'price_info' => json_encode($item['priceInfo']),
            'currency_id' => $this->queryParams['marketplace_currency']['id'],
            'total_b2b' => 0, ///> plugin will fill this
            'total_b2c' => 0, ///> plugin will fill this
            'title' => $item['serviceInfo']['title'],
            'travel_style' => $item['attachs']['travelStyleIds'],
            'lodging_level' => $item['serviceInfo']['lodgingLevel'],
            'start_city' => $item['serviceInfo']['places'][0]['id'] ?? 0,
            'end_city' => $item['serviceInfo']['places'][count($item['serviceInfo']['places']) - 1]['id'] ?? 0,
            'is_group' => empty($item['serviceInfo']['group_size']) ? 0 : 1,
            'is_onholdable' => $onholdable,
            'created_at' => $now,
        ];
    }
}
