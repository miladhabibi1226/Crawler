<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

trait CreateTST
{
    protected $method = 'Ticket_CreateTSTFromPricing';
    public function canBeDone()
    {
        $this->supplierSession = $this->retrieveSession('sellFromRec', $this->object->seller_info['sessions']['sellFromRec']);
        if (empty($this->supplierSession)) {
            $this->addError('Session Expired.');
            return false;
        }

        return true;
    }

    public function requestGenerator()
    {
        $depends = $this->headerData() + ['include' => 'CreateTST', 'passengersCount' =>  $this->getServiceInfo()['passengersCount'], 'session' => json_decode($this->supplierSession->extras, true)];
        lugInfo("Amadeus CreateTST Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.inseries', $depends)->render(),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'CreateTST'
        ];
        return $this->action();
    }

    public function extractor()
    {
        ///> there is nothing to check
        // lugInfo('Amadeus CreateFOP extractor Items', ['items' => $this->items]);
    }

    protected function mapper() {}

    protected function failed($error = null)
    {
        $this->failBook();
        $this->expireSupplierCache();
        $this->callSignOut($this->object->seller_info['sessions']['sellFromRec']);
        $err = [];
        foreach ($error ?? [] as $errorMessages) {
            if ($this->getErrorClass($errorMessages) != 'technical') {
                $err[] = $errorMessages;
            }
        }
        if (empty($this->getSupplierErrorMessage())) {
            $error = implode('. ', $err);
        } else {
            $error = $this->getSupplierErrorMessage();
        }
        return ['status' => false, 'message' => $error];
    }
}
