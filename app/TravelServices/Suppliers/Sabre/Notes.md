
# Search Validity
    - i will ask as soon i can get in contact




# Open Return / Multi City
    - Hs Multi-city / Open Jaw (tested search)
    - No Open Return (Prem from gcc said)
    

# Booking segment statuses
    - HK/SS/NN are considered successful (non-error) status codes
    - UC/NO are considered unsuccessful status codes and the API will return an error
    - PN will always be returned for aggregator bookings and is considered a successful (non-error) status code
    
    HK	CONFIRMED	The status code HK indicates holds confirmed status.
    NN	ON_REQUEST	The status code NN indicates a pending request to the vendor, this is normally a temporary status code returned when the communication to a vendor is slow. If this code persists after redisplaying a reservation it likely indicates a breakdown in communication between Sabre and thevendor.
    NO	UNCONFIRMED	The status code NO indicates the vendor denied the booking request with NO action. You must cancel the denied segment and request an alternate booking. If NO status is returned by a vendor on a previously confirmed segment, it may indicate that the Sabre PNR is out of synch with the vendor records (the records do not match). Contact the vendor to validate the PNR data.
    PN	PENDING     PN will always be returned for aggregator bookings. The confirmation number at this time is created by Sabre and not the vendor. At time of commit (End Transaction), this status should change to either HK or UC:If HK, the previous confirmation number will be overwritten with the one created by the vendor. If the segment remains in PN status at time of Commit (End Transaction), this indicates something has gone wrong with the request and you will need to follow up with the aggregator manually. Segments in PN status cannot be updated by the API user.
    SS	CONFIRMED	The status code SS indicates a successful Sabre system response. The segment sold through Sabre or a sell message was sent to the vendor.
    UC	UNCONFIRMED	The status code UC indicates the segment is not confirmed. Cancel the segment and request an alternative booking.