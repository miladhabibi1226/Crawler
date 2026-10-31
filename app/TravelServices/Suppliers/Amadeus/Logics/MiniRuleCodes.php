<?php

namespace App\TravelServices\Suppliers\Amadeus\Logics;

use Carbon\Carbon;

class MiniRuleCodes
{
    protected $response = [];
    public function __construct(array $mnrRulesInfoGrps)
    {
        $this->response = $mnrRulesInfoGrps;
    }

    public function handle()
    {
        $rules = [];
        foreach ($this->response as $group) {
            $fareComponents = $group['mnrFCInfoGrp']['refInfo']['referenceDetails'];
            $this->repelSingleMode($fareComponents);
            $translated = $this->lookUp($group);
            foreach ($fareComponents as $fareComponent) {
                $rules[$fareComponent['value']][] = $translated;
            }
        }
        // dd($rules);
        return $rules;
    }

    public function lookUp(array $response)
    {
        $category = $response['mnrCatInfo']['descriptionInfo']['number'];
        $item = ['title' => $this->categories[$category]['title'], 'text' =>  $this->{"collectCodes$category"}($response)];
        return $item;
    }

    protected function collectCodes33($response)
    {
        return $this->collectCodes31($response, 33);
    }

    protected function collectCodes31($response, $cat = 31)
    {
        $catSource = $this->categories[$cat];
        $codes = [];
        $dateCodesOfResponse = $response['mnrDateInfoGrp']['dateInfo']['dateAndTimeDetails'] ?? [];
        $this->repelSingleMode($dateCodesOfResponse);
        foreach ($dateCodesOfResponse as $dateAndTimeDetails) {
            $code = $dateAndTimeDetails['qualifier'];
            $translated = $this->transDateType($catSource['codes'][$code], $dateAndTimeDetails);
            if (!in_array($translated, $codes)) {
                $codes[$code] = $translated;
            }
            unset($catSource['codes'][$code]);
        }
        $amountCodesOfResponse = $response['mnrMonInfoGrp'];
        $this->repelSingleMode($amountCodesOfResponse);
        foreach ($amountCodesOfResponse as $mnrMonInfoGrp) {
            $monetaryDetailsGrp = $mnrMonInfoGrp['monetaryInfo']['monetaryDetails'];
            $this->repelSingleMode($monetaryDetailsGrp);
            foreach ($monetaryDetailsGrp as $monetaryDetails) {
                $code = $monetaryDetails['typeQualifier'];
                $translated = $this->transAmountType($catSource['codes'][$code], $monetaryDetails);
                if (!in_array($translated, $codes)) {
                    $codes[$code] = $translated;
                }
                unset($catSource['codes'][$code]);
            }
        }
        $flagCodesOfResponse = $response['mnrRestriAppInfoGrp']['mnrRestriAppInfo']['statusInformation'];
        $this->repelSingleMode($flagCodesOfResponse);
        foreach ($flagCodesOfResponse as $statusInformation) {
            $code = $statusInformation['indicator'];
            $translated = $this->transFlagType($catSource['codes'][$code], $statusInformation);
            if (!in_array($translated, $codes)) {
                $codes[$code] = $translated;
            }
            unset($catSource['codes'][$code]);
        }
        foreach ($catSource['codes'] as $code => $codeInfo) {
            $codes[$code] = $this->transDateType($codeInfo);
        }
        return implode('. ', $codes);
    }

