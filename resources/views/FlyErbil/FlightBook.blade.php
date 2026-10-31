<?xml version="1.0"?>
<OTA_AirBookRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsi="http://www.w3.org/2001/XMLSchemainstance" EchoToken="456789"
TimeStamp="2003-08-17T09:30:47-05:00" Target="Production" Version="2.001" SequenceNmbr="1" PrimaryLangID="en">
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
                    <DepartureAirport LocationCode="{{ $segment['DepartureAirport'] }}"/>
                    <ArrivalAirport LocationCode="{{ $segment['ArrivalAirport'] }}"/>
                    <MarketingAirline Code="{{ $segment['MarketingAirline'] }}"/>
                </FlightSegment>
                @endforeach
            </OriginDestinationOption>
            @endforeach
        </OriginDestinationOptions>
    </AirItinerary>
    <TravelerInfo>
    @foreach ($passengers as $type => $typePassengers)
        @foreach ($typePassengers as $key => $passenger)
            <AirTraveler PassengerTypeCode="{{ ['adults' => 'ADT', 'children' => 'CHD', 'infants' => 'INF'][$type] }}">
                <PersonName>
                    <NamePrefix>{{ $passenger['title'] }}</NamePrefix>
                    <GivenName>{{ $passenger['First_name'] }}</GivenName>
                    <Surname>{{ $passenger['Last_name'] }}</Surname>
                </PersonName>
                @if ($type == 'adults' && $key == 0)
                    <Email EmailType="1">{{ $passenger['Email'] }}</Email>
                @endif
                <TravelerRefNumber RPH="{{ $passenger['rph'] }}"/>
            </AirTraveler>
        @endforeach
    @endforeach 
    </TravelerInfo>
</OTA_AirBookRQ>
