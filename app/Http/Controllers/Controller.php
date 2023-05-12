<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Exception;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function getStatusByTrackingNumber($trackingNumber, $file, $token)
    {
        //dd($trackingNumber);
        // Instantiate a new Guzzle client
        $client = new Client();
        try {
            // Set the API endpoint URL
            $url = 'https://apis.fedex.com/track/v1/trackingnumbers';

            // Set the API request headers
            $headers = [
                'Authorization' => 'Bearer ' . $token,
                'X-locale' => 'en_US',
                'Content-Type' => 'application/json',
            ];

            // Set the API request body
            $body = [
                "trackingInfo" => [
                    [
                        "trackingNumberInfo" => [
                            "trackingNumber" => '395699113047'
                        ]
                    ]
                ],
                "includeDetailedScans" => true
            ]; // Replace with your JSON payload

            // Send the API request using Guzzle
            $response = $client->post($url, [
                'headers' => $headers,
                'body' => json_encode($body),
            ]);
            // Get the API response body
            $responseBody = json_decode($response->getBody()->getContents());

            if ($responseBody) {
                if (property_exists($responseBody->output->completeTrackResults[0]->trackResults[0], 'error')) {
                } else {
                    $statusByLocale = $responseBody->output->completeTrackResults[0]->trackResults[0]->latestStatusDetail->statusByLocale;

                    if ($statusByLocale == 'Shipment exception') {

                        $status = $responseBody->output->completeTrackResults[0]->trackResults[0]->latestStatusDetail->delayDetail->status;
                        $reasonDescription = $responseBody->output->completeTrackResults[0]->trackResults[0]->ancillaryDetails[0]->reasonDescription;
                        fputcsv($file, [$trackingNumber, $status, $statusByLocale, $reasonDescription]);
                    }
                    if ($statusByLocale == 'In transit') {

                        $timeArrays = $responseBody->output->completeTrackResults[0]->trackResults[0]->dateAndTimes;
                        $schedDelivery = "";
                        foreach ($timeArrays as $types) {
                            if ($types->type == "ESTIMATED_DELIVERY") {
                                $schedDelivery = $types->dateTime;
                            }
                        }
                        fputcsv($file, [$trackingNumber, $statusByLocale, $statusByLocale, "", $schedDelivery]);
                    }
                }
            }
        } catch (Exception $ex) {
        }
    }

    public function getToken()
    {
        $client = new Client();

        $response = $client->request('POST', 'https://apis.fedex.com/oauth/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => 'l7b10e4b292d45471b8d39bd68d20dfc7a',
                'client_secret' => '75eff2cac5b94c48ab4ba28042116e11',
            ]
        ]);

        $body = $response->getBody();
        $data = json_decode($body, true);
        return $data['access_token'];
    }

    public function import()
    {
        $token = $this->getToken();
        $today = Carbon::now()->format('Y-m-d');

        $a = explode("\n", file_get_contents(storage_path('\imports\tracking-numbers.csv')));
        //validate phone numbers

        $filePath = public_path('exports\fedex\fedex-' . $today . '.csv');
        // Open the file for writing
        $file = fopen($filePath, 'w');

        fputcsv($file, ['Tracking Number', 'Status', 'Status by locale', 'Reason Description', 'Scheduled Delivery Date']);
        // Add the header row
        for ($i = 0; $i <= count($a) - 1; $i += 1) {
            $value = $a[$i];
            $this->getStatusByTrackingNumber($value, $file, $token);
        }

        fclose($file);
    }
}
