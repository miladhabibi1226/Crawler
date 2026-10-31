<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:ses="http://xml.amadeus.com/2010/06/Session_v3">
    <soap:Header>
        <awsse:Session TransactionStatusCode="InSeries" xmlns:awsse="http://xml.amadeus.com/2010/06/Session_v3">
            <awsse:SessionId>{{ $session['SessionId'] }}</awsse:SessionId>
            <awsse:SequenceNumber>{{ $session['SequenceNumber'] }}</awsse:SequenceNumber>
            <awsse:SecurityToken>{{ $session['SecurityToken'] }}</awsse:SecurityToken>
        </awsse:Session>
        <add:MessageID xmlns:add="http://www.w3.org/2005/08/addressing">{{ $messageID }}</add:MessageID>
        <add:Action xmlns:add="http://www.w3.org/2005/08/addressing">{{ $action }}</add:Action>
        <add:To xmlns:add="http://www.w3.org/2005/08/addressing">{{ $api_url }}</add:To>
    </soap:Header>
    <soap:Body>
        @include("Amadeus.$include")
    </soap:Body>
</soap:Envelope>
