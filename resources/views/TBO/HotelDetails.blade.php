<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:hot="http://TekTravel/HotelBookingApi">
    <soap:Header xmlns:wsa='http://www.w3.org/2005/08/addressing' >
        <hot:Credentials UserName="{{$username}}" Password="{{$password}}"></hot:Credentials>
        <wsa:Action>http://TekTravel/HotelBookingApi/HotelDetails</wsa:Action>
        <wsa:To>{{$url}}</wsa:To>
    </soap:Header>
    <soap:Body>
        <hot:HotelDetailsRequest>
            <hot:HotelCode>{{$hotelCode}}</hot:HotelCode>
            <hot:SessionId>{{$session}}</hot:SessionId>
            <hot:ResultIndex>{{$resultIndex}}</hot:ResultIndex>
        </hot:HotelDetailsRequest>
    </soap:Body>
</soap:Envelope>