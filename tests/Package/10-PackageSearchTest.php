<?php

class PackageSearchTest extends TestCase
{
    public function testRequest()
    {
        $month = "2021-04";
        $tehran = 8878;
        $response = $this->json('get', "1/fake/package/search?month=$month&city=$tehran")
            ->seeJson(['status' => true,])
            ->seeJsonStructure(["sessionId"])
            ->response->getContent();
        self::$data['searchQueryId'] = json_decode($response)->sessionId;
    }

    public function testSearch()
    {
        $searchQueryId = self::$data['searchQueryId'];
        $this->get("1/search/$searchQueryId")
            ->seeJson(['status' => true,]);
    }

    public function testResult()
    {
        $searchQueryId = self::$data['searchQueryId'];
        for ($i = 0; $i < 7; $i++) {
            $response = $this->get("1/fake/progress?sessionId=$searchQueryId")
                ->seeStatusCode(200)
                ->response->getContent();
            $progress = ($response == '{"percent":100}');
            if ($progress) {
                $result = $this->get("1/fake/package/result?sessionId=$searchQueryId")
                    ->seeStatusCode(200)
                    ->response->getcontent();
                $results =json_decode($result, true);
                $this->assertNotEmpty($results); ///? "[]" is not empty
                self::$data['packageBufferId'] = $results[0]['reference_id'];
                break;
            }
            sleep(5);
        }
    }
}
