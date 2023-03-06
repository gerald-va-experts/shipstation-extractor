<?php

namespace App\Services;

use Carbon\Carbon;

class ShipmentServices
{
    public function getShipments()
    {
        $yesterday = Carbon::now()->format('Y-m-d');

        //Get shipments
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://ssapi.shipstation.com',
            'auth' => ['c2f656f36d864c5486cb36561035a273', '10f340330eb94fe8b80a780aa1a6ee6b']
        ]);

        $response = $client->get('/shipments', [
            'query' => [
                "shipDateStart" => $yesterday . "T00:00:00.000Z",
                "shipDateEnd" => $yesterday . "T00:00:00.000Z",
                "page" => 1,
            ],
            'allow_redirects' => true
        ]);
        //return page 1 shipmets;
        $shipments = $response->getBody()->getContents();

        $data = json_decode($shipments);

        // Set the file path
        $filePath = public_path('exports\shipments-' . time() . '.csv');

        // Open the file for writing
        $file = fopen($filePath, 'w');

        // Add the header row
        fputcsv($file, ['Order Number', 'Shipdate', 'Carrier', 'Tracking Number']);

        // Add the data rows for page 1
        foreach ($data->shipments as $shipment) {
            $orderNumber = $shipment->orderNumber;
            $shipDate = $shipment->shipDate;
            $carrierCode = $shipment->carrierCode;
            $trackingNumber = $shipment->trackingNumber;
            fputcsv($file, [$orderNumber, $shipDate, $carrierCode, $trackingNumber]);
        }

        // Get all pages
        for ($page = 2; $page <= $data->pages; $page++) {
            echo $page;
            $nextPage = $client->get('/shipments', [
                'query' => [
                    "shipDateStart" => $yesterday . "T00:00:00.000Z",
                    "shipDateEnd" => $yesterday . "T00:00:00.000Z",
                    "page" => $page,
                ],
                'allow_redirects' => true
            ]);
            $shipmentsNext = $nextPage->getBody()->getContents();

            $dataNext = json_decode($shipmentsNext);
            // // Add the data rows for so on page
            foreach ($dataNext->shipments as $shipmentNextPage) {
                $orderNumber = $shipmentNextPage->orderNumber;
                $shipDate = $shipmentNextPage->shipDate;
                $carrierCode = $shipmentNextPage->carrierCode;
                $trackingNumber = $shipmentNextPage->trackingNumber;
                fputcsv($file, [$orderNumber, $shipDate, $carrierCode, $trackingNumber]);
            }
        }

        // Close the file
        fclose($file);

        // Return a response indicating success or failure
        if (file_exists($filePath)) {
            return response()->json(['message' => 'CSV file exported and saved successfully']);
        } else {
            return response()->json(['message' => 'Error occurred while exporting CSV file'], 500);
        }
    }
}
