<?php

namespace App\TravelServices\Suppliers\CityNet;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\CityNet\Logics\Common;
use App\TravelServices\Suppliers\CityNet\Logics\Flight\SignIn;

class Ping extends Remote
{
    use SignIn, Common, LogicsPing;
    public function canBeDone()
    {
        $configSchema = $this->seller->supplierService->config_schema;
        $sellerConfigs = $this->seller->api->configs;
        foreach ($configSchema as $item) {
            if (empty($sellerConfigs[$item['item']])) {
                lugError('All configuration items must be filled.', ['config schema' => $configSchema, 'sellersconfigs' => $sellerConfigs, 'failed item' => $item]);
                $this->addError('All configuration items must be filled.');
            } else {
                if (in_array($item, ['useCredit', 'useEwallet'])) {
                    if (!in_array($sellerConfigs[$item['item']], ['yes', 'no'])) {
                        $this->addError("Available Values for $item are: yes and no.");
                    }
                }
            }
        }
        return true;
    }

    public function done()
    {
        return ['status' => true];
    }
}
