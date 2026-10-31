<OTA_AirLowFareSearchRQ xmlns="http://www.opentravel.org/OTA/2003/05"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xsi:schemaLocation="http://www.opentravel.org/OTA/2003/05 
  OTA_AirLowFareSearchRQ.xsd" EchoToken="50987" TimeStamp="2019-08-22T05:44:10+05:30"
  Target="Test" Version="2.001" SequenceNmbr="1" PrimaryLangID="En-us">
    <POS>
        <Source AirlineVendorID="{{$airline}}" ISOCountry="{{$country}}" ISOCurrency="{{$currency}}">
            <RequestorID Type="5" ID="{{$agentId}}"/>
        </Source>
    </POS>
    <OriginDestinationInformation>
        <DepartureDateTime>2021-10-17</DepartureDateTime>
        <OriginLocation LocationCode="BGW"/>
        <DestinationLocation LocationCode="EBL"/>
    </OriginDestinationInformation>
    <TravelPreferences >
        <CabinPref  Cabin="Economy"/>
    </TravelPreferences>
    <TravelerInfoSummary>
        <AirTravelerAvail>
            <PassengerTypeQuantity Code="ADT" Quantity="1"/>
            <PassengerTypeQuantity Code="CHD" Quantity="0"/>
            <PassengerTypeQuantity Code="INF" Quantity="0"/>
        </AirTravelerAvail>
    </TravelerInfoSummary>
    <ProcessingInfo SearchType="STANDARD"/>
</OTA_AirLowFareSearchRQ>