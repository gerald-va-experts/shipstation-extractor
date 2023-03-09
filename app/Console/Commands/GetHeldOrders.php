<?php

namespace App\Console\Commands;

use App\Services\ShipmentServices;
use Illuminate\Console\Command;

class GetHeldOrders extends Command
{
    private $shipmentServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geHeldOrders:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get All Held Order';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ShipmentServices $shipmentServices)
    {
        parent::__construct();
        $this->shipmentServices = $shipmentServices;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->shipmentServices->getOrders();
    }
}
