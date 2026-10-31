<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:ses="http://xml.amadeus.com/2010/06/Session_v3">
    <soap:Header>
        <add:MessageID xmlns:add="http://www.w3.org/2005/08/addressing">{{ $messageID }}</add:MessageID>
        <add:Action xmlns:add="http://www.w3.org/2005/08/addressing">{{ $action }}</add:Action>
        <add:To xmlns:add="http://www.w3.org/2005/08/addressing">{{ $api_url }}</add:To>
        <link:TransactionFlowLink xmlns:link="http://wsdl.amadeus.com/2010/06/ws/Link_v1" />
        <oas:Security xmlns:oas="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
            <oas:UsernameToken oas1:Id="UsernameToken-1"
                xmlns:oas1="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">
                <oas:Username>{{ $userId }}</oas:Username>
                <oas:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary"> {{ $nonce }}</oas:Nonce>
                <oas:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest"> {{ $passSHA }}</oas:Password>
                <oas1:Created>{{ $timestamp }}</oas1:Created>
            </oas:UsernameToken>
        </oas:Security>
        <AMA_SecurityHostedUser xmlns="http://xml.amadeus.com/2010/06/Security_v1">
            <UserID AgentDutyCode="{{ $agentDutyCode }}" POS_Type="{{ $POSType }}" PseudoCityCode="{{ $officeId }}" RequestorType="{{ $requestorType }}" />
        </AMA_SecurityHostedUser>
        <awsse:Session TransactionStatusCode="Start" xmlns:awsse="http://xml.amadeus.com/2010/06/Session_v3" />
    </soap:Header>
    <soap:Body>
        @include("Amadeus.$include")
    </soap:Body>
</soap:Envelope>
