<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

trait CreateFOP
{
    protected $method = 'FOP_CreateFormOfPayment';
    public function canBeDone()
    {
        $this->supplierSession = $this->retrieveSession('sellFromRec');
        if (empty($this->supplierSession)) {
            $this->addError('Session Expired.');
            $this->addErrorToBook(trans('messages.session'));
            return false;
        }

        return true;
    }

    public function requestGenerator()
    {
        $depends = $this->headerData() + ['include' => 'CreateFOP', 'session' => json_decode($this->supplierSession->extras, true)];
        // lugInfo("Amadeus CreateFOP Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.inseries', $depends)->render(),
            'timeout' => 20,
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'CreateFOP'
        ];
        return $this->action();
    }

    public function extractor()
    {
        ///> there is nothing to check
        // lugInfo('Amadeus CreateFOP extractor Items', ['items' => $this->items]);
    }

    protected function mapper()
    {
        $sellerInfo = $this->object->seller_info;
        $sellerInfo['beforeBooks']['createFOP'] = true;
        $this->object->seller_info = $sellerInfo;
        $this->object->update();
    }

    protected function failed($error = null)
    {
        $this->failBook();
        $this->expireSupplierCache();
        $this->callSignOut('sellFromRec' . $this->object->reference_id);
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
