<?php

namespace App\TravelServices\Suppliers\Sabre;

use App\TravelServices\Remote;
use App\TravelServices\Logics\Ping as LogicsPing;
use App\TravelServices\Suppliers\Sabre\Logics\Common;
use App\TravelServices\Suppliers\Sabre\Logics\Flight\SignIn;

class Ping extends Remote
{
    use SignIn, Common, LogicsPing {
        SignIn::decodeRawResult insteadof Common;
    }

    public function canBeDone()
    {
        $configSchema = $this->seller->supplierService->config_schema;
        $sellerConfigs = $this->seller->api->configs;
        foreach ($configSchema as $item) {
            if (empty($sellerConfigs[$item['item']])) {
                if (empty($item['depends']) || ($sellerConfigs[$item['depends'] ?? ''] ?? 'no depends') == ($item['when'] ?? '')) {
                    lugError('All configuration items must be filled.', ['config schema' => $configSchema, 'sellersconfigs' => $sellerConfigs, 'failed item' => $item]);
                    $this->addError('All configuration items must be filled.');
                }
            } else {
                if ($item['item'] == 'paymentType') {
                    $acceptable = ['CASH', 'CREDIT CARD'];
                    if (!in_array($sellerConfigs[$item['item']], $acceptable)) {
                        $this->addError('Available Values for paymentType are: ' . implode(',', $acceptable));
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
