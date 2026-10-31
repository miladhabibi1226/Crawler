<?php

class HotelBookTest extends TestCase
{
    public function testRequest()
    {
        $hotelBufferId = self::$data['hotelBufferId'];
        $response = $this->get("1/fake/hotel/bookAndIssue?referenceId=$hotelBufferId")
            ->seeStatusCode(200)
            ->response->getcontent();
        self::$data['hotelBookingId'] = json_decode($response, true)['reference_id'];
    }

    public function testAvailability()
    {
        $hotelBookingId = self::$data['hotelBookingId'];
        $this->get("1/beforebook/hotel/$hotelBookingId/availabilityAndPricing")
            ->seeStatusCode(200)
            ->seeJson(['status' => true,]);
    }

    public function testBookAndIssue()
    {
        $hotelBookingId = self::$data['hotelBookingId'];
        $this->get("1/bookAndIssue/$hotelBookingId")
            ->seeStatusCode(200)
            ->seeJson(['status' => true,]);
    }
}