    protected function collectCodes6($response)
    {
        if (strtoupper($response['mnrCatInfo']['processIndicator'] ?? '') == 'ASS') {
            return $this->categories[6]['assumption'];
        }
        if (strtoupper($response['mnrCatInfo']['processIndicator'] ?? '') == 'ASS') {
            return $this->categories[6]['assumption'];
        }
        $locationType = [25 => 'City'][$response['mnrFCInfoGrp']['locationInfo']['locationType']]; ///> in user quide 25 => city, in technicalRef 25 => country ?????
        $location = $response['mnrFCInfoGrp']['locationInfo']['locationDescription']['code'] . ' ' . $locationType;
        $dateCodesOfResponse = $response['mnrDateInfoGrp']['dateInfo']['dateAndTimeDetails'];
        $this->repelSingleMode($dateCodesOfResponse);
        foreach ($dateCodesOfResponse as $dateAndTimeDetails) {
            $code = $dateAndTimeDetails['qualifier'];
            $codes[$code] = $this->transDateLocationType($this->categories[6]['codes'][$code], $location, $dateAndTimeDetails);
            unset($this->categories[6]['codes'][$code]);
        }
        foreach ($this->categories[6]['codes'] as $code => $codeInfo) {
            $codes[$code] = $this->transDateLocationType($codeInfo, $location);
        }
        return implode('. ', $codes);
    }

    protected function collectCodes7($response)
    {
        if (strtoupper($response['mnrCatInfo']['processIndicator'] ?? '') == 'ASS') {
            return $this->categories[6]['assumption'];
        }
        $locationType = [25 => 'City'][$response['mnrFCInfoGrp']['locationInfo']['locationType']]; ///> in user quide 25 => city, in technicalRef 25 => country ?????
        $location = $response['mnrFCInfoGrp']['locationInfo']['locationDescription']['code'] . ' ' . $locationType;
        $dateCodesOfResponse = $response['mnrDateInfoGrp']['dateInfo']['dateAndTimeDetails'];
        $this->repelSingleMode($dateCodesOfResponse);
        foreach ($dateCodesOfResponse as $dateAndTimeDetails) {
            $code = $dateAndTimeDetails['qualifier'];
            $codes[$code] = $this->transDateLocationType($this->categories[7]['codes'][$code], $location, $dateAndTimeDetails);
            unset($this->categories[7]['codes'][$code]);
        }
        foreach ($this->categories[7]['codes'] as $code => $codeInfo) {
            $codes[$code] = $this->transDateLocationType($codeInfo, $location);
        }
        // dd($response,$codes );
        return implode('. ', $codes);
    }

    protected function collectCodes5($response)
    {
        if (strtoupper($response['mnrCatInfo']['processIndicator'] ?? '') == 'ASS') {
            return $this->categories[5]['assumption'];
        }
        $dateCodesOfResponse = $response['mnrDateInfoGrp']['dateInfo']['dateAndTimeDetails'];
        $this->repelSingleMode($dateCodesOfResponse);
        foreach ($dateCodesOfResponse as $dateAndTimeDetails) {
            $code = $dateAndTimeDetails['qualifier'];
            $codes[$code] = $this->transDateType($this->categories[5]['codes'][$code], $dateAndTimeDetails);
            unset($this->categories[5]['codes'][$code]);
        }
        foreach ($this->categories[5]['codes'] as $code => $codeInfo) {
            $codes[$code] = $this->transDateType($codeInfo);
        }
        return implode('. ', $codes);
    }

    protected function transAmountType($codeInfo, $response = null)
    {
        if (empty($response) || $response['amount'] == 0) {
            return $codeInfo['def'];
        }
        return  "{$codeInfo['situation']} {$codeInfo['desc']} is {$response['amount']} {$response['currency']}";
    }

    protected function transFlagType($codeInfo, $response = null)
    {
        if (empty($response) || $response['action'] == 0) {
            return $codeInfo['def'];
        }
        return  $codeInfo['desc'];
    }

    protected function transDateType($codeInfo, $response = null)
    {
        if (empty($response)) {
            return $codeInfo['def'];
        }
        $time = Carbon::parse($response['date'] . '  ' . $response['time'])->format('Y-m-d H:i');
        return $codeInfo['desc'] . " is $time";
    }

    protected function transDateLocationType($codeInfo, $location, $response = null)
    {
        if (empty($response)) {
            return $codeInfo['def'];
        }
        $time = Carbon::parse($response['date'] . '  ' . $response['time'])->format('Y-m-d H:i');
        return $codeInfo['desc'] . " $time in $location";
    }

