<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <univ:AirCreateReservationReq RetainReservation="None" RetrieveProviderReservationDetails="true"
            TraceId="{{ $traceId }}" TargetBranch="{{ $targetBranch }}"
            xmlns:univ="http://www.travelport.com/schema/universal_v52_0">
            <com:BillingPointOfSaleInfo OriginApplication="UAPI"
                xmlns:com="http://www.travelport.com/schema/common_v52_0" />
            @php
                use Illuminate\Support\Carbon;
            @endphp
            @foreach ($passengers as $type => $typePassengers)
                @foreach ($typePassengers as $index => $passenger)
                    @php
                        $passengerType = ['adults' => 'ADT', 'children' => 'CNN', 'infants' => 'INF'][$type];
                        $baseKey = 1000000;
                        $phoneRandomKey = $baseKey + rand(0, 9999);
                    @endphp
                    <com:BookingTraveler Key="{{ $passengerUuids[$type][$index] }}"
                        DOB="{{ $passenger['Date of birth'] }}" TravelerType="{{ $passengerType }}"
                        xmlns:com="http://www.travelport.com/schema/common_v52_0">
                        @php
                            $formattedBirthDate = Carbon::parse($passenger['Date of birth'])->format('dMy');
                            $formattedPassporthDate = Carbon::parse($passenger['Passport Expiry Date'])->format('dMy');
                            $age = Carbon::parse($passenger['Date of birth'])->age;
                        @endphp
                        <com:BookingTravelerName Prefix="{{ $passenger['title'] }}"
                            First="{{ $passenger['First_name'] }}" Last="{{ $passenger['Last_name'] }}" />
                        @if ($passengerType == 'ADT')
                            <com:PhoneNumber Key="{{ $phoneRandomKey }}"
                                CountryCode="{{ $passengers['adults'][0]['Phone']['countryPhoneCode'] }}"
                                Number="{{ $passengers['adults'][0]['Phone']['phone'] }}" />
                            <com:Email EmailID="{{ $passengers['adults'][0]['Email'] }}" />
                            <com:SSR Key="{{ $passengerUuids[$type][$index] }}" Type="DOCS" Status="NN"
                                Carrier="{{ $validatingAirline['abb'] }}"
                                FreeText="{{ 'p/' . $passenger['CountriesInfo']['Nationality']['abb'] . '/' . $passenger['Passport Number'] . '/' . $passenger['CountriesInfo']['Nationality']['abb'] . '/' . $formattedBirthDate . '/' . substr($passenger['Gender'], 0, 1) . '/' . $formattedPassporthDate . '/' . $passenger['Last_name'] . '/' . $passenger['First_name'] }}" />
                        @endif
                        @if ($passengerType == 'CNN')
                            @php
                                $age = str_pad($age, 2, '0', STR_PAD_LEFT);
                            @endphp
                            <com:SSR Key="{{ $passengerUuids[$type][$index] }}" Type="DOCS" Status="NN"
                                Carrier="{{ $validatingAirline['abb'] }}"
                                FreeText="{{ 'p/' . $passenger['CountriesInfo']['Nationality']['abb'] . '/' . $passenger['Passport Number'] . '/' . $passenger['CountriesInfo']['Nationality']['abb'] . '/' . $formattedBirthDate . '/' . substr($passenger['Gender'], 0, 1) . '/' . $formattedPassporthDate . '/' . $passenger['Last_name'] . '/' . $passenger['First_name'] }}" />
                            <com:NameRemark>
                                <com:RemarkData>{{ 'P-C' . $age . ' DOB' . $formattedBirthDate }}</com:RemarkData>
                            </com:NameRemark>
                        @endif
                        @if ($passengerType == 'INF')
                            <com:SSR Key="{{ $passengerUuids[$type][$index] }}" Type="DOCS" Status="NN"
                                Carrier="{{ $validatingAirline['abb'] }}"
                                FreeText="{{ 'p/' . $passenger['CountriesInfo']['Nationality']['abb'] . '/' . $passenger['Passport Number'] . '/' . $passenger['CountriesInfo']['Nationality']['abb'] . '/' . $formattedBirthDate . '/' . substr($passenger['Gender'], 0, 1) . 'I' . '/' . $formattedPassporthDate . '/' . $passenger['Last_name'] . '/' . $passenger['First_name'] }}" />
                            <com:NameRemark>
                                <com:RemarkData>{{ $formattedBirthDate }}</com:RemarkData>
                            </com:NameRemark>
                        @endif

                    </com:BookingTraveler>
                @endforeach
            @endforeach
            <air:AirPricingSolution Key="{{ $AirPricingSolution['Key'] }}"
                TotalPrice="{{ $AirPricingSolution['TotalPrice'] }}"
                BasePrice="{{ $AirPricingSolution['BasePrice'] }}"
                ApproximateTotalPrice="{{ $AirPricingSolution['ApproximateTotalPrice'] }}"
                ApproximateBasePrice="{{ $AirPricingSolution['ApproximateBasePrice'] }}"
                EquivalentBasePrice="{{ $AirPricingSolution['EquivalentBasePrice'] ?? '' }}"
                Taxes="{{ $AirPricingSolution['Taxes'] }}"
                ApproximateTaxes="{{ $AirPricingSolution['ApproximateTaxes'] }}"
                xmlns:air="http://www.travelport.com/schema/air_v52_0">

                {!! $segmentXml !!}

                @foreach ($AirPricingSolution['AirPricingInfo'] as $key => $AirPricingInfo)
                    <air:AirPricingInfo Key="{{ $AirPricingInfo['Key'] }}"
                        TotalPrice="{{ $AirPricingInfo['TotalPrice'] }}"
                        BasePrice="{{ $AirPricingInfo['BasePrice'] }}"
                        ApproximateTotalPrice="{{ $AirPricingInfo['ApproximateTotalPrice'] ?? '' }}"
                        ApproximateBasePrice="{{ $AirPricingInfo['ApproximateBasePrice'] ?? '' }}"
                        ApproximateTaxes="{{ $AirPricingInfo['ApproximateTaxes'] ?? '' }}"
                        Taxes="{{ $AirPricingInfo['Taxes'] ?? '' }}"
                        LatestTicketingTime="{{ $AirPricingInfo['LatestTicketingTime'] ?? '' }}"
                        PricingMethod="{{ $AirPricingInfo['PricingMethod'] ?? '' }}"
                        PlatingCarrier="{{ $AirPricingInfo['PlatingCarrier'] ?? '' }}" ProviderCode="1G">

                        {!! $fareInfoXml[$key] !!}

                        @if(!empty($AirPricingInfo['BookingInfo']))
                        @foreach ($AirPricingInfo['BookingInfo'] as $bookingInfo)
                            <air:BookingInfo BookingCode="{{ $bookingInfo['BookingCode'] }}"
                                CabinClass="{{ $bookingInfo['CabinClass'] }}"
                                FareInfoRef="{{ $bookingInfo['FareInfoRef'] }}"
                                SegmentRef="{{ $bookingInfo['SegmentRef'] }}"
                                HostTokenRef="{{ $bookingInfo['HostTokenRef'] }}" />
                        @endforeach
                        @endif

                        @if(!empty($AirPricingInfo['TaxInfo']))
                        @foreach ($AirPricingInfo['TaxInfo'] as $taxInfo)
                            <air:TaxInfo Category="{{ $taxInfo['Category'] }}" Amount="{{ $taxInfo['Amount'] }}"
                                Key="{{ $taxInfo['Key'] }}" />
                        @endforeach
                        @endif

                        @if(!empty($AirPricingInfo['FareCalc']))
                        @foreach ($AirPricingInfo['FareCalc'] as $fareCale)
                            <air:FareCalc>{{ $fareCale['fareCalc'] }}</air:FareCalc>
                        @endforeach
                        @endif

                        @foreach ($AirPricingInfo['PassengerType'] as $index => $passengerType)
                            @php
                                $pType = $passengerType['Code'];
                                $PT = ['ADT' => 'adults', 'CNN' => 'children', 'INF' => 'infants'][$pType];
                            @endphp
                            <air:PassengerType Code="{{ $passengerType['Code'] }}"
                                BookingTravelerRef="{{ array_shift($passengerUuids[$PT])}}" />
                        @endforeach
                    </air:AirPricingInfo>
                @endforeach

                {!! $fareNotesXml !!}

                {!! $hostTokenXml !!}

            </air:AirPricingSolution>
            <com:ActionStatus Type="ACTIVE" ProviderCode="1G"
                xmlns:com="http://www.travelport.com/schema/common_v52_0" />
        </univ:AirCreateReservationReq>
    </soap:Body>
</soap:Envelope>
