<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

use Carbon\Carbon;

trait Search
{
    public function canBeDone()
    {
    }

    protected function getContent()
    {
        $ts = ucfirst($this->travelService);
        if ($this->fromFile) {
            $this->rawResult['body'] = file_get_contents(storage_path("Samples/Quantum//{$ts}Search.json"));
            $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);
            $this->latestSession = 'fromFile';
            return true;
        }

        if ($this->requestGenerator() === false) {
            $this->addError("Supplier connection error");
            return false;
        }

        $this->rawResult['decoded'] = json_decode($this->rawResult['body'], true);

        $error = $this->haveSupplierResponseError();
        if (!empty($error)) {
            $this->addError($error);
            return false;
        }
    }

    public function requestGenerator()
    {
        if ($this->object->searcher_identity === 'landingPage') {
            $this->landingPageRequestGenerator();
        } else {
            $this->regularRequestGenerator();
        }
        return $this->action();
    }

    protected function extractor() ///> flight does not use this
    {
        $data = $this->rawResult['decoded']['data'] ?? [];
        if (empty($data)) {
            return $this->addError('No items from the supplier');
        }

        $skippedByexception = 0;
        foreach ($data as $key => $item) {
            if ($item['priceDetails']['currency']['id'] != $this->seller->currency_id) {
                lugError('Currency Mismatch', ['result' => $item['priceDetails']['currency']['id'], 'seller' => $this->seller->currency_id]);
                return $this->addError('Currency Mismatch');
            }
            $serviceInfo = $item['serviceInfo'];
            $serviceInfo['query_params'] = $this->queryParams;
            $serviceInfo['supplier'] = '';
            $conditions = $item['pricingConditions'];
            if (!$this->checkAccessExceptions($conditions, $this->getAccessExceptions())) {
                $skippedByexception++;
                continue;
            }
            $this->items[$key] = $item;
            $this->items[$key]['serviceInfo'] = $serviceInfo;
            $this->items[$key]['priceInfo'] = $this->priceModifier($item['priceDetails'], $conditions);
            $this->items[$key]['sellerInfo'] =  $this->getSellerInfo($item);
        }
        lugInfo('S>>>> Quantum Hotel Search Extractor Items - ' . $this->seller->alias, ['skippedByexception' => $skippedByexception,'count' => count($this->items )]);
    }

    protected function landingPageRequestGenerator()
    {
        $auth = $this->seller->api->configs;
        $this->call = [
            'api' => $this->travelService == 'flight' ? 'v1/search' : 'v0/search',
            'host' => $auth['url'],
            'variables' => [$this->travelService],
            'params' => ['landingPage' => true],
            'apiMethod' => 'Search',
            'httpMethod' => 'POST'
        ];
    }

    protected function regularRequestGenerator()
    {
        $auth = $this->seller->api->configs;
        $depends = $this->getFields();
        // lugInfo('Blade Data', [$depends]);
        $this->call = [
            'api' => $this->travelService == 'flight' ? 'v1/search' : 'v0/search',
            'host' => $auth['url'],
            'variables' => [$this->travelService],
            'fields' => $depends,
            'headers' => ['X-Origin-Nodes:' . (empty($this->object->requested_by_node) ? ',' : $this->object->requested_by_node) . node()->id . ','],
            'apiMethod' => 'Search',
            'httpMethod' => 'POST'
        ];
    }


    protected function mapper()
    {
        $now = Carbon::now();
        $onholdable = $this->isOnholdable();

        $buffers = [];
        foreach ($this->items  as $item) {
            $buffers[] = $this->getBufferData($item, $onholdable, $now);
        }
        $this->insertIntoBuffer($this->calculatePriceforEachItem($buffers));
    }

    protected function insertIntoBuffer($buffers)
    {
        if (!empty($buffers)) {
            $start = microtime(true);
            foreach (array_chunk($buffers, 200) as $items) {
                $bufferModel = 'App\Models\\' . ucfirst($this->travelService) . 'Buffer';
                $bufferModel::insert($items);
            }
            lugWarning('Insert Time: ' . (microtime(true) - $start));
        }
    }

    protected function updateOriginalPassengerForm($original)
    {
        foreach ($original as $type => $group) { ///> $type = all or lead
            foreach ($group as $itemType => $items) { ///> $itemType = phone , email , first_name , etc
                foreach ($items as $index => $item) {
                    if (!empty($item['default'])) {
                        $original[$type][$itemType][$index]['default'] = $this->passengerFormItems($itemType, 'default');
                    }
                }
            }
        }
        return $original;
    }
}
