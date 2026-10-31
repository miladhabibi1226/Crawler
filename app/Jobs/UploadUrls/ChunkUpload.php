<?php

namespace App\Jobs\UploadUrls;

use App\Jobs\Job;

class ChunkUpload extends Job
{
    protected $urls, $hotelProfile;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($urls, $hotelProfile)
    {
        $this->urls = $urls;
        $this->hotelProfile = $hotelProfile;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function operate()
    {
        // lugInfo('start chunked upload', ['count' => count($this->urls), 'urls' => $this->urls]);

        $response = ChunkUploadUrls($this->urls);
        foreach ($response as $key => $jsonFile) {
            if (!$jsonFile) {
                continue;
            } else {
                $downloadedUrls[$key] = $jsonFile['downloadUrl'] . '/' . $jsonFile['fileName'];
            }
        }
        $allUrls = $this->hotelProfile['images'];
        $updatedUrls = array_replace($allUrls, ($downloadedUrls ?? []));
        // lugInfo('Upload response', ['response' => $response, 'downloadedUrls' => $downloadedUrls ?? [], 'updatedImageList' => $updatedUrls]);
        $this->hotelProfile['images'] = $updatedUrls;
        $this->hotelProfile->update();
    }
}
