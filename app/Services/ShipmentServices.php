<?php

namespace App\Services;

use Carbon\Carbon;

use function PHPUnit\Framework\isEmpty;

class ShipmentServices
{
    const TAG = "Bad Address";
    public function getShipments()
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');

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
        //return page 1 shipments;
        $shipments = $response->getBody()->getContents();

        $data = json_decode($shipments);

        // Set the file path
        $filePath = public_path('exports\shipments\shipments-' . $yesterday . '-' . time() . '.csv');

        // Open the file for writing
        $file = fopen($filePath, 'w');

        // Add the header row
        fputcsv($file, ['Order Number', 'Shipdate', 'Carrier', 'Tracking Number']);

        // Add the data rows for page 1
        foreach ($data->shipments as $shipment) {
            $carrierCode = $shipment->carrierCode;

            if ($shipment->carrierCode == "deutsche_post_cross_border") {
                $carrierCode = "Deutsche Post Cross-Border";
            } else if ($shipment->carrierCode == "royal_mail") {

                $carrierCode = "Royal Mail";
            } else if ($shipment->carrierCode == "hermescorp") {

                $carrierCode = "EVRi UK";
            }

            $orderNumber = $shipment->orderNumber;
            $shipDate = $shipment->shipDate;
            $trackingNumber = $shipment->trackingNumber;
            fputcsv($file, [$orderNumber, $shipDate, $carrierCode, $trackingNumber]);
        }

        // Get all pages
        for ($page = 2; $page <= $data->pages; $page++) {
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
                $carrierCode = $shipmentNextPage->carrierCode;

                if ($shipmentNextPage->carrierCode == "deutsche_post_cross_border") {
                    $carrierCode = "Deutsche Post Cross-Border";
                } else if ($shipmentNextPage->carrierCode == "royal_mail") {

                    $carrierCode = "Royal Mail";
                } else if ($shipmentNextPage->carrierCode == "hermescorp") {

                    $carrierCode = "EVRi UK";
                }

                $orderNumber = $shipmentNextPage->orderNumber;
                $shipDate = $shipmentNextPage->shipDate;
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


    //for orders
    function filterOrdersByTag($orders)
    {
        $filtered = array();

        foreach ($orders as $item) {
            if (count($item->tagIds) != 0 && in_array(3480, $item->tagIds)) {
                $filtered[] =  $item;
            }
        }

        return $filtered;
    }

    public function getOrders()
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        //Get orders
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://ssapi.shipstation.com',
            'auth' => ['c2f656f36d864c5486cb36561035a273', '10f340330eb94fe8b80a780aa1a6ee6b']
        ]);

        $response = $client->get('/orders', [
            'query' => [
                "orderStatus" => "awaiting_shipment",
                "page" => 1,
            ],
            'allow_redirects' => true
        ]);

        //return page 1 orders;
        $heldOrders = $response->getBody()->getContents();

        $data = json_decode($heldOrders);
        $filteredData = $this->filterOrdersByTag($data->orders);
        //Set the file path
        $filePath = public_path('exports\held-orders\held-orders-' . $yesterday . '-' . time() . '.csv');
        // Open the file for writing
        $file = fopen($filePath, 'w');

        // Add the header row
        fputcsv($file, ['Order Number', 'Order Date', 'Item SKU', 'Item Name', 'Recipient', 'Quantity', 'Order Total', 'Tags', 'Country', 'Ship Date']);

        //Add the data rows for page 1
        foreach ($filteredData as $orders) {
            $date_time = $orders->orderDate;
            $date = date("Y-m-d", strtotime($date_time));
            $orderNumber = $orders->orderNumber;
            $orderDate = $date;
            $itemSKU = count($orders->items) != 0 ? $orders->items['0']->sku : "";
            $itemName = count($orders->items) != 0 ? $orders->items['0']->name : "";
            $recipient = $orders->shipTo->name;
            $quantity = count($orders->items) != 0 ? $orders->items['0']->quantity : "";
            $total = $orders->orderTotal;
            $tag = self::TAG;
            $country = $orders->shipTo->country;
            $shipDate = $orders->shipDate;
            fputcsv($file, [$orderNumber, $orderDate, $itemSKU, $itemName, $recipient, $quantity, $total, $tag, $country, $shipDate]);
        }

        // Get all pages
        for ($page = 2; $page <= $data->pages; $page++) {
            $nextPage = $client->get('/orders', [
                'query' => [
                    "orderStatus" => "awaiting_shipment",
                    "page" => $page,
                ],
                'allow_redirects' => true
            ]);
            $ordersNext = $nextPage->getBody()->getContents();

            $dataNext = json_decode($ordersNext);

            $filteredDataNext = $this->filterOrdersByTag($dataNext->orders);
            // // Add the data rows for so on page
            foreach ($filteredDataNext as $orders) {
                $date_time = $orders->orderDate;
                $date = date("Y-m-d", strtotime($date_time));
                $orderNumber = $orders->orderNumber;
                $orderDate = $date;
                $itemSKU = count($orders->items) != 0 ? $orders->items['0']->sku : "";
                $itemName = count($orders->items) != 0 ? $orders->items['0']->name : "";
                $recipient = $orders->shipTo->name;
                $quantity = count($orders->items) != 0 ? $orders->items['0']->quantity : "";
                $total = $orders->orderTotal;
                $tag = self::TAG;
                $country = $orders->shipTo->country;
                $shipDate = $orders->shipDate;
                fputcsv($file, [$orderNumber, $orderDate, $itemSKU, $itemName, $recipient, $quantity, $total, $tag, $country, $shipDate]);
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
