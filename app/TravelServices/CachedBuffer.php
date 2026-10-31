<?php

namespace App\TravelServices;

use Exception;
use MatinUtils\NodeTracker\Models\Seller;
use MatinUtils\SharedLogics\LocalInventory\DirectProfile;

///>    WILL MOVE THIS WHOLE CLASS TO NODE TRACKER LATER TO USE IT IN MARKETPLACE AND APIOUT
class CachedBuffer
{
    use DirectProfile;
    public $travelService, $id, $service_info, $price_info, $seller_info, $passenger_info, $seller, $queryParams;
    public function __construct(string $travelService, int $id, string|null|object $seller)
    {
        if (empty($travelService) || empty($id)) {
            throw new Exception("Travel service or id are missing", 1);
        }
        $this->travelService = $travelService;
        $this->id = $id;
        $this->{$travelService . "_profile_id"} = $id;
        $this->findSeller($seller);
        $this->passenger_info = request('passengersInfo');
        request()->merge(['id' => $id]);
    }

    public function getCachedBuffer($withPriceInfo = false)
    {
        $cached = getCachedItem($this->localCacheKey());
        $buyerGroupId = request('buyerGroupId');
        if (empty($cached['serviceInfo'])) {
            ///> when cache socket is restarted we'll get null. will remove this when we started closing cache socket after each worker
            $cached = getCachedItem($this->localCacheKey());
        }
        $cachedServiceInfo = $cached['serviceInfo'] ?? [];
        $this->seller_info = ['passengersForm' => $cached['serviceInfo']['passengersForm']];
        if ($this->travelService ==  'package') {
            $this->service_info['availabilities'] =  $cachedServiceInfo['availabilities'] ?? [];
            $this->seller_info['registerLimit'] = $cachedServiceInfo['registerLimit'];
            unset($cachedServiceInfo['availabilities']);
            unset($cachedServiceInfo['registerLimit']);
        }
        unset($cached['serviceInfo']['passengersForm']);
        $this->service_info[$this->travelService] = $cachedServiceInfo ?? [];
        $this->service_info['query_params'] = ['buyer_currency' => nodeCurrency(), 'marketplace_currency' => nodeCurrency()]; ///> we'll use node currency for original price calculations and will exchange it later if it's needed
        if ($withPriceInfo) {
            $priceInfo = $cached['priceInfo'][$buyerGroupId] ?? [];
            ///> for now there is no need to worry about marketplace and buyer price here since supplier won't use this
            $this->price_info = $priceInfo;
        }
        if (empty($this->service_info) || ($withPriceInfo && empty($this->price_info))) {
            lugError('Item is not cached', ['cached' => $cached]);
            return null;
        }
        return $this;
    }

    public function clearCachedBuffer()
    {
        removeCache($this->localCacheKey(null, $this->id));
    }

    public function toArray()
    {
        foreach ($this->getPublicProperties() as $key => $val) {
            if (is_object($val)) {
                $data[$key] = $val->toArray();
            } else {
                $data[$key] = $val;
            }
        }
        return $data;
    }

    public function updateCached() ///> could have named it "update" but i didn't want to mix it with model::update for now
    {
        ///> right now only package uses this function for availabilities
        if (request('action') == 'availabilities') {
            $marketplaceCurrencyId = request('marketplaceCurrencyId');
            $buyerCurrencyId = request('buyerCurrencyId');
            $buyerGroupId = request('buyerGroupId');
            $this->updateAvailabilitiesPriceInfo($this->price_info['availabilities'], $buyerGroupId, $marketplaceCurrencyId, $buyerCurrencyId);
            $this->updateAvailabilitiesServiceInfo($this->service_info['availabilities'], $marketplaceCurrencyId);

            ///> temp untill maybe later we find a better place
            ///> putting sellerInfo in cache. putting it in price info
            ///> can't put it in service info bc service info will be shared with other nodes
            $buyerGroupId = request('buyerGroupId');
            cacheitem($this->localCacheKey("priceInfo.$buyerGroupId.availabilitiesSellerInfo"), $this->seller_info['availabilities']);
        }
    }

    protected function getPublicProperties()
    {
        return array_filter(
            (array) $this,
            static fn (string $key): bool => strpos($key, "\0") !== 0,
            ARRAY_FILTER_USE_KEY,
        );
    }

    protected function findSeller($seller)
    {
        if (empty($seller)) {
            $profileClass = $this->getObjectClass();
            $this->seller = $profileClass::select('id', 'seller_id')->whereId($this->id)->first()->seller;
        } else {
            $this->seller = is_string($seller) ? Seller::where('reference_id', $seller)->first() : $seller;
        }
    }
}
