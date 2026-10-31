<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:hot="http://TekTravel/HotelBookingApi">
    <soap:Header xmlns:wsa='http://www.w3.org/2005/08/addressing' >
        <hot:Credentials UserName="{{$username}}" Password="{{$password}}"></hot:Credentials>
        <wsa:Action>http://TekTravel/HotelBookingApi/HotelCancellationPolicy</wsa:Action>
        <wsa:To>{{$url}}</wsa:To>
    </soap:Header>
    <soap:Body>
        <hot:HotelCancellationPolicyRequest>
            <hot:ResultIndex>{{$resultIndex}}</hot:ResultIndex>
            <hot:SessionId>{{$session}}</hot:SessionId>
            <hot:OptionsForBooking>
                <hot:FixedFormat>false</hot:FixedFormat>
                <hot:RoomCombination>
                    <hot:RoomIndex>{{$roomIndex}}</hot:RoomIndex>
                </hot:RoomCombination>
            </hot:OptionsForBooking>
        </hot:HotelCancellationPolicyRequest>
    </soap:Body>
</soap:Envelope>