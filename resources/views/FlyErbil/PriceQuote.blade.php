<?xml version="1.0"?>
<OTA_AirPriceRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.opentravel.org/OTA/2003/05 OTA_AirPriceRQ.xsd" EchoToken="36732"
    TimeStamp="2003-11-14T10:30:00" Target="Production" Version="2.001" SequenceNmbr="284" PrimaryLangID="en"
    Type="PerSegment">
    <POS>
        <Source ISOCurrency="{{ $sellerCurrency }}">
        </Source>
    </POS>
    <AirItinerary>
        <OriginDestinationOptions>
            @foreach ($sellerInfo['bookingRequestData'] as $legSegments)
                <OriginDestinationOption>
                    @foreach ($legSegments as $segment)
                        <FlightSegment DepartureDateTime="{{ $segment['DepartureDateTime'] }}" FlightNumber="{{ $segment['FlightNumber'] }}" ResBookDesigCode="{{ $segment['ResBookDesigCode'] }}">
                            <DepartureAirport LocationCode="{{ $segment['DepartureAirport'] }}" />
                            <ArrivalAirport LocationCode="{{ $segment['ArrivalAirport'] }}" />
                            <MarketingAirline Code="{{ $segment['MarketingAirline'] }}" />
                        </FlightSegment>
                    @endforeach
                </OriginDestinationOption>
            @endforeach
        </OriginDestinationOptions>
    </AirItinerary>
    <TravelerInfoSummary>
        @foreach ($passengers as $type => $typePassengers)
            <AirTravelerAvail>
                <PassengerTypeQuantity
                    Code="{{ ['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'][$type] }}"
                    Quantity="{{ count($typePassengers) }}" />
                @foreach ($typePassengers as $key => $passenger)
                    <AirTraveler Gender="{{ ucfirst($passenger['title']) == 'Mr' ? 'Male' : 'Female' }}"
                        PassengerTypeCode="ADT">
                        <PersonName>
                            <GivenName>{{ $passenger['First_name'] }}</GivenName>
                            <Surname>{{ $passenger['Last_name'] }}</Surname>
                        </PersonName>
                        <TravelerRefNumber RPH="{{ $passenger['rph'] }}" />
                    </AirTraveler>
                @endforeach
            </AirTravelerAvail>
        @endforeach
    </TravelerInfoSummary>
</OTA_AirPriceRQ>
