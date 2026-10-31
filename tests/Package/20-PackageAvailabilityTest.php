<?php

class PackageAvailabilityTest extends TestCase
{
    public function testAvailability()
    {
        $packageBufferId = self::$data['packageBufferId'];
        $this->get("1/aftersearch/package/$packageBufferId/availabilities")
            ->seeJson(['status' => true,]);
    }
}
