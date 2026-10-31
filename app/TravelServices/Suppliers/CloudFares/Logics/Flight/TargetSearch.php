<?php

namespace App\TravelServices\Suppliers\CloudFares\Logics\Flight;

trait TargetSearch
{
    ///> we are disabling targetSearch for CloudFares bc we have disabled their cache

    protected function done()
    {
        return [
            'status' => true,
            'items' => [[
                'service' => $this->object->buffer->serviceInfo->data,
                'price' => $this->object->buffer->price_info,
                'sellerInfo' => $this->object->buffer->seller_info,
            ]],
            'queryParams' => $this->queryParams
        ];
    }
}
