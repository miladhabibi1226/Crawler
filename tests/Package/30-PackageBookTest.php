<?php

class PackageBookTest extends TestCase
{
    public function testRequest()
    {
        $packageBufferId = self::$data['packageBufferId'];
        $response = $this->get("1/fake/package/book?referenceId=$packageBufferId&adults=1&children=0&infants=0")
            ->seeStatusCode(200)
            ->response->getcontent();
        self::$data['packageBookingId'] = json_decode($response, true)['reference_id'];
    }

    public function testLock()
    {
        $packageBookingId = self::$data['packageBookingId'];
        $this->get("1/lock/$packageBookingId")
            ->seeStatusCode(200)
            ->seeJson(['status' => true,]);
    }

    public function testAvailability()
    {
        $packageBookingId = self::$data['packageBookingId'];
        $response = $this->get("1/beforebook/package/$packageBookingId/checkAvailability")
            ->seeStatusCode(200)
            ->seeJson(['status' => true,])
            ->response->getcontent();
    }

    public function testBookAndIssue()
    {
        $packageBookingId = self::$data['packageBookingId'];
        $response = $this->get("1/bookAndIssue/$packageBookingId?sync=1")
            ->seeStatusCode(200)
            ->seeJson(['status' => true,])
            ->response->getcontent();
    }
}
