<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:hot="http://TekTravel/HotelBookingApi">
    <soap:Header xmlns:wsa='http://www.w3.org/2005/08/addressing' >
        <hot:Credentials UserName="{{$username}}" Password="{{$password}}">
        </hot:Credentials>
        <wsa:Action>http://TekTravel/HotelBookingApi/AvailabilityAndPricing</wsa:Action>
        <wsa:To>{{$url}}</wsa:To>
    </soap:Header>
    <soap:Body>
        <hot:AvailabilityAndPricingRequest>
            <hot:ResultIndex>{{$resultIndex}}</hot:ResultIndex>
            <hot:SessionId>{{$session}}</hot:SessionId>
            <hot:OptionsForBooking>
                <hot:FixedFormat>true</hot:FixedFormat>
                <hot:RoomCombination>
                    @foreach ($combination as $room)
                        <hot:RoomIndex>{{$room}}</hot:RoomIndex>
                    @endforeach
                </hot:RoomCombination>
            </hot:OptionsForBooking>
        </hot:AvailabilityAndPricingRequest>
    </soap:Body>
</soap:Envelope>

