<?php

namespace App\Console\Commands\NewSupplier;

use Illuminate\Console\Command;

class TravelService extends Command
{
    protected $supplierName, $travelService, $supplierPath;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'travel-service {name : Name} {travelService : Travel Service}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create The Folder/File structure for a new supplier.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->supplierName = ucfirst($this->argument('name'));
        $this->travelService = ucfirst($this->argument('travelService'));
        $this->supplierPath = base_path() . "/app/TravelServices/Suppliers/$this->supplierName";

        $availableTravelServices = ['Flight', 'Hotel', 'Package', 'Visa', 'Event', 'Tour'];
        if (!in_array($this->travelService, $availableTravelServices)) {
            return $this->error('Invalid TravelService.');
        }

        if (!file_exists($this->supplierPath . "/Logics/Common.php")) {
            return $this->error('Common files dont exist.');
        }

        if (!file_exists($this->supplierPath . "/$this->travelService.php")) {
            mkdir($this->supplierPath . "/Logics/$this->travelService", 0777, true);
        } else {
            $response = readline("The structure for $this->supplierName $this->travelService already exists. do you wish to replace the existing files? (y/n)");
            if ($response != 'y' && $response != 'yes') {
                return;
            }
        }

        $this->makeTravelServicePHP();

        $this->makeSearchFile();

        $this->makePassengersValidationFile();

        $this->makeBookFile();

        $this->makeIssueFile();

        $this->makeBookAndIssueFile();

        $this->makeResourceFiles();

        $this->makeSampleFiles();

        $this->makeServiceFiles();

        $this->info('Done');
    }

    protected function makeTravelServicePHP()
    {
        $this->info("Creating $this->travelService.php...");

        $travelServiceFile = file_get_contents(storage_path('NewSupplier/TravelService.php'));
        $travelServiceFile = str_replace('DummyName', $this->supplierName, $travelServiceFile);
        $travelServiceFile = str_replace('DummyTravelService', $this->travelService, $travelServiceFile);
        file_put_contents($this->supplierPath . "/$this->travelService.php", $travelServiceFile);
    }

    protected function makeSearchFile()
    {
        $this->info("Creating Search.php...");

        $SearchFile = file_get_contents(storage_path("NewSupplier/Search/{$this->travelService}Search.php"));
        $SearchFile = str_replace('DummyName', $this->supplierName, $SearchFile);
        $SearchFile = str_replace('DummyTravelService', $this->travelService, $SearchFile);
        file_put_contents($this->supplierPath . "/Logics/$this->travelService/Search.php", $SearchFile);
    }

    protected function makePassengersValidationFile()
    {
        $this->info("Creating PassengersValidation.php...");

        $PassengersValidationFile = file_get_contents(storage_path("NewSupplier/Validation/{$this->travelService}PassengersValidation.php"));
        $PassengersValidationFile = str_replace('DummyName', $this->supplierName, $PassengersValidationFile);
        $PassengersValidationFile = str_replace('DummyTravelService', $this->travelService, $PassengersValidationFile);
        file_put_contents($this->supplierPath . "/Logics/$this->travelService/PassengersValidation.php", $PassengersValidationFile);
    }

    protected function makeBookFile()
    {
        $this->info("Creating Book.php...");
        if ($this->travelService == 'Flight') {
            $BookFile = file_get_contents(storage_path('NewSupplier/FlightBook.php'));
        } else {
            $BookFile = file_get_contents(storage_path('NewSupplier/Book.php'));
        }
        $BookFile = str_replace('DummyName', $this->supplierName, $BookFile);
        $BookFile = str_replace('DummyTravelService', $this->travelService, $BookFile);
        file_put_contents($this->supplierPath . "/Logics/$this->travelService/Book.php", $BookFile);
    }

    protected function makeIssueFile()
    {
        $this->info("Creating Issue.php...");
        if ($this->travelService == 'Flight') {
            $IssueFile = file_get_contents(storage_path('NewSupplier/FlightIssue.php'));
        } else {
            $IssueFile = file_get_contents(storage_path('NewSupplier/Issue.php'));
        }
        $IssueFile = str_replace('DummyName', $this->supplierName, $IssueFile);
        $IssueFile = str_replace('DummyTravelService', $this->travelService, $IssueFile);
        file_put_contents($this->supplierPath . "/Logics/$this->travelService/Issue.php", $IssueFile);
    }

    protected function makeBookAndIssueFile()
    {
        $this->info("Creating BookAndIssue.php...");

        $BookAndIssueFile = file_get_contents(storage_path('NewSupplier/BookAndIssue.php'));
        $BookAndIssueFile = str_replace('DummyName', $this->supplierName, $BookAndIssueFile);
        $BookAndIssueFile = str_replace('DummyTravelService', $this->travelService, $BookAndIssueFile);
        file_put_contents($this->supplierPath . "/Logics/$this->travelService/BookAndIssue.php", $BookAndIssueFile);
    }

    protected function makeResourceFiles()
    {
        $this->info("Creating recource blade files...");

        $path = resource_path() . "/views/$this->supplierName";

        file_put_contents($path . "/{$this->travelService}Search.blade.php", '');
        file_put_contents($path . "/{$this->travelService}Book.blade.php", '');
        file_put_contents($path . "/{$this->travelService}Issue.blade.php", '');
        file_put_contents($path . "/{$this->travelService}BookAndIssue.blade.php", '');
    }

    protected function makeSampleFiles()
    {
        $this->info("Creating sample files...");

        $path = storage_path() . "/Samples/$this->supplierName";

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($path . "/{$this->travelService}Search.json", '');
        file_put_contents($path . "/{$this->travelService}Book.json", '');
        file_put_contents($path . "/{$this->travelService}Issue.json", '');
        file_put_contents($path . "/{$this->travelService}BookAndIssue.json", '');
    }

    protected function makeServiceFiles()
    {
        if ($this->travelService == 'flight') {
            $this->info("Creating CustomSearch.php...");
            $CustomSearchFile = file_get_contents(storage_path('NewSupplier/CustomSearch.php'));
            $CustomSearchFile = str_replace('DummyName', $this->supplierName, $CustomSearchFile);
            $CustomSearchFile = str_replace('DummyTravelService', $this->travelService, $CustomSearchFile);
            file_put_contents($this->supplierPath . "/Logics/$this->travelService/CustomSearch.php", $CustomSearchFile);

            $this->info("Creating TargetSearch.php...");
            $TargetSearchFile = file_get_contents(storage_path('NewSupplier/TargetSearch.php'));
            $TargetSearchFile = str_replace('DummyName', $this->supplierName, $TargetSearchFile);
            $TargetSearchFile = str_replace('DummyTravelService', $this->travelService, $TargetSearchFile);
            file_put_contents($this->supplierPath . "/Logics/$this->travelService/TargetSearch.php", $TargetSearchFile);

            $this->info("Creating FareRulesSearch.php...");
            $FareRulesFile = file_get_contents(storage_path('NewSupplier/FareRules.php'));
            $FareRulesFile = str_replace('DummyName', $this->supplierName, $FareRulesFile);
            file_put_contents($this->supplierPath . "/Logics/$this->travelService/FareRules.php", $FareRulesFile);
        }
    }
}
