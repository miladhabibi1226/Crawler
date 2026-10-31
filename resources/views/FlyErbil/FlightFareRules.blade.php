<?xml version="1.0"?>
<OTA_AirPriceRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsi="http://www.w3.org/2001/XMLSchemainstance" EchoToken="36732" TimeStamp="2003-11-14T10:30:00" Target="Production" Version="2.001" SequenceNmbr="284" PrimaryLangID="en" Type="PerSegment">
    <POS>
        {{-- since we're not using the price info provided in the response of this request, we're hard coding curency,
             if we start to use those price info, seller's currency needs to be used --}}
        <Source ISOCurrency="USD">
        </Source>
    </POS>
    <AirItinerary>
        <OriginDestinationOptions>
            @foreach ($sellerInfo['bookingRequestData'] as $legSegments)
            <OriginDestinationOption>
                @foreach ($legSegments as $segment)
                <FlightSegment DepartureDateTime="{{ $segment['DepartureDateTime'] }}" FlightNumber="{{ $segment['FlightNumber'] }}" ResBookDesigCode="{{ $segment['ResBookDesigCode'] }}">
                    <DepartureAirport LocationCode="{{ $segment['DepartureAirport'] }}"/>
                    <ArrivalAirport LocationCode="{{ $segment['ArrivalAirport'] }}"/>
                    <MarketingAirline Code="{{ $segment['MarketingAirline'] }}"/>
                </FlightSegment>
                @endforeach
            </OriginDestinationOption>
            @endforeach
        </OriginDestinationOptions>
    </AirItinerary>
    <TravelerInfoSummary>
        <AirTravelerAvail>
            {{-- fake passenger info for at least one adult must be entered --}}
            <PassengerTypeQuantity Code="ADT" Quantity="1"/>
            <AirTraveler Gender="Unknown" PassengerTypeCode="ADT">
                <PersonName>
                    <GivenName>Passenger1</GivenName>
                    <Surname>Traveler</Surname>
                </PersonName>
                <TravelerRefNumber RPH="1"/>
            </AirTraveler>
        </AirTravelerAvail>
        {{-- ////////>>> we can list all the passenger types that we have to get base fare per each type. but we won't do it now because there is no use at this step --}}
        {{-- @php $counter = 1; @endphp
        @for ($i = 1; $i <= $queryParams['adults']; $i++)
        <AirTravelerAvail>
            <PassengerTypeQuantity Code="ADT" Quantity="1"/>
            <AirTraveler Gender="Unknown" PassengerTypeCode="ADT">
                <PersonName>
                    <GivenName>Passenger{{ $counter }}</GivenName>
                    <Surname>Traveler</Surname>
                </PersonName>
                <TravelerRefNumber RPH="{{ $counter }}"/>
            </AirTraveler>
        </AirTravelerAvail>
        @php $counter++ @endphp
        @endfor
        @for ($i = 1; $i <= $queryParams['children']; $i++)
         <AirTravelerAvail>
            <PassengerTypeQuantity Code="CHD" Quantity="1"/>
            <AirTraveler Gender="Unknown" PassengerTypeCode="CHD">
                <PersonName>
                    <GivenName>Passenger{{ $counter }}</GivenName>
                    <Surname>Traveler</Surname>
                </PersonName>
                <TravelerRefNumber RPH="{{$counter}}"/>
            </AirTraveler>
        </AirTravelerAvail>
        @php $counter++ @endphp
        @endfor
        @for ($i = 1; $i <= $queryParams['infants']; $i++)
         <AirTravelerAvail>
            <PassengerTypeQuantity Code="INF" Quantity="1"/>
            <AirTraveler Gender="Unknown" PassengerTypeCode="INF">
                <PersonName>
                    <GivenName>Passenger{{ $counter }}</GivenName>
                    <Surname>Traveler</Surname>
                </PersonName>
                <TravelerRefNumber RPH="{{$counter}}"/>
            </AirTraveler>
        </AirTravelerAvail>
        @php $counter++ @endphp
        @endfor --}}
    </TravelerInfoSummary>
</OTA_AirPriceRQ>
