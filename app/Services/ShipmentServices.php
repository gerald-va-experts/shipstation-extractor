<?php

namespace App\Services;

use Carbon\Carbon;

use function PHPUnit\Framework\isEmpty;

class ShipmentServices
{
    /**Author: Gerald(Jah) */
    const TAG = "Bad Address";
    const BASE_URI = "https://ssapi.shipstation.com";
    const AUTH_USER = 'c2f656f36d864c5486cb36561035a273';
    const AUTH_PASSWORD = '10f340330eb94fe8b80a780aa1a6ee6b';

    public function getShipments()
    {
        $yesterday = Carbon::now()->format('Y-m-d');

        //Get shipments
        $client = new \GuzzleHttp\Client([
            'base_uri' => self::BASE_URI,
            'auth' => [self::AUTH_USER, self::AUTH_PASSWORD]
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
        $filePath = public_path('exports\shipments\shipments-' . $yesterday . '.csv');

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

        $this->getAllOrders();

        // Return a response indicating success or failure
        if (file_exists($filePath)) {
            return response()->json(['message' => 'CSV file exported and saved successfully']);
        } else {
            return response()->json(['message' => 'Error occurred while exporting CSV file'], 500);
        }
    }

    public function generateTag($tagId)
    {

        switch ($tagId) {
            case "15291":
                return "Backorder";
            case "3480":
                return "Bad Address";
            case "21743":
                return "DHL GlobalMail Shipment";
            case "7351":
                return "EU Address";
            case "15282":
                return "EU SKU";
            case "21742":
                return "Evri Shipment";
            case "4123":
                return "FORCE SHIP";
            case "18911":
                return "Missing Phone Number";
            case "4254":
                return "SPECIAL REPORTING v1";
            case "4921":
                return "SPLIT ORDER";
            case "3246":
                return "UK Address";
            case "3245":
                return "UK IGNORE";
            case "15283":
                return "UK SHIP";
            case "9312":
                return "Urgent";
            default:
                return "";
        }
    }

    public function mapOrders($data, $file)
    {
        // Add the data rows for page 1 (awaiting)
        foreach ($data->orders as $shipment) {
            try {
                $tags = "";
                $itemName = "";
                $itemSKU = "";
                $quantity = "";

                //map and generate tag
                if ($shipment->tagIds) {
                    foreach ($shipment->tagIds as $tag) {
                        $tags = $this->generateTag($tag) . ":" . $tags;
                    }
                }

                $itemsCount = count($shipment->items);
                if ($itemsCount > 0) {
                    $itemName = $shipment->items[0]->name;
                    $itemSKU = $shipment->items[0]->sku;
                    $quantity = $shipment->items[0]->quantity;
                }

                $orderId = $shipment->orderId;
                $orderNumber = $shipment->orderNumber;
                $orderDate = $shipment->orderDate;
                $customerName = $shipment->shipTo->name;
                $status = $shipment->orderStatus;
                $requestedShippingService = $shipment->requestedShippingService;
                $street1 = $shipment->shipTo->street1;
                $street2 = $shipment->shipTo->street2;
                $street3 = $shipment->shipTo->street3;
                $city = $shipment->shipTo->city;
                $state = $shipment->shipTo->state;
                $postal = $shipment->shipTo->postalCode;
                $countryCode = $shipment->shipTo->country;
                fputcsv($file, [$orderId, $orderNumber, $orderDate, $customerName, $itemName, $itemSKU, $quantity, $status, $requestedShippingService, $street1, $street2, $street3, $city, $state, $postal, $countryCode, $tags]);
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    public function mapAllPages($data, $file, $orderStatus, $dateStart, $dateEnd)
    {
        //Get shipments
        $client = new \GuzzleHttp\Client([
            'base_uri' => self::BASE_URI,
            'auth' => [self::AUTH_USER, self::AUTH_PASSWORD]
        ]);

        // Get all pages
        for ($page = 2; $page <= $data->pages; $page++) {
            $nextPage = $client->get('/orders', [
                'query' => [
                    "orderDateStart" => $dateStart . "T00:00:00.000Z",
                    "orderDateEnd" => $dateEnd . "T00:00:00.000Z",
                    "orderStatus" => $orderStatus,
                    "page" => $page,
                ],
                'allow_redirects' => true
            ]);
            $shipmentsNext = $nextPage->getBody()->getContents();

            $dataNext = json_decode($shipmentsNext);

            // // Add the data rows for so on page
            foreach ($dataNext->orders as $shipmentNextPage) {
                try {
                    $tags = "";
                    $itemName = "";
                    $itemSKU = "";
                    $quantity = "";

                    //map and generate tag
                    if ($shipmentNextPage->tagIds) {
                        foreach ($shipmentNextPage->tagIds as $tag) {
                            $tags = $this->generateTag($tag) . ":" . $tags;
                        }
                    }

                    $itemsCount = count($shipmentNextPage->items);
                    if ($itemsCount > 0) {
                        $itemName = $shipmentNextPage->items[0]->name;
                        $itemSKU = $shipmentNextPage->items[0]->sku;
                        $quantity = $shipmentNextPage->items[0]->quantity;
                    }

                    $orderId = $shipmentNextPage->orderId;
                    $orderNumber = $shipmentNextPage->orderNumber;
                    $orderDate = $shipmentNextPage->orderDate;
                    $customerName = $shipmentNextPage->shipTo->name;
                    $status = $shipmentNextPage->orderStatus;
                    $requestedShippingService = $shipmentNextPage->requestedShippingService;
                    $street1 = $shipmentNextPage->shipTo->street1;
                    $street2 = $shipmentNextPage->shipTo->street2;
                    $street3 = $shipmentNextPage->shipTo->street3;
                    $city = $shipmentNextPage->shipTo->city;
                    $state = $shipmentNextPage->shipTo->state;
                    $postal = $shipmentNextPage->shipTo->postalCode;
                    $countryCode = $shipmentNextPage->shipTo->country;
                    fputcsv($file, [$orderId, $orderNumber, $orderDate, $customerName, $itemName, $itemSKU, $quantity, $status, $requestedShippingService, $street1, $street2, $street3, $city, $state, $postal, $countryCode, $tags]);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    public function getShipmentsPerPage($dateStart, $dateEnd, $orderStatus)
    {
        //Get shipments
        $client = new \GuzzleHttp\Client([
            'base_uri' => self::BASE_URI,
            'auth' => [self::AUTH_USER, self::AUTH_PASSWORD]
        ]);

        //awaiting
        $response = $client->get('/orders', [
            'query' => [
                "orderDateStart" => $dateStart . "T00:00:00.000Z",
                "orderDateEnd" => $dateEnd . "T00:00:00.000Z",
                "orderStatus" => $orderStatus,
                "page" => 1,
            ],
            'allow_redirects' => true
        ]);

        //return page 1 shipments;
        $result = $response->getBody()->getContents();

        $shipments = json_decode($result);

        return $shipments;
    }

    public function getAllOrders()
    {
        /**Author: Gerald (Jah)*/

        $pastDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');
        
        //fetch data
        $awaiting_shipment = $this->getShipmentsPerPage($pastDate, $today, "awaiting_shipment");
        $shipped = $this->getShipmentsPerPage($pastDate, $today, "shipped");

        /**
         * --------start-------------
         * Set the file path
         * Open the file for writing
         * Add the header row
         */
        $filePath = public_path('exports\all-orders\allOrders-' . $today . '.csv');
        $file = fopen($filePath, 'w');
        fputcsv($file, ['Order ID', 'Order Number', 'Order Date', 'Name of the customer', 'Item Name', 'Item SKU', 'Quantity', 'Status', 'Requested Shipping Service', 'Street1', 'Street2', 'Street3', 'City', 'State', 'Postal', 'Country Code', 'Tags']);
        //-------end----------

        // /**
        //  * -------start-------------
        //  * map result for shipped
        //  * map all pages for shipped
        //  */
        $this->mapOrders($shipped, $file);
        $this->mapAllPages($shipped, $file, "shipped", $pastDate, $today);
        // //-------end----------

        /**
         * -------start-------------
         * map result for awaiting_shipment
         * map all pages for awaiting_shipment
         */
        $this->mapOrders($awaiting_shipment, $file);
        $this->mapAllPages($awaiting_shipment, $file, "awaiting_shipment", $pastDate, $today);
        //-------end----------

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
            if ($item->tagIds != null) {

                //filter bad address
                if (count($item->tagIds) != 0 && in_array(3480, $item->tagIds)) {
                    $filtered[] =  $item;
                }

                //filter back orders
                if (count($item->tagIds) != 0 && in_array(15291, $item->tagIds)) {
                    $filtered[] =  $item;
                }
            }
        }

        return $filtered;
    }

    public function getOrders()
    {
        $yesterday = Carbon::now()->format('Y-m-d');

        //Get orders
        $client = new \GuzzleHttp\Client([
            'base_uri' => self::BASE_URI,
            'auth' => [self::AUTH_USER, self::AUTH_PASSWORD]
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
        $filePath = public_path('exports\held-orders\held-orders-' . $yesterday . '.csv');
        // Open the file for writing
        $file = fopen($filePath, 'w');

        // Add the header row
        fputcsv($file, ['Order Number', 'Order Date', 'Item SKU', 'Item Name', 'Recipient', 'Quantity', 'Order Total', 'Tags', 'Country', 'Street 1', 'Street 2', 'Street 3', 'City', 'Postal', 'State', 'Address Verified']);

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
            $street1 = $orders->shipTo->street1;
            $street2 = $orders->shipTo->street2;
            $street3 = $orders->shipTo->street3;
            $city = $orders->shipTo->city;
            $postal = $orders->shipTo->postalCode;
            $state = $orders->shipTo->state;
            $addressVerified = $orders->shipTo->addressVerified;
            fputcsv($file, [$orderNumber, $orderDate, $itemSKU, $itemName, $recipient, $quantity, $total, $tag, $country, $street1, $street2, $street3, $city, $postal, $state, $addressVerified]);
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
                $street1 = $orders->shipTo->street1;
                $street2 = $orders->shipTo->street2;
                $street3 = $orders->shipTo->street3;
                $city = $orders->shipTo->city;
                $postal = $orders->shipTo->postalCode;
                $state = $orders->shipTo->state;
                $addressVerified = $orders->shipTo->addressVerified;
                fputcsv($file, [$orderNumber, $orderDate, $itemSKU, $itemName, $recipient, $quantity, $total, $tag, $country, $street1, $street2, $street3, $city, $postal, $state, $addressVerified]);
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
