<Fare_InformativePricingWithoutPNR xmlns= {{ $action }}>
    <passengersGroup>
        <segmentRepetitionControl>
            <segmentControlDetails>
                <quantity>1</quantity>
                <numberOfUnits>{{$adults}}</numberOfUnits>
            </segmentControlDetails>
        </segmentRepetitionControl>
        <travellersID>
            @php
                $counter = 1;
            @endphp
            @for ($i = 0; $i < $adults; $i++)
            <travellerDetails>
                <measurementValue>{{$counter}}</measurementValue>
            </travellerDetails>
            @php
                $counter++;
            @endphp
            @endfor
        </travellersID>
    </passengersGroup>
    @if (!empty($children))
    <passengersGroup>
        <segmentRepetitionControl>
            <segmentControlDetails>
                <quantity>1</quantity>
                <numberOfUnits>{{$children}}</numberOfUnits>
            </segmentControlDetails>
        </segmentRepetitionControl>
        <travellersID>
            @for ($i = 0; $i < $children; $i++)
            <travellerDetails>
                <measurementValue>{{$counter}}</measurementValue>
            </travellerDetails>
            @php
                $counter++;
            @endphp
            @endfor
        </travellersID>
        <discountPtc>
            <valueQualifier>CH</valueQualifier>
        </discountPtc>
    </passengersGroup>
    @endif
    @if (!empty($infants))
    <passengersGroup>
        <segmentRepetitionControl>
            <segmentControlDetails>
                <quantity>1</quantity>
                <numberOfUnits>{{$infants}}</numberOfUnits>
            </segmentControlDetails>
        </segmentRepetitionControl>
        <travellersID>
            @php
                $counter = 1;
            @endphp
            @for ($i = 0; $i < $infants; $i++)
            <travellerDetails>
                <measurementValue>{{$counter}}</measurementValue>
            </travellerDetails>
            @php
                $counter++;
            @endphp
            @endfor
        </travellersID>
        <discountPtc>
            <valueQualifier>INF</valueQualifier>
            <fareDetails>
                <qualifier>766</qualifier>
            </fareDetails>
        </discountPtc>
    </passengersGroup>
    @endif
    @php
        $counter = 1;
    @endphp
    @foreach ($legsServiceInfo as $legIndex => $leg)
        @foreach ($leg['segments'] as $segIndex =>$segment)
    <segmentGroup>
        <segmentInformation>
            <flightDate>
                <departureDate>{{$rawTimes[$legIndex][$segIndex]['dateOfDeparture']}}</departureDate>
                <departureTime>{{$rawTimes[$legIndex][$segIndex]['timeOfDeparture']}}</departureTime>
                <arrivalDate>{{$rawTimes[$legIndex][$segIndex]['dateOfArrival']}}</arrivalDate>
                <arrivalTime>{{$rawTimes[$legIndex][$segIndex]['timeOfArrival']}}</arrivalTime>
        </flightDate>
        <boardPointDetails>
                <trueLocationId>{{$segment['departure']['airport']['abb']}}</trueLocationId>
        </boardPointDetails>
        <offpointDetails>
                <trueLocationId>{{$segment['arrival']['airport']['abb']}}</trueLocationId>
        </offpointDetails>
        <companyDetails>
                <marketingCompany>{{$segment['airline']['supplier_key']}}</marketingCompany>
                <operatingCompany>{{$segment['operatingAirline']['supplier_key']}}</operatingCompany>
        </companyDetails>
        <flightIdentification>
                <flightNumber>{{$segment['flight_number']}}</flightNumber>
                <bookingClass>{{$segment['resBookDesigCode']}}</bookingClass>
        </flightIdentification>
        <flightTypeDetails>
            <flightIndicator>{{$legIndex+1}}</flightIndicator>
        </flightTypeDetails>
        <itemNumber>{{$counter}}</itemNumber>
        </segmentInformation>  
    </segmentGroup>
            @php
                if ($segIndex != 0) {
                    $needsPOG[] = $counter;
                }
                $counter++;
            @endphp
        @endforeach
    @endforeach
    <pricingOptionGroup>
        <pricingOptionKey>
            <pricingOptionKey>RLO</pricingOptionKey>
        </pricingOptionKey>
    </pricingOptionGroup>
    <pricingOptionGroup>
        <pricingOptionKey>
            <pricingOptionKey>RP</pricingOptionKey>
        </pricingOptionKey>
    </pricingOptionGroup>
    <pricingOptionGroup>
        <pricingOptionKey>
            <pricingOptionKey>RU</pricingOptionKey>
        </pricingOptionKey>
    </pricingOptionGroup>
    @if (!empty($needsPOG))
    <pricingOptionGroup>
        <pricingOptionKey>
            <pricingOptionKey>CON</pricingOptionKey>
        </pricingOptionKey>
        <paxSegTstReference>
            @foreach ($needsPOG as $segmentReference)
            <referenceDetails>
                <type>S</type>
                <value>{{$segmentReference}}</value>
            </referenceDetails>
            @endforeach
        </paxSegTstReference>
    </pricingOptionGroup>
    @endif
</Fare_InformativePricingWithoutPNR>

