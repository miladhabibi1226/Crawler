<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics\Flight;

use App\TravelServices\Handler;
use App\TravelServices\Logics\Flight\FareRules as FlightFareRules;
use App\TravelServices\Suppliers\Amadeus\Logics\MiniRuleCodes;

trait FareRules
{
    use FlightFareRules;
    protected $method = 'MiniRule_GetFromRec';
    public function canBeDone()
    {
        request()->merge(['action' => 'fareInformativePricing', 'travelService' => 'flight']);
        $fareInformativePricing = Handler::afterSearch($this->object->reference_id);
        if (!$fareInformativePricing['status']) {
            $this->addError("fareInformativePricing unsuccessful");
            return false;
        }
        $this->supplierSession = $this->retrieveSession('fareInformativePricing');
        if (empty($this->supplierSession)) {
            $this->addError('Session Expired.');
            return false;
        }

        $this->LugTheResponse = false;
        $this->LugTheRequest = false;
        return true;
    }

    public function requestGenerator()
    {
        $depends = $this->headerData() + ['include' => 'FareRules', 'session' => json_decode($this->supplierSession->extras, true)];
        lugInfo("Amadeus FareRules Data ({$this->seller->alias})", [$depends]);
        $this->call = [
            'fields' => view('Amadeus.header.inseries', $depends)->render(),
            'url' => $this->cleanUrlSlash($depends['api_url']),
            'apiMethod' => 'FareRules'
        ];
        return $this->action();
    }

    public function extractor()
    {
        ///> one mnrByPricingRecord - FRN (Fare Recommendation Number) foreach passenger type
        $result = $this->rawResult['decoded']['MiniRule_GetFromRecReply']['mnrByPricingRecord'] ?? [];
        if (empty($result)) {
            $this->addError('no mnrByPricingRecord');
            return false;
        }
        $this->repelSingleMode($result);
        foreach ($result as $passengerTypeIndex => $legRules) {
            $mnrRulesInfoGrps = $legRules['mnrRulesInfoGrp'];
            $this->repelSingleMode($mnrRulesInfoGrps);
            $miniRulesSource = (new MiniRuleCodes($mnrRulesInfoGrps))->handle();

            $paxRef = $legRules['paxRef']['passengerReference'];
            $this->repelSingleMode($paxRef);
            $passengerType = $paxRef[0]['type'] == 'PI' ? 'INF' : ($passengerTypeIndex == 0 ? 'ADT' : 'CHD'); ///> amadeus returns passengerReference type PI for infants and PA for both ADT and CHD so we have to assume here that the 1st one is for adt and the second one for chd

            $fareComponentInfo = $legRules['fareComponentInfo'];
            $this->repelSingleMode($fareComponentInfo);
            foreach ($fareComponentInfo as $segment) {
                $item = [];
                $referenceDetails = $segment['fareComponentRef']['referenceDetails'];
                $this->repelSingleMode($referenceDetails);
                $referenceDetails = collect($referenceDetails);
                $FC = $referenceDetails->where('type', "FC")->first()['value'];
                $item['group'] = [
                    'passengerType' => $passengerType,
                    'airline' => '-',
                    'airports' => $segment['originAndDestination']['origin'] . '-' . $segment['originAndDestination']['destination'],
                ];
                $item['details'] = $miniRulesSource[$FC];
                $this->items[] = $item;
            }
        }
        // lugInfo('Amadeus FareRules extractor Items', ['Items' => $this->items]);
    }

    protected function handleSignOut()
    {
        $this->callSignOut('fareInformativePricing' . $this->object->reference_id);
    }
}
