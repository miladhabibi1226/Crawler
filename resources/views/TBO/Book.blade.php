<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:hot="http://TekTravel/HotelBookingApi">
    <soap:Header xmlns:wsa='http://www.w3.org/2005/08/addressing'>
        <hot:Credentials UserName="{{ $username }}" Password="{{ $password }}">
        </hot:Credentials>
        <wsa:Action>http://TekTravel/HotelBookingApi/HotelBook</wsa:Action>
        <wsa:To>{{ $url }}</wsa:To>
    </soap:Header>
    <soap:Body>
        <hot:HotelBookRequest>
            <hot:ClientReferenceNumber>{{ $clientRef }}</hot:ClientReferenceNumber>
            <hot:GuestNationality>{{ $guestNationality }}</hot:GuestNationality>
            <hot:Guests>
                @foreach ($passengers as $key => $room)
                    @foreach ($room['adult'] ?? [] as $passenger)
                        <hot:Guest LeadGuest="{{ $loop->parent->first && $loop->first ? 'true' : 'false' }}"
                            GuestType="Adult" GuestInRoom="{{ $key + 1 }}">
                            <hot:Title>{{ $passenger['title'] }}</hot:Title>
                            <hot:FirstName>{{ $passenger['First_name'] }}</hot:FirstName>
                            <hot:LastName>{{ $passenger['Last_name'] }}</hot:LastName>
                            <hot:Age>{{ $passenger['Age'] }}</hot:Age>
                        </hot:Guest>
                    @endforeach
                    @foreach ($room['child'] ?? [] as $passenger)
                        <hot:Guest LeadGuest="false" GuestType="Child" GuestInRoom="{{ $key + 1 }}">
                            <hot:Title>{{ $passenger['title'] }}</hot:Title>
                            <hot:FirstName>{{ $passenger['First_name'] }}</hot:FirstName>
                            <hot:LastName>{{ $passenger['Last_name'] }}</hot:LastName>
                            <hot:Age>{{ $passenger['Age'] }}</hot:Age>
                        </hot:Guest>
                    @endforeach
                @endforeach
            </hot:Guests>
            <hot:PaymentInfo VoucherBooking="{{ $voucherBooking }}" PaymentModeType="Limit">
            </hot:PaymentInfo>
            <hot:SessionId>{{ $session }}</hot:SessionId>
            <hot:NoOfRooms>{{ count($rooms) }}</hot:NoOfRooms>
            <hot:ResultIndex>{{ $resultIndex }}</hot:ResultIndex>
            <hot:HotelCode>{{ $hotelCode }}</hot:HotelCode>
            <hot:HotelRooms>
                @foreach ($rooms as $room)
                    <hot:HotelRoom>
                        <hot:RoomIndex>{{ $room['serviceInfo']['index'] }}</hot:RoomIndex>
                        <hot:RoomTypeCode>{{ $room['serviceInfo']['typeCode'] }}</hot:RoomTypeCode>
                        <hot:RatePlanCode>{{ $room['serviceInfo']['planCode'] }}</hot:RatePlanCode>
                        <hot:RoomRate RoomFare="{{ $room['price'] }}" />
                        @if (!empty($room['serviceInfo']['suppliments']))
                            <hot:Supplements>
                                <hot:SuppInfo SuppID="1" SuppChargeType="AtProperty"
                                    Price="{{ $room['serviceInfo']['suppliments']['Supplement']['@attributes']['Price'] }}"
                                    SuppIsSelected="false" />
                            </hot:Supplements>
                        @endif
                    </hot:HotelRoom>
                @endforeach
            </hot:HotelRooms>
        </hot:HotelBookRequest>
    </soap:Body>
</soap:Envelope>
