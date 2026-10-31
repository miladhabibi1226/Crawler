<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:hot="http://TekTravel/HotelBookingApi">
    <soap:Header xmlns:wsa='http://www.w3.org/2005/08/addressing'>
        <hot:Credentials UserName="{{$username}}" Password="{{$password}}"></hot:Credentials>
        <wsa:Action>http://TekTravel/HotelBookingApi/HotelSearch</wsa:Action>
        <wsa:To>{{$url}}</wsa:To>
    </soap:Header>
    <soap:Body>
        <hot:HotelSearchRequest>
            <hot:CheckInDate>{{$checkin}}</hot:CheckInDate>
            <hot:CheckOutDate>{{$checkout}}</hot:CheckOutDate>
            <hot:CityId>{{$city_id}}</hot:CityId>
            <hot:IsNearBySearchAllowed>false</hot:IsNearBySearchAllowed>
            <hot:NoOfRooms>{{$rooms_no}}</hot:NoOfRooms>
            <hot:GuestNationality>{{$nationality_country['abb']}}</hot:GuestNationality>
            <hot:RoomGuests>
                @foreach ($rooms_detail as $item)
                <hot:RoomGuest AdultCount="{{$item['adults']}}" ChildCount="{{$item['children'] ?? 0 }}">
                    @if (($item['children'] ?? 0) > 0)
                    <hot:ChildAge>
                        @foreach ($item['child-age'] as $childAge)
                        <hot:int>{{$childAge}}</hot:int>
                        @endforeach
                    </hot:ChildAge>
                    @endif
                </hot:RoomGuest>
                @endforeach
            </hot:RoomGuests>
            <hot:ResultCount>0</hot:ResultCount>
            <hot:Filters>
                <hot:StarRating>All</hot:StarRating>
                <hot:OrderBy>PriceAsc</hot:OrderBy>
            </hot:Filters>
            <hot:ResponseTime>17</hot:ResponseTime>
        </hot:HotelSearchRequest>
    </soap:Body>
</soap:Envelope>