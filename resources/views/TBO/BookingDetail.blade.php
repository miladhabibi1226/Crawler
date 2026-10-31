<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:hot="http://TekTravel/HotelBookingApi">
    <soap:Header xmlns:wsa='http://www.w3.org/2005/08/addressing' >
        <hot:Credentials UserName="{{$username}}" Password="{{$password}}"> </hot:Credentials>
        <wsa:Action>http://TekTravel/HotelBookingApi/HotelBookingDetail</wsa:Action>
        <wsa:To>{{$url}}</wsa:To>
    </soap:Header>
    <soap:Body>
        <hot:HotelBookingDetailRequest>
            <hot:ConfirmationNo>{{$pnr}}</hot:ConfirmationNo>
        </hot:HotelBookingDetailRequest>
    </soap:Body>
</soap:Envelope>