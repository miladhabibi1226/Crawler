<?php

namespace App\TravelServices\Logics;

trait Ping
{
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
            }
        }
        $this->checkSupplierCondition();
        return true;
    }

    protected function failed($error = null)
    {
        if (is_array($error)) {
            $error = $error[0];
            if (is_array($error[0])) {
                $error = json_encode($error[0]);
            }
        }
        return ['status' => false, 'error' => $error];
    }
    
    protected function  checkSupplierCondition()//Accelaero
    {
        return true;
    }
}
