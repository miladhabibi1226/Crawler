<?xml version="1.0"?>
<OTA_AirDemandTicketRQ xmlns="http://www.opentravel.org/OTA/2003/05" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" EchoToken="456789" TimeStamp="2006-09-15T10:30:33-06:00" Target="Production" Version="2.001" SequenceNmbr="1">
    <POS>
        <Source ISOCurrency="{{ $sellerCurrency }}">
        </Source>
    </POS>
    <DemandTicketDetail>
        <MessageFunction Function="ET"/>
        <BookingReferenceID Type="{{ $pnrType }}" ID="{{ $pnr }}">
        </BookingReferenceID>
        <PaymentInfo PaymentType="1" PassengerRPH="{{ $rphGroup }}" CurrencyCode="{{ $sellerCurrency }}" Amount="{{ $totalPrice }}">
 </PaymentInfo>
 @foreach ($passengers as $type => $typePassengers)
     @foreach ($typePassengers as $key => $passenger)
     <PassengerName RPH="{{ $passenger['rph'] }}">
            <NamePrefix>"{{ $passenger['title'] }}"</NamePrefix>
            <GivenName>"{{ $passenger['First_name'] }}"</GivenName>
            <Surname>"{{ $passenger['Last_name'] }}"</Surname>
    </PassengerName>
    @endforeach
 @endforeach
    </DemandTicketDetail>
</OTA_AirDemandTicketRQ>
