Source Organization Should be your organization code so that booking reflects to your account

Always use the culture as “en-GB” in Contact request.

Use the organization ID in your passenger contact request ("sourceOrganization": “Agency
Organization ID”) & payment requests ("ACCTNO": "Agency Organization ID ")

For the Jazeera Itinerary add the tag “distribution = ‘Email” in passengercontact method.

Itinerary sending option can be controlled from booking commit request by setting
“notifyContacts”- true/false.“notifyContacts” feature will be enabled in the production environment, upon request from the
agency.

Use CompressionType": "CompressByProductClass" in availability request to get the fare bundles
(Economy Light, Value & Extra)

You'll always receive the fares in availability response in origin station default currency
regardless of which currency you pass in the request. All the remaining API call requests
(BookingQuote, TripSell, BookingCommit, etc) need to use the currency specified in the availability API response. If you want to display fares for customer in any other currency, use
currency conversion API.