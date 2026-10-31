<?php

namespace App\TravelServices\Suppliers\Local\Logics\Visa;

use App\TravelServices\Suppliers\Local\Logics\Search as LogicsSearch;
use MatinUtils\NodeTracker\Models\VisaProfile;

trait Search
{
    use LogicsSearch;

    protected function regularSearch($onlyFeatured = false)
    {
        $this->rawResult = VisaProfile::whereSellerId($this->seller->id)->whereIsPublished(1)
            ->when(!empty($this->queryParams['country']['id']), function ($query) {
                $query->whereCountryId($this->queryParams['country']['id']);
            })->when(!empty($this->queryParams['passport_country']['id']), function ($query) {
                $query->wherePassportCountryId($this->queryParams['passport_country']['id']);
            })->when($onlyFeatured, function ($query) {
                $query->whereIsFeatured(1);
            })->with('country', 'passportCountry')->get();
    }

    protected function getSellerInfo($profile)
    {
        return [
            'onholdable' => true,
            'passengersForm' => $profile->passengers_form,
        ];
    }

    protected function getBufferData($item, $onholdable, $now)
    {
        return [
            'reference_id' => createReferenceId(),
            'search_query_id' => $this->object->id,
            'seller_id' => $this->seller->id,
            'visa_profile_id' => $item['attachs']['profileId'],
            'seller_info' => json_encode($item['sellerInfo']),
            'service_info' => json_encode($item['serviceInfo']),
            'price_info' => json_encode($item['priceInfo']),
            'currency_id' => $this->queryParams['marketplace_currency']['id'],
            'total_b2b' => 0, ///> plugin will fill this
            'total_b2c' => 0, ///> plugin will fill this
            'type' => json_encode($item['serviceInfo']['visa']['type']),
            'fulltext_type' => implode(' ', array: array_filter($item['serviceInfo']['visa']['type'])),
            'is_onholdable' => $onholdable,
            'created_at' => $now,
        ];
    }

    protected function serviceModifier($profile)
    {
        return [
            'luxotaCode' => $this->seller->luxotaCode->code,
            'query_params' => $this->queryParams,
            'visa' => [
                'title' => $profile->title,
                'type' => $profile->type,
                'rules' => $profile->rules,
                'documents' => $profile->documents,
                'about' => $profile->about,
                'country' => $profile->country,
                'passportCountry' => $profile->passportCountry,
                'image' => $profile->image,
                'pax' => $profile->pax,
                'featuredImage' => $profile->featured_image ?? '',
                'subtitle' => $profile->subtitle ?? ''
            ]
        ];
    }

    protected function priceModifier($profile, $conditions = [])
    {
        $price = [
            'totalPrice' => $profile->price
        ];
        return [
            'priceInfo' => $price,
            'conditions' => $conditions,
            'rawPrices' => []
        ];
    }

    protected function visaPricingConditions($profile)
    {
        return [];
    }
}
