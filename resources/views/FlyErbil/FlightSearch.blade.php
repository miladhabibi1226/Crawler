<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<OTA_AirLowFareSearchRQ xmlns="http://www.opentravel.org/OTA/2003/05"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.opentravel.org/OTA/2003/05
OTA_AirLowFareSearchRQ.xsd" Version="2.001"
    PrimaryLangID="en" DirectFlightsOnly="false" AvailableFlightsOnly="true">
    <POS>
        <Source ISOCurrency="{{ $sellerCurrency }}">
        </Source>
    </POS>
    <ProcessingInfo DisplayOrder="ByPriceLowToHigh" AvailabilityIndicator="true" />
    @foreach ($legs as $leg)
        <OriginDestinationInformation>
            <DepartureDateTime>{{ $leg['departure'] }}</DepartureDateTime>
            <OriginLocation LocationCode="{{ $leg['origin']['abb'] }}" />
            <DestinationLocation LocationCode="{{ $leg['destination']['abb'] }}" />
        </OriginDestinationInformation>
    @endforeach
    <SpecificFlightInfo>
        <BookingClassPref ResBookDesigCode="{{ ['Economy' => 'Y', 'First' => 'F', 'Business' => 'C'][$cabin] }}" />
    </SpecificFlightInfo>
    <TravelerInfoSummary>
        <AirTravelerAvail>
            <PassengerTypeQuantity Code="ADT" Quantity="{{ $adults }}" />
        </AirTravelerAvail>
        @if ($children > 0)
            <AirTravelerAvail>
                <PassengerTypeQuantity Code="CHD" Quantity="{{ $children }}" />
            </AirTravelerAvail>
        @endif
        @if ($infants > 0)
            <AirTravelerAvail>
                <PassengerTypeQuantity Code="INF" Quantity="{{ $infants }}" />
            </AirTravelerAvail>
        @endif
        <PriceRequestInformation FareQualifier="38" />
    </TravelerInfoSummary>
</OTA_AirLowFareSearchRQ>
