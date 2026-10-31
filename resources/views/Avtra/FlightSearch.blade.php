<OTA_AirLowFareSearchRQ xmlns="http://www.opentravel.org/OTA/2003/05"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.opentravel.org/OTA/2003/05 
  OTA_AirLowFareSearchRQ.xsd" EchoToken="50987"
    TimeStamp="{{ $time }}" Target="Test" Version="2.001" SequenceNmbr="1" PrimaryLangID="En-us">
    <POS>
        <Source AirlineVendorID="{{ $airlineCode }}" ISOCountry="{{ $countryCode }}"
            ISOCurrency="{{ $sellerCurrency }}">
        <RequestorID Type="5" ID="{{ $agentId }}" />
        </Source>
    </POS>
    @foreach ($legs as $leg)
        <OriginDestinationInformation>
            <DepartureDateTime>{{ $leg['departure'] }}</DepartureDateTime>
            <OriginLocation LocationCode="{{ $leg['origin']['abb'] }}" />
            <DestinationLocation LocationCode="{{ $leg['destination']['abb'] }}" />
        </OriginDestinationInformation>
    @endforeach
    <TravelPreferences>
        <CabinPref Cabin="{{ $cabin }}" />
    </TravelPreferences>
    <TravelerInfoSummary>
        <AirTravelerAvail>
            <PassengerTypeQuantity Code="ADT" Quantity="{{ $adults }}" />
            <PassengerTypeQuantity Code="CHD" Quantity="{{ $children ?? 0 }}" />
            <PassengerTypeQuantity Code="INF" Quantity="{{ $infants ?? 0 }}" />
        </AirTravelerAvail>
    </TravelerInfoSummary>
    <ProcessingInfo SearchType="STANDARD" />
</OTA_AirLowFareSearchRQ>
