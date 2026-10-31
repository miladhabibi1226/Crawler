<?php

namespace App\Console\Commands\NewSupplier;

use Illuminate\Console\Command;

class Common extends Command
{
    protected $supplierName, $supplierPath;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supplier {name : Name}';

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
        $this->supplierPath = base_path() . "/app/TravelServices/Suppliers/$this->supplierName";

        if (!file_exists($this->supplierPath . "/Logics")) {
            mkdir($this->supplierPath . "/Logics", 0777, true);
        } else {
            $response = readline("The structure for $this->supplierName already exists. do you wish to replace the existing files? (y/n)");
            if ($response != 'y' && $response != 'yes') {
                return;
            }
        }
        
        $this->makeCommonFile();

        $this->makePingFile();

        $this->info('Done');
    }

    protected function makeCommonFile()
    {
        $this->info("Creating Common.php...");

        $commonFile = file_get_contents(storage_path('NewSupplier/Common.php'));
        $commonFile = str_replace('DummyName', $this->supplierName, $commonFile);
        file_put_contents($this->supplierPath . "/Logics/Common.php", $commonFile);
    }

    protected function makePingFile()
    {
        $this->info("Creating Ping.php...");

        $PingFile = file_get_contents(storage_path('NewSupplier/Ping.php'));
        $PingFile = str_replace('DummyName', $this->supplierName, $PingFile);
        file_put_contents($this->supplierPath . "/Ping.php", $PingFile);


        $this->info("Creating Ping.blade.php...");

        $path = resource_path() . "/views/$this->supplierName";
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . "/Ping.blade.php", '');
    }
}
