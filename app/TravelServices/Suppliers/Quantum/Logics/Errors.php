<?php

namespace App\TravelServices\Suppliers\Quantum\Logics;

use App\TravelServices\Suppliers\Accelaero\Logics\Errors as AccelaeroErrors;
use App\TravelServices\Suppliers\Amadeus\Logics\Errors as AmadeusErrors;
use App\TravelServices\Suppliers\Avtra\Logics\Errors as AvtraErrors;
use App\TravelServices\Suppliers\BasisFly\Logics\Errors as BasisFlyErrors;
use App\TravelServices\Suppliers\CityNet\Logics\Errors as CityNetErrors;
use App\TravelServices\Suppliers\Faranegar\Logics\Errors as FaranegarErrors;
use App\TravelServices\Suppliers\FlyErbil\Logics\Errors as FlyErbilErrors;
use App\TravelServices\Suppliers\GRS\Logics\Errors as GRSErrors;
use App\TravelServices\Suppliers\ITours\Logics\Errors as IToursErrors;
use App\TravelServices\Suppliers\Kplus\Logics\Errors as KplusErrors;
use App\TravelServices\Suppliers\MystiFly\Logics\Errors as MystiFlyErrors;
use App\TravelServices\Suppliers\Navitaire\Logics\Errors as NavitaireErrors;
use App\TravelServices\Suppliers\Nira\Logics\Errors as NiraErrors;
use App\TravelServices\Suppliers\Parto\Logics\Errors as PartoErrors;
use App\TravelServices\Suppliers\Sabre\Logics\Errors as SabreErrors;
use App\TravelServices\Suppliers\Sepehr\Logics\Errors as SepehrErrors;
use App\TravelServices\Suppliers\Sepehran\Logics\Errors as SepehranErrors;
use App\TravelServices\Suppliers\TBO\Logics\Errors as TBOErrors;
use App\TravelServices\Suppliers\Travelport\Logics\Errors as TravelportErrors;

trait Errors
{
    use AvtraErrors,
        CityNetErrors,
        FlyErbilErrors,
        IToursErrors,
        PartoErrors,
        SepehrErrors,
        TBOErrors,
        AccelaeroErrors,
        AmadeusErrors,
        BasisFlyErrors,
        FaranegarErrors,
        GRSErrors,
        KplusErrors,
        MystiFlyErrors,
        NavitaireErrors,
        NiraErrors,
        SabreErrors,
        SepehranErrors,
        TravelportErrors {
        AvtraErrors::errorPatterns as AvtraErrorPatterns;
        CityNetErrors::errorPatterns as CityNetErrorPatterns;
        FlyErbilErrors::errorPatterns as FlyErbilErrorPatterns;
        IToursErrors::errorPatterns as IToursErrorPatterns;
        PartoErrors::errorPatterns as PartoErrorPatterns;
        SepehrErrors::errorPatterns as SepehrErrorPatterns;
        TBOErrors::errorPatterns as TBOErrorPatterns;
        AccelaeroErrors::errorPatterns as AccelaeroErrorPatterns;
        AmadeusErrors::errorPatterns as AmadeusErrorPatterns;
        BasisFlyErrors::errorPatterns as BasisFlyErrorPatterns;
        FaranegarErrors::errorPatterns as FaranegarErrorPatterns;
        GRSErrors::errorPatterns as GRSErrorPatterns;
        KplusErrors::errorPatterns as KplusErrorPatterns;
        MystiFlyErrors::errorPatterns as MystiFlyErrorPatterns;
        NavitaireErrors::errorPatterns as NavitaireErrorPatterns;
        NiraErrors::errorPatterns as NiraErrorPatterns;
        SabreErrors::errorPatterns as SabreErrorPatterns;
        SepehranErrors::errorPatterns as SepehranErrorPatterns;
        TravelportErrors::errorPatterns as TravelportErrorPatterns;
    }

    protected function errorPatterns()
    {
        ///> until i have time to write  a script to automatically get all the suppliers' errors
        $suppliers = [
            'AvtraErrorPatterns',
            'CityNetErrorPatterns',
            'FlyErbilErrorPatterns',
            'IToursErrorPatterns',
            'PartoErrorPatterns',
            'SepehrErrorPatterns',
            'TBOErrorPatterns',
            'AccelaeroErrorPatterns',
            'AmadeusErrorPatterns',
            'BasisFlyErrorPatterns',
            'FaranegarErrorPatterns',
            'GRSErrorPatterns',
            'KplusErrorPatterns',
            'MystiFlyErrorPatterns',
            'NavitaireErrorPatterns',
            'NiraErrorPatterns',
            'SabreErrorPatterns',
            'SepehranErrorPatterns',
            'TravelportErrorPatterns'
        ];
        $allErrors = [];
        foreach ($suppliers as $supplierErrorMethod) {
            $allErrors = array_merge($allErrors, $this->$supplierErrorMethod());
        }
        return $allErrors;
    }
}
