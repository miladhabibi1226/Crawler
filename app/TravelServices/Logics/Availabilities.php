<?php

namespace App\TravelServices\Logics;

trait Availabilities
{
    protected function calculatePriceforAvailabilities($items)
    {
        $this->seller->currency; ///> to load currency object, to send with seller
        $priceCalculatorata = $items + [
            'queryParams' => $this->queryParams,
            'b2bBuyerGroupId' => $this->buyerGroupId(),
            'b2cBuyerGroupId' => nodeConfigs('default_buyer_group_id'),
            'seller' => $this->seller,
            'nodeCurrency' => node()->currency,
            'exchanges' => $this->getExchanges(),
            'buyerGroupSellers' =>  [
                $this->buyerGroupId() => $this->getBuyerGroupSeller($this->buyerGroupId(), true, false),
                node()->website_config['default_buyer_group_id'] => $this->getBuyerGroupSeller(node()->website_config['default_buyer_group_id'], true, true)
            ]
        ];
        $itemsPriceInfo = dataFromMSS('priceCalculator/supplier/' . $this->travelService . '/availability', $priceCalculatorata);
        return $itemsPriceInfo ?? [];
    }

    protected function extraDatafromPlugin($items)
    {
        $data = UpdateDataByMSS('supplier/afterSearch/profileData/' . $this->travelService, ['fields' => $items]);
        return $data ?? [];
    }

    protected function extraDatafromApiOut($items)
    {
        $data = UpdateDataByMSS('supplier/Quantum/afterSearch/' . $this->travelService, ['fields' => $items]);
        return $data ?? [];
    }

    protected function afterPriceModifier($fields, $data = [])
    {
        $data = UpdateDataByMSS('priceCalculator/supplier/' . $this->travelService . '/availability/after', ['fields' => $fields] + $data);
        return $data ?? [];
    }
}
