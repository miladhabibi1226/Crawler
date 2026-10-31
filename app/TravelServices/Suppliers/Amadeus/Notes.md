
# **IMPORTANT**
right now we are missing some recommendations by ignoring the extra available cabin products in each segment
make sure to cleanUp the reccomendations asap
# **Search Validity**
    - 15 min (i asked specificlly in an email)
    - for searchBookDaedline, if the calculated default value is less than 15 min we'll use the default value. bc PO said not to go over that time even if supplier allowed it


# **Work Flow / Seassion handling**

    - Fare_MasterPricerTravelBoardSearch ............... STATELESS

    
    - Fare_InformativePricingWithoutPNR ................ STATEFUL-START
    - FareCheckRules (or MiniRule_GetFromRec ).......... STATEFUL-INSERIES
    - Security-SingOut ................................. STATEFUL-END

    - Air_SellFromRecommendation ....................... STATEFUL-START
    - PNR_AddMultiElements ............................. STATEFUL-INSERIES
    - FOP_CreateFormOfPayment .......................... STATEFUL-INSERIES
    - Fare_PricePNRWithBookingClass .................... STATEFUL-INSERIES
    - Ticket_CreateTSTFromPricing ...................... STATEFUL-INSERIES
    - PNR_AddMultiElements ............................. STATEFUL-INSERIES
    - Security-SingOut ................................. STATEFUL-END
 

### TICKET ISSUANCE:

If the ticket is done right after PNR creation, a gap of 15-30 seconds should be left between PNR creation and ticket issuance in order to let enough time to airline to update the PNR if needed and to not receive simultaneous error:
 

    - PNR_Retrieve ..................................... STATEFUL-START
    - DocIssuance_IssueTicket​ .......................... STATEFUL-INSERIES
    - Security-SingOut ................................. STATEFUL-END

Leave 15 to 30 seconds gap and then:

    - PNR_Retrieve ..................................... STATELESS
 
This gap allows the airline to process the issuance and get the tickets ready. Then with PNR_Retrieve your application will have the ticket numbers. 

### Queuing:
 
    - Queue_placePNR ................................... STATELESS
    - PNR_Retrieve ..................................... STATELESS

# **Notes**

> After calling the Fare_PricePNRWithBookingClass function, the system keeps the recommendations stored internally
for three minutes 


# Open Return / Multi City
    - Hs Multi-city / Open Jaw (tested search)
    - No Open Return (Prem from gcc said)
    
