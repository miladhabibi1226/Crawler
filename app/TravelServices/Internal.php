<?php

namespace App\TravelServices;

use MatinUtils\NodeTracker\ApiOut;
use MatinUtils\NodeTracker\Models\Booking;

abstract class Internal extends BaseSupplier
{
    protected $defaultHeader = [];
    protected $call = [];
    protected $supplierSession, $latestSession;
    protected $supplierConvo;
    protected $supplierConnection;
    public $LugTheRequest = true, $LugTheResponse = true;

    use ApiOut;

    public function action()
    {
        $requestHeaders = array_merge(($this->call['headers'] ?? []), $this->defaultHeader);
        $this->LugTheRequest($this->LugTheRequest, $requestHeaders);

        $executionStart = microtime(true);
        $result = $this->callApiOut($this->call['api'], $this->call['host'], $this->call['variables'] ?? [], $this->call['params'] ?? [], $this->call['fields'] ?? [], $requestHeaders, $this->call['httpMethod'] ?? null);
        $executionEnd = microtime(true);
        if (!$result) {
            $this->processStatus = false;
        }

        $this->rawResult = ['body' => $result];

        $lugDataResponse = [];
        if ($this->LugTheResponse) {
            $lugDataResponse = $this->rawResult;
        }
        lugInfo('Supplier Response - ' . $this->lugInfo['class'] . ' - ' . $this->seller->alias, $lugDataResponse);

        if ($this->objectIsBooking) {
            $this->supplierConvo = [
                'entity_id' => $this->object->id,
                'entity_type' => Booking::class,
                'url' => $this->call['host'] .':'.$this->call['api'],
                'request_body' => json_encode($this->call['fields'] ?? []),
                'request_headers' => json_encode($requestHeaders),
                'response_body' => $result,
                'response_headers' => '',
                'method' => $this->call['apiMethod'] ?? ''
            ];
        }
        $this->supplierConnection = [
            'supplier_service_id' => $this->seller->supplier_service_id,
            'pid' => app('log-system')->getPid(),
            'method' => $this->call['apiMethod'] ?? '',
            'start_time' => $executionStart,
            'total_time' => $executionEnd - $executionStart
        ];

        return true;
    }

    protected function supplierConversaion($errorMessages = '')
    {
        if (is_array($errorMessages)) {
            $errorMessages = json_encode($errorMessages);
        }

        $this->insertSupplierConversation($errorMessages);

        if (!empty($errorMessages)) {
            $this->returnBookingErrorToMarketplace($errorMessages);
            $this->sendSupplierErrorEmail($errorMessages);
        }
    }

    protected function lugTheRequest($LugTheRequest, $requestHeaders) //> temp
    {
        $lugDataRequest = [];
        // if ($LugTheRequest) {
            $lugDataRequest = ['body' => $this->call['fields'] ?? [], 'headers' => $requestHeaders] + $this->call;
            unset($lugDataRequest['fields']);
            $lugDataRequest = ['request' => $lugDataRequest, 'callHeaders' => $this->call['headers'] ?? [], 'defaultHeader' => $this->defaultHeader ?? []];
        // }
        lugInfo('Supplier Request - ' . $this->lugInfo['class'] . ' - ' . $this->seller->alias, $lugDataRequest);
    }
}
