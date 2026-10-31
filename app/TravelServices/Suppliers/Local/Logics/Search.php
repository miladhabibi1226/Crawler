<?php

namespace App\TravelServices\Suppliers\Local\Logics;

use App\TravelServices\Logics\Search as LogicsSearch;
use Carbon\Carbon;

trait Search
{
    use LogicsSearch;
    ///> flight and hotel won't use this trait at all
    protected function getContent()
    {
        if ($this->isFromLandingPage()) {
            $this->landingPageSearch();
        } else {
            $this->regularSearch();
        }
        if (count($this->rawResult) < 1) {
            return $this->addError('No Result');
        }
    }

    protected function regularSearch($onlyFeatured = false) ///> not visa
    {
        $commonQuery = $this->commonQuery($onlyFeatured);
        if (!($serviceQuery = $this->serviceQuery($commonQuery))) return false;
        $this->rawResult = $serviceQuery->get();
        $this->getAvailabilities();
    }

    protected function commonQuery($onlyFeatured)
    {
        $profileModel = 'MatinUtils\NodeTracker\Models\\' . ucfirst($this->travelService) . 'Profile';
        return $profileModel::whereStatus(1)->whereSellerId($this->seller->id)
            ->when($onlyFeatured, function ($query) {
                $query->whereIsFeatured(1);
            })->whereHas('availabilities', function ($availabilityQuery) {
                $now = Carbon::now();
                $this->availabilityQuery($availabilityQuery)->where('from', '>', $now)
                    ->when(!empty($this->queryParams['startDate']), function ($q) {
                        $q->where(function ($mountQ) {
                            $startDate = Carbon::create($this->queryParams['startDate']);
                            $endDate = Carbon::create($this->queryParams['endDate']);
                            $mountQ->Where('from', '<=', $endDate)->Where('to', '>=', $startDate);
                        });
                    });
            });
    }

    protected function availabilityQuery($availabilityQuery) ///> for package
    {
        return $availabilityQuery;
    }

    protected function extractor()
    {
        $this->extractData();
        $skippedForServiceInfo = 0;
        foreach ($this->rawResult as $profile) {
            if (!($serviceInfo = $this->serviceModifier($profile))) {
                $skippedForServiceInfo++;
                continue;
            }
            $serviceInfo['support'] = ['pid' => ['search' => app('log-system')->getPid()]];
            $serviceInfo['luxotaCode'] = $this->seller->luxotaCode->code;
            if (!($sellerInfo = $this->getSellerInfo($profile))) {
                $skippedForServiceInfo++;
                continue;
            }
            $serviceInfo['sellerCode'] = $this->seller->abb;
            $this->items[] = [
                'serviceInfo' => $serviceInfo,
                'priceInfo' => $this->priceModifier($profile, $this->getPricingConditions($serviceInfo, $profile)),
                'sellerInfo' => $sellerInfo,
                'attachs' => ['profileId' => $profile->id, 'travelStyleIds' => $profile->travel_style ?? []]
            ];
        }
        if (empty($this->items)) {
            $this->addError('no items passed extractor');
        }
        lugInfo("S>>>> Local $this->travelService Search Extractor items - " . $this->seller->alias, ['skippedForServiceInfo' => $skippedForServiceInfo, 'supplier raw response items count' => count($this->rawResult), 'Inserted items' => count($this->items)]);
    }

    protected function mapper()
    {
        $onholdable = $this->isOnholdable();
        $now = Carbon::now();

        $buffers = [];
        foreach ($this->items  as $item) {
            $buffers[] = $this->getBufferData($item, $onholdable, $now);
        }
        $this->insertIntoBuffer($this->calculatePriceforEachItem($buffers));
    }

    protected function landingPageSearch()
    {
        ///> right now for all the travelServices reqular and lanfingPage search are the same (the conditions are not required)
        ///> except landingpage results must be from featured items only
        $this->regularSearch(true);
    }

    protected function priceModifier($profile, $conditions = []) ///>not visa
    {
        $price = [
            'oldPrice' => $profile->old_price,
            'priceStartsFrom' => $profile->price_starts_from,
            'totalPrice' => $profile->price_starts_from
        ];
        return [
            'priceInfo' => $price,
            'conditions' => $conditions,
            'rawPrices' => [],
        ];
    }

    protected function insertIntoBuffer($buffers)
    {
        try {
            $start = microtime(true);
            if (!empty($buffers)) {
                $bufferModel = 'App\Models\\' . ucfirst($this->travelService) . 'Buffer';
                $bufferModel::insert($buffers);
            }
            lugInfo('S>>>> ' . count($buffers) . ' items inserted for ' . $this->seller->alias . '. Time: ' . (microtime(true) - $start));
        } catch (\Throwable $th) {
            lugError("can't Insert Local items for " . $this->seller->alias, ['exception' => $th->getMessage(), 'exceptionLine' => $th->getFile() . ' line: ' . $th->getLine(), 'trace' => $th->getTraceAsString()]);
        }
    }

    protected function isFromLandingPage()
    {
        return ($this->object->searcher_identity === 'landingPage');
    }

    protected function extractData()
    {
    }

    protected function getConditions()
    {
        return [];
    }
}
