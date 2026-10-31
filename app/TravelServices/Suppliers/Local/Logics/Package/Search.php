<?php

namespace App\TravelServices\Suppliers\Local\Logics\Package;

use App\TravelServices\Suppliers\Local\Logics\Search as LogicsSearch;
use Carbon\Carbon;
use App\Models\City;
use App\Models\Landmark;

trait Search
{
    use LogicsSearch;
    protected $availabilities, $cities, $landmarks;

    ///> using $this->travelService bc tour and event search use this trait as well

    protected function availabilityQuery($availabilityQuery)
    {
        $exceptions = $this->availabilityIdsFromExceptins();
        return $availabilityQuery->when(!empty($exceptions) && !$this->seller->is_allow_all, function ($query) use ($exceptions) {
            $query->whereIn('reference_id', $exceptions);
        })->when(!empty($exceptions) && $this->seller->is_allow_all, function ($query) use ($exceptions) {
            $query->whereNotIn('reference_id', $exceptions);
        });
    }

    protected function serviceQuery($commonQuery)
    {
        if (empty($this->getAccessExceptions()) && !$this->seller->is_allow_all) {
            lugError('seller is deny_all and there are no exceptions');
            $this->addError('seller is deny_all and there are no exceptions');
            return false;
        }
        $exceptions = $this->profileIdsFromExceptins();
        return $commonQuery->when(!empty($exceptions) && !$this->seller->is_allow_all, function ($profileQuery) use ($exceptions) {
            $profileQuery->whereIn('reference_id', $exceptions);
        })->when(!empty($exceptions) && $this->seller->is_allow_all, function ($profileQuery) use ($exceptions) {
            $profileQuery->whereNotIn('reference_id', $exceptions);
        })->when(!empty($this->queryParams['destination_city']['id']), function ($profileQuery) {
            ///>  places sample => c105-8878:5;c105-8878:70;c105-4677:122;c105-4677:123;	 => c{countryID}-{cityId}:{landmarkId};
            $profileQuery->where('places', 'like', "%-{$this->queryParams['destination_city']['id']}:%");
        })->when(!empty($this->queryParams['departure_from']['id']), function ($profileQuery) {
            $profileQuery->where('departure_from', $this->queryParams['departure_from']['id']);
        })->with('city.country');
    }

    protected function getAvailabilities()
    {
        $exceptions = $this->availabilityIdsFromExceptins();
        $profileIds = $this->rawResult->pluck('id')->toArray();
        $availabilityModel = 'MatinUtils\NodeTracker\Models\\' . ucfirst($this->travelService) . 'Availability';
        $this->availabilities = $availabilityModel::whereIn($this->travelService . '_id', $profileIds)
            ->when(!empty($exceptions) && !$this->seller->is_allow_all, function ($query) use ($exceptions) {
                $query->whereIn('reference_id', $exceptions);
            })->when(!empty($exceptions) && $this->seller->is_allow_all, function ($query) use ($exceptions) {
                $query->whereNotIn('reference_id', $exceptions);
            })->when(!empty($this->queryParams['startDate']), function ($query) {
                $query->where('from', '>=', Carbon::create($this->queryParams['startDate']));
            })->where('from', '>=', Carbon::now())->get();
    }

    protected function profileIdsFromExceptins()
    {
        foreach ($this->getAccessExceptions() as $exception) {
            if (!empty($exception->conditions['profile_id'])) {
                if ($this->seller->is_allow_all == empty($exception->conditions['availability_id'])) {
                    $profileIds[] = $exception->conditions['profile_id'];
                }
            }
        }
        return $profileIds ?? [];
    }

    protected function availabilityIdsFromExceptins()
    {
        foreach ($this->getAccessExceptions() as $exception) {
            if (!empty($exception->conditions['availability_id'])) {
                $availabilityIds[] = $exception->conditions['availability_id'];
            }
        }
        return $availabilityIds ?? [];
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
            return [
                'luxotaCode' => $this->seller->luxotaCode->code,
                'query_params' => $this->queryParams,
                'package' => $profile->info + [
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
                    'subtitle' => $profile->subtitle ?? ''
                ],
                'availabilities' => array_values($availabilities)
            ];
        }
    }

    protected function getSellerInfo($profile)
    {
        return [
            'onholdable' => true,
            'registerLimit' => $profile->register_limit ?? 0, ///> only package has
            'afters' => [
                'availabilities' => false,
            ],
            'beforeBooks' => [
                'checkAvailability' => false
            ],
            'passengersForm' => $profile->passengers_form
        ];
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
            'title' => reset($item['serviceInfo']['package']['title']),
            'travel_style' => $item['attachs']['travelStyleIds'],
            'lodging_level' => $item['serviceInfo']['package']['lodgingLevel'],
            'is_group' => empty($item['serviceInfo']['package']['group_size']) ? 0 : 1,
            'is_onholdable' => $onholdable,
            'departure_from' => $item['serviceInfo']['package']['departureFrom']['id'] ?? null,
            'created_at' => $now
        ];
    }

    protected function cityLandmarks($places)
    {
        preg_match_all('/c[\d]+-(\d+):(\d+);/', $places, $output_array);
        $landmarkIds = $output_array[2];
        foreach ($landmarkIds as $landmarkId) {
            $landmark = $this->landmarks[$landmarkId];
            $landmark['city'] = $this->cities[$landmark['city_id']] ?? null;
            if (empty($landmark['city'])) continue;
            unset($landmark['city_id']);
            $CityLandmark[] = $landmark;
        }
        return $CityLandmark ?? [];
    }

    protected function extractData()
    {
        $places = $this->rawResult->pluck('places')->toArray();
        $cityIds = [];
        $landmarkIds = array_map(function ($item) use (&$cityIds) {
            preg_match_all('/c[\d]+-(\d+):(\d+);/', $item, $output_array);
            $cityIds[] = $output_array[1];
            return $output_array[2];
        }, $places);
        $landmarkIds = array_merge(...$landmarkIds);
        $cityIds = array_merge(...$cityIds);
        $this->cities = City::whereIn('id', $cityIds)->with('country', fn ($c) => $c->select('id', 'en', 'abb'))->get()->keyBy('id')->toArray() ?? null;
        $this->landmarks = Landmark::whereIn('id', $landmarkIds)->get()->keyBy('id')->toArray() ?? null;
    }

    protected function getPricingConditions($serviceInfo, $profile)
    {
        return [
            'profile_id' => $profile->reference_id,
            'luxotaCodes' => $serviceInfo['luxotaCode']
        ];
    }
}
