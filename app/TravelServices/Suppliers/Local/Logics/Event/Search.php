<?php

namespace App\TravelServices\Suppliers\Local\Logics\Event;

use App\TravelServices\Suppliers\Local\Logics\Package\Search as PackageSearch;

trait Search
{
    use PackageSearch;
    protected function serviceQuery($commonQuery)
    {
        return $commonQuery->when(!empty($this->queryParams['destination_city']['id']), function ($profileQuery) {
            $profileQuery->where('city_id',  $this->queryParams['destination_city']['id']);
        });
    }

    protected function getBufferData($item, $onholdable, $now)
    {
        return [
            'reference_id' => createReferenceId(),
            'search_query_id' => $this->object->id,
            'seller_id' => $this->seller->id,
            'event_profile_id' => $item['attachs']['profileId'],
            'seller_info' => json_encode($item['sellerInfo']),
            'service_info' => json_encode($item['serviceInfo']),
            'price_info' => json_encode($item['priceInfo']),
            'currency_id' => $this->queryParams['marketplace_currency']['id'],
            'total_b2b' => 0, ///> plugin will fill this
            'total_b2c' => 0, ///> plugin will fill this
            'title' => $item['serviceInfo']['title'],
            'travel_style' => $item['attachs']['travelStyleIds'],
            'landmark_id' => $item['serviceInfo']['landmark']['id'],
            'is_group' => empty($item['serviceInfo']['group_size']) ? 0 : 1,
            'is_onholdable' => $onholdable,
            'created_at' => $now,
        ];
    }

    protected function serviceModifier($profile)
    {
        $travelStyles = [];
        $travelStyleIds = explode(',', $profile->travel_style);
        foreach ($travelStyleIds as $travelStyleId) {
            $travelStyles[$travelStyleId] = types('eventTravelStyle', $travelStyleId);
        }
        $triplenght = [
            'minutes' => $profile->trip_lenght,
            'hours' => $profile->trip_lenght > 60 ?  $profile->trip_lenght / 60 : 0,
            'days' => $profile->trip_lenght >= 1440 ?  $profile->trip_lenght / 1440 : 0
        ];

        return $profile->info + [
            'luxotaCode' => $this->seller->luxotaCode->code,
            'referenceId' => $profile->reference_id,
            'title' => $profile->title,
            'description' => $profile->description,
            'itinerary' => $profile->itinerary,
            'ageRange' => $profile->age_range,
            'group_size' => $profile->group_size,
            'minParticipants' => $profile->min_participants,
            'travelStyle' => $travelStyles,
            'tripLenght' => $triplenght,
            'images' => $profile->images,
            'guiding_method' => types('eventGuidingMethod', $profile->guiding_method),
            'landmark' => $this->getLandmark($profile->landmark_id),
            'city' => $this->getCityWithCountry($profile->city_id),
            'operator' => $this->getUser($profile->operator)->search_title,
            'availabilities' => array_values($this->availabilities->where('event_id', $profile->id)->take(4)->toarray()),
            'query_params' => $this->queryParams
        ];
    }
}