    protected $categories = [
        '31' => [
            'title' => 'Changes/Reissue',
            'assumption' => 'Not allowed',
            'codes' => [
                'BDV' => [
                    'desc' => 'Ticket validity date Before departure',
                    'def' => 'No limit for Ticket validity date Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Date',
                ],
                'BNV' => [
                    'desc' => 'Ticket validity date Before departure no show',
                    'def' => 'No limit for Ticket validity date Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Date',
                ],
                'ADV' => [
                    'desc' => 'Ticket validity date after departure',
                    'def' => 'No limit for Ticket validity date after departure',
                    'situation' => 'After departure',
                    'type' => 'Date',
                ],
                'ANV' => [
                    'desc' => 'Ticket validity date after departure no show',
                    'def' => 'No limit for Ticket validity date after departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Date',
                ],
                'BDM' => [
                    'desc' => 'Reissue minimum Penalty amount with sale currency',
                    'def' => 'Reissue Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDX' => [
                    'desc' => 'Reissue maximum penalty amount with sale currency',
                    'def' => 'Reissue Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDF' => [
                    'desc' => 'Reissue minimum Penalty amount with filing currency',
                    'def' => 'Reissue Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDG' => [
                    'desc' => 'Reissue maximum penalty amount with filing currency',
                    'def' => 'Reissue Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDT' => [
                    'desc' => 'Reissue maximum penalty amount for the ticket',
                    'def' => 'Reissue Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDI' => [
                    'desc' => 'Revalidation minimum Penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDU' => [
                    'desc' => 'Revalidation maximum penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDH' => [
                    'desc' => 'Revalidation minimum Penalty amount with filing currency',
                    'def' => 'Revalidation Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDL' => [
                    'desc' => 'Revalidation maximum penalty amount with filing currency',
                    'def' => 'Revalidation Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDC' => [
                    'desc' => 'Revalidation maximum penalty amount for the ticket',
                    'def' => 'Revalidation Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BNM' => [
                    'desc' => 'Reissue minimum Penalty amount with sale currency',
                    'def' => 'Reissue Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNX' => [
                    'desc' => 'Reissue maximum penalty amount with sale currency',
                    'def' => 'Reissue Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNF' => [
                    'desc' => 'Reissue minimum penalty amount with filing currency',
                    'def' => 'Reissue Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNG' => [
                    'desc' => 'Reissue maximum penalty amount with filing currency',
                    'def' => 'Reissue Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNT' => [
                    'desc' => 'Reissue maximum penalty amount for the ticket',
                    'def' => 'Reissue Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNI' => [
                    'desc' => 'Reissue minimum penalty amount for the ticket',
                    'def' => 'Reissue Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNU' => [
                    'desc' => 'Revalidation maximum penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNH' => [
                    'desc' => 'Revalidation maximum penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNL' => [
                    'desc' => 'Revalidation maximum penalty amount with filing currency',
                    'def' => 'Revalidation Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNC' => [
                    'desc' => 'Revalidation maximum penalty amount for the ticket',
                    'def' => 'Revalidation Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'ADM' => [
                    'desc' => 'Reissue minimum Penalty amount with sale currency',
                    'def' => 'Reissue Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADX' => [
                    'desc' => 'Reissue Maximum penality amount After departure',
                    'def' => 'Reissue Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADF' => [
                    'desc' => 'Reissue minimum Penalty amount with filing currency',
                    'def' => 'Reissue Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADT' => [
                    'desc' => 'Reissue maximum penalty amount for the ticket',
                    'def' => 'Reissue Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADG' => [
                    'desc' => 'Reissue maximum penalty amount with filing currency',
                    'def' => 'Reissue Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADI' => [
                    'desc' => 'Revalidation minimum Penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADU' => [
                    'desc' => 'Revalidation Maximum penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADH' => [
                    'desc' => 'Revalidation Minimum Penalty amount with filing currency',
                    'def' => 'Revalidation Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADL' => [
                    'desc' => 'Revalidation Maximum penalty amount with filing currency',
                    'def' => 'Revalidation Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADC' => [
                    'desc' => 'Revalidation Maximum penalty amount for the ticket',
                    'def' => 'Revalidation Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ANM' => [
                    'desc' => 'Reissue minimum Penalty amount with sale currency',
                    'def' => 'Reissue Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANX' => [
                    'desc' => 'Reissue maximum penalty amount with filing currency',
                    'def' => 'Reissue Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANF' => [
                    'desc' => 'Reissue minimum Penalty amount with filing currency',
                    'def' => 'Reissue Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANG' => [
                    'desc' => 'Reissue minimum Penalty amount with filing currency',
                    'def' => 'Reissue Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANT' => [
                    'desc' => 'Reissue maximum penalty amount for the ticket',
                    'def' => 'Reissue Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANI' => [
                    'desc' => 'Revalidation minimum Penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANU' => [
                    'desc' => 'Revalidation maximum penalty amount with sale currency',
                    'def' => 'Revalidation Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANH' => [
                    'desc' => 'Revalidation minimum Penalty amount with filing currency',
                    'def' => 'Revalidation Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANL' => [
                    'desc' => 'Revalidation maximum penalty amount with filing currency',
                    'def' => 'Revalidation Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANC' => [
                    'desc' => 'Revalidation maximum penalty amount for the ticket',
                    'def' => 'Revalidation Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'FFT' => [
                    'desc' => 'Part of rule is free form text from Cat16',
                    'def' => 'No free form text',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'No free form text from Cat16', '1' => 'Part of the rule is free form text from Cat16'],
                    'situation' => 'All'
                ],
                'RVA' => [
                    'desc' => 'Revalidation before departure is allowed',
                    'def' => 'Revalidation before departure is Not Allowed ',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not Allowed ', '1' => 'Allowed with restrictions'],
                    'situation' => 'Before departure'
                ],
                'WAI' => [
                    'desc' => 'Penalties can be waived for passenger and family death/illness',
                    'def' => 'No waiver Before departure for passenger and family death/illness',
                    'type' => 'Flag',
                    'bolean_value' => ['Value hardcoded to 0'],
                    'situation' => 'Before departure'
                ], ///> This codeset is dismissed and the process always sends an hardcoded value 0
                'BDA' => [
                    'desc' => 'Reissue before departure(RiBD) allowed',
                    'def' => 'Reissue before departure is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'Before departure'
                ],
                'BNW' => [
                    'desc' => 'Penalties can be waived for passenger and family death/illness',
                    'def' => 'No waiver Before departure no show for passenger and family death/illness',
                    'type' => 'Flag',
                    'bolean_value' => ['Value hardcoded to 0'],
                    'situation' => 'Before departure no show'
                ], ///> This codeset is dismissed and the process always sends an hardcoded value 0
                'BNR' => [
                    'desc' => 'Revalidation before departure when no show(ReBDNS) is allowed',
                    'def' => 'Revalidation before departure when no show is Not Allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'Before departure no show'
                ],
                'BNA' => [
                    'desc' => 'Reissue before departure when no show(ReBDNS) is allowed',
                    'def' => 'Reissue before departure when no show is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'Before departure no show'
                ],
                'ADW' => [
                    'desc' => 'Penalties can be waived for passenger and family death/illness',
                    'def' => 'No waiver After departure for passenger and family death/illness',
                    'type' => 'Flag',
                    'bolean_value' => ['Value hardcoded to 0'],
                    'situation' => 'After departure'
                ], ///> This codeset is dismissed and the process always sends an hardcoded value 0
                'ADR' => [
                    'desc' => 'Revalidation After departure is allowed',
                    'def' => 'Revalidation After departure is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => [],
                    'situation' => 'After departure'
                ],
                'ADA' => [
                    'desc' => 'Reissue After departure(RiAD) is allowed',
                    'def' => 'Reissue After departure is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => [],
                    'situation' => 'After departure'
                ],
                'ANW' => [
                    'desc' => 'Penalties can be waived for passenger and family death/illness',
                    'def' => 'No waiver After departure no show for passenger and family death/illness',
                    'type' => 'Flag',
                    'bolean_value' => ['Value hardcoded to 0'],
                    'situation' => 'After departure no show'
                ],
                'ANR' => [
                    'desc' => 'Revalidation After departure when no show is allowed',
                    'def' => 'Revalidation After departure when no show is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'After departure no show'
                ],
                'ANA' => [
                    'desc' => 'Reissue After departure when no show(RiADNS) is allowed',
                    'def' => 'Reissue After departure when no show is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'After departure no show'
                ],
                'GUA' => [
                    'desc' => 'Amount Guaranteed',
                    'def' => 'Amount Not guaranteed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not guaranteed', '1' => 'Guaranteed'],
                    'situation' => 'Before departure'
                ]
            ]
        ],
        '33' => [
            'title' => 'Refund',
            'assumption' => 'Refundable without penalties',
            'codes' => [
                'BDV' => [
                    'desc' => 'Ticket validity date Before departure',
                    'def' => 'No limit for Ticket validity date Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Date',
                ],
                'BNV' => [
                    'desc' => 'Ticket validity date Before departure no show',
                    'def' => 'No limit for Ticket validity date Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Date',
                ],
                'ADV' => [
                    'desc' => 'Ticket validity date after departure',
                    'def' => 'No limit for Ticket validity date after departure',
                    'situation' => 'After departure',
                    'type' => 'Date',
                ],
                'ANV' => [
                    'desc' => 'Ticket validity date after departure no show',
                    'def' => 'No limit for Ticket validity date after departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Date',
                ],
                'BDM' => [
                    'desc' => 'Minimum Penalty amount with sale currency',
                    'def' => 'Reissue Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDX' => [
                    'desc' => 'Maximum penalty amount with sale currency',
                    'def' => 'Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDF' => [
                    'desc' => 'Minimum Penalty amount with filing currency',
                    'def' => 'Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDG' => [
                    'desc' => 'Maximum penalty amount with filing currency',
                    'def' => 'Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BDT' => [
                    'desc' => 'Maximum penalty amount for the ticket',
                    'def' => 'Not allowed Before departure',
                    'situation' => 'Before departure',
                    'type' => 'Amount',
                ],
                'BNM' => [
                    'desc' => 'Minimum Penalty amount with sale currency',
                    'def' => 'Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNX' => [
                    'desc' => 'Maximum penalty amount with sale currency',
                    'def' => 'Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNF' => [
                    'desc' => 'Minimum penalty amount with filing currency',
                    'def' => 'Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNG' => [
                    'desc' => 'Maximum penalty amount with filing currency',
                    'def' => 'Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNT' => [
                    'desc' => 'Maximum penalty amount for the ticket',
                    'def' => 'Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'BNI' => [
                    'desc' => 'Minimum penalty amount for the ticket',
                    'def' => 'Not allowed Before departure no show',
                    'situation' => 'Before departure no show',
                    'type' => 'Amount',
                ],
                'ADM' => [
                    'desc' => 'Minimum Penalty amount with sale currency',
                    'def' => 'Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADX' => [
                    'desc' => 'Maximum penality amount After departure',
                    'def' => 'Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADF' => [
                    'desc' => 'Minimum Penalty amount with filing currency',
                    'def' => 'Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADT' => [
                    'desc' => 'Maximum penalty amount for the ticket',
                    'def' => 'Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ADG' => [
                    'desc' => 'Maximum penalty amount with filing currency',
                    'def' => 'Not allowed After departure',
                    'situation' => 'After departure',
                    'type' => 'Amount',
                ],
                'ANM' => [
                    'desc' => 'Minimum Penalty amount with sale currency',
                    'def' => 'Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANX' => [
                    'desc' => 'Maximum penalty amount with filing currency',
                    'def' => 'Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANF' => [
                    'desc' => 'Minimum Penalty amount with filing currency',
                    'def' => 'Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANG' => [
                    'desc' => 'Minimum Penalty amount with filing currency',
                    'def' => 'Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'ANT' => [
                    'desc' => 'Maximum penalty amount for the ticket',
                    'def' => 'Not allowed After departure no show',
                    'situation' => 'After departure no show',
                    'type' => 'Amount',
                ],
                'FFT' => [
                    'desc' => 'Part of rule is free form text',
                    'def' => 'No free form text',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'No free form text from', '1' => 'Part of the rule is free form text'],
                    'situation' => 'All'
                ],
                'BDA' => [
                    'desc' => 'Refund before departure allowed',
                    'def' => 'Refund before departure Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'Before departure'
                ],
                'BNR' => [
                    'desc' => 'Refund before departure when no show(ReBDNS) is allowed',
                    'def' => 'Refund before departure when no show is Not Allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'Before departure no show'
                ],
                'BNA' => [
                    'desc' => 'Refund before departure when no show(ReBDNS) is allowed',
                    'def' => 'Refund is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'Before departure no show'
                ],
                'ADA' => [
                    'desc' => 'Refund After departure(RiAD) is allowed',
                    'def' => 'Refund After departure is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => [],
                    'situation' => 'After departure'
                ],
                'ANW' => [
                    'desc' => 'Penalties can be waived for passenger and family death/illness',
                    'def' => 'No waiver After departure no show for passenger and family death/illness',
                    'type' => 'Flag',
                    'bolean_value' => ['Value hardcoded to 0'],
                    'situation' => 'After departure no show'
                ],
                'ANA' => [
                    'desc' => 'Refund After departure when no show(RiADNS) is allowed',
                    'def' => 'Refund After departure when no show is Not allowed',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => 'Not allowed', '1' => 'Allowed with restrictions'],
                    'situation' => 'After departure no show'
                ],
                'GUA' => [
                    'desc' => 'Amount Guaranteed flag for cat 31/33',
                    'def' => '0',
                    'type' => 'Flag',
                    'bolean_value' => ['0' => ': Not guaranteed', '1' => 'Guaranteed'],
                    'situation' => 'Before departure'
                ]
            ]
        ],
        '5' => [
            'title' => 'Advance Reservation/Ticketing',
            'assumption' => 'open segments including open returns are permitted',
            'codes' => [
                'ERD' => [
                    'desc' => 'Earliest reservation date before departure',
                    'def' => 'No restriction for the earliest reservation date before departure',
                    'situation' => '',
                    'type' => 'Date',
                ],
                'LRD' => [
                    'desc' => 'Latest reservation date before departure',
                    'def' => 'No restriction for the latest reservation date before departure',
                    'situation' => '',
                    'type' => 'Date',
                ],
                'ETD' => [
                    'desc' => 'Earliest ticketing date before departure',
                    'def' => 'No restriction for the earliest ticketing date before departure',
                    'situation' => '',
                    'type' => 'Date',
                ],
                'LTD' => [
                    'desc' => 'Latest ticketing date before departure',
                    'def' => 'No restriction for the latest ticketing date before departure',
                    'situation' => '',
                    'type' => 'Date',
                ],
                'LTR' => [
                    'desc' => 'Latest ticketing date after reservation',
                    'def' => 'No restriction for the latest ticketing date after reservatio',
                    'situation' => '',
                    'type' => 'Date',
                ],
            ]
        ],
        '6' => [
            'title' => 'Minimum Stay',
            'assumption' => 'no Minimum stay requirement',
            'codes' => [
                'MIS' => [
                    'desc' => 'Minimum stay date',
                    'def' => 'No restriction on Minimum stay',
                    'situation' => '',
                    'type' => 'Date',
                ],
            ]
        ],
        '7' => [
            'title' => 'Maximum Stay',
            'assumption' => 'no Maximum stay requirement',
            'codes' => [
                'MSP' => [
                    'desc' => 'Travel must be completed before',
                    'def' => 'No restriction on the date Travel must be completed',
                    'situation' => '',
                    'type' => 'Date',
                ],
                'MSC' => [
                    'desc' => 'Travel must commence before',
                    'def' => 'No restriction on the date Travel must commence',
                    'situation' => '',
                    'type' => 'Date',
                ],
            ]
        ],
    ];
    protected function repelSingleMode(&$set)
    {
        if (empty($set)) {
            return;
        }
        if (empty($set[0])) {
            $set = [$set];
        }
    }
}
