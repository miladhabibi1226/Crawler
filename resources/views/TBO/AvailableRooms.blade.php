<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:hot="http://TekTravel/HotelBookingApi">
    <soap:Header xmlns:wsa='http://www.w3.org/2005/08/addressing' >
        <hot:Credentials UserName="{{$username}}" Password="{{$password}}"></hot:Credentials>
        <wsa:Action>http://TekTravel/HotelBookingApi/AvailableHotelRooms</wsa:Action>
        <wsa:To>{{$url}}</wsa:To>
    </soap:Header>
    <soap:Body>
        <hot:HotelRoomAvailabilityRequest>
            <hot:SessionId>{{$session}}</hot:SessionId>
            <hot:ResultIndex>{{$resultIndex}}</hot:ResultIndex>
            <hot:HotelCode>{{$hotelCode}}</hot:HotelCode>
            <hot:IsCancellationPolicyRequired>true</hot:IsCancellationPolicyRequired>
            <hot:ResponseTime>0</hot:ResponseTime>
        </hot:HotelRoomAvailabilityRequest>
    </soap:Body>
</soap:Envelope>