
# Search Validity
    - 20 min

# session lifeTime
    - from supplier support : The session ID will be valid until the password reset done from your end post password reset new session ID will be generated which should be used for further API flow.

# notes
    - they said that we can't do target search bc they thought it will increase the pressure and lookToBook, they couldn't understand that we are caching to reduce the pressure and lookToBook
    - rom supplier support : Once the search API response is received you need to pass the faresourcecode in the Revalidation Request and in the revalidation response you will get Tag as "HoldAllowed": "False", Which means its a Webfare and in the Book API Ticket issuance will be completed, However if the Tag is "HoldAllowed": "True", Which means its a Private and Public fare Type were you have option to Hold the booking in Book API and as per the Ticket time limit you can go ahead and perform Order Ticket API.

# supplier support
    - to contact supplier use jia and raise a MYSD case. https://mystiflyjiradesk.atlassian.net/servicedesk/customer/portals (we have an acc it@luxota.net)

