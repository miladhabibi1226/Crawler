<Fare_MasterPricerTravelBoardSearch>
    <numberOfUnit>
        <unitNumberDetail>
            <numberOfUnits>250</numberOfUnits>
            <typeOfUnit>RC</typeOfUnit>
        </unitNumberDetail>
        <unitNumberDetail>
            <numberOfUnits>{{ $adults + ($children ?? 0) }}</numberOfUnits>
            <typeOfUnit>PX</typeOfUnit>
        </unitNumberDetail>
    </numberOfUnit>
    @php
        $counter = 1;
    @endphp
    <paxReference>
        <ptc>ADT</ptc>
        @for ($i = 0; $i < $adults; $i++)
            <traveller>
                <ref>{{ $counter }}</ref>
            </traveller>
            @php
                $counter++;
            @endphp
        @endfor
    </paxReference>

    @if (!empty($children))
        <paxReference>
            <ptc>CH</ptc>
            @for ($i = 0; $i < $children; $i++)
                <traveller>
                    <ref>{{ $counter }}</ref>
                </traveller>
                @php
                    $counter++;
                @endphp
            @endfor
        </paxReference>
    @endif

    @php
        $counter = 1;
    @endphp
    @if (!empty($infants))
        <paxReference>
            <ptc>INF</ptc>
            @for ($i = 0; $i < $infants; $i++)
                <traveller>
                    <ref>{{ $counter }}</ref>
                    <infantIndicator>1</infantIndicator>
                </traveller>
                @php
                    $counter++;
                @endphp
            @endfor
        </paxReference>
    @endif

    <fareOptions>
        <pricingTickInfo>
            <pricingTicketing>
                <priceType>RU</priceType>
                <priceType>RP</priceType>
                <priceType>TAC</priceType>
            </pricingTicketing>
        </pricingTickInfo>
        <formOfPayment>
            <formOfPaymentDetails>
                <type>CC</type>
            </formOfPaymentDetails>
        </formOfPayment>
    </fareOptions>
    <travelFlightInfo>
        <cabinId>
            <cabinQualifier>MC</cabinQualifier>
            <cabin>{{ ['ECONOMY' => 'Y', 'FIRST' => 'F', 'BUSINESS' => 'C', 'PREMIUMECONOMY' => 'W'][strtoupper($cabin)] }}</cabin>
        </cabinId>
    </travelFlightInfo>
    @foreach ($legs as $leg)
    @php
        $originType = $leg['origin']['isCity'] == true ? 'C' : 'A';
        $destinationType = $leg['destination']['isCity'] == true ? 'C' : 'A';
    @endphp
        <itinerary>
            <requestedSegmentRef>
                <segRef>1</segRef>
            </requestedSegmentRef>
            <departureLocalization>
                <departurePoint>
                    <locationId>{{ $leg['origin']['abb'] }}</locationId>
                    <airportCityQualifier>{{ $originType }}</airportCityQualifier>
                </departurePoint>
            </departureLocalization>
            <arrivalLocalization>
                <arrivalPointDetails>
                    <locationId>{{ $leg['destination']['abb'] }}</locationId>
                    <airportCityQualifier>{{ $destinationType }}</airportCityQualifier>
                </arrivalPointDetails>
            </arrivalLocalization>
            <timeDetails>
                <firstDateTimeDetail>
                    <date>{{ Carbon\Carbon::create($leg['departure'])->format('dmy') }}</date>
                </firstDateTimeDetail>
            </timeDetails>
        </itinerary>
    @endforeach
</Fare_MasterPricerTravelBoardSearch>
