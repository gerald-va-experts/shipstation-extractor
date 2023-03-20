<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use GuzzleHttp\Client;
use Carbon\Carbon;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function index()
    {

        // Instantiate a new Guzzle client
        $client = new Client();

        // Set the API endpoint URL
        $url = 'https://apis-sandbox.fedex.com/track/v1/trackingnumbers';

        // Set the API request headers
        $headers = [
            'Authorization' => 'Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzY29wZSI6WyJDWFMiXSwiUGF5bG9hZCI6eyJjbGllbnRJZGVudGl0eSI6eyJjbGllbnRLZXkiOiJsNzJiZjMwOGY3N2IxNjQ0YzJhNWY5NjYzOGEwMDdlZmU1In0sImF1dGhlbnRpY2F0aW9uUmVhbG0iOiJDTUFDIiwiYWRkaXRpb25hbElkZW50aXR5Ijp7InRpbWVTdGFtcCI6IjE0LU1hci0yMDIzIDEwOjQzOjA1IEVTVCIsImdyYW50X3R5cGUiOiJjbGllbnRfY3JlZGVudGlhbHMiLCJhcGltb2RlIjoiU2FuZGJveCIsImN4c0lzcyI6Imh0dHBzOi8vY3hzYXV0aHNlcnZlci1zdGFnaW5nLmFwcC5wYWFzLmZlZGV4LmNvbS90b2tlbi9vYXV0aDIifSwicGVyc29uYVR5cGUiOiJEaXJlY3RJbnRlZ3JhdG9yX0IyQiJ9LCJleHAiOjE2Nzg4MTIxODUsImp0aSI6IjQ3NGE3NDIxLTU4NzQtNDA4OC05ZjMyLTY0ZTg4Yzg2NzljZiJ9.eX_Ir_PBC_NZapkccU50fQL7pUp35nRdcG1skvrtdZx0-m3RSNdNMYyRh9VgkZsBLwyj83qlEjJks_PmCP9FnSTyQ8Yd-z4Z7-Xc33b3j2e6jmycMHIVrwbxvOmSRWOvoInBPjS_VAoJEaRRXJcgQvTAOAuOcwVKP0-f_KRjj5asYTkS1oF3uVEnrYEk3htoq-2DgjWi62IRPL4qRLUJbL4ow6vJfXQapbciAp8Y9iumIc6TflK5KItE3P8acRuBPV-meLm3Wr6SvB60wcQQPYJFE14_KjHcWmx1HV4r0gE0MTnOglQHX6d221zCdRBWu126FXUffmh1M-O7e0K5neYAMLu0xXyxLcZCFPgcw7Wky2unVpLJEpMp2I7XhyrbCWZolXWuinS0KPAcBmxcPDbZobBO0xYwG3F-_tB7WHpXf-TuKm0DlL0jdcmR415IX2hRkuQVeMvkxGgWI9uD5WnKFGwmiHSeUwYCDy8MeekimdXpACGB0iweQp-4iQmj9eqFJkSF5sJ1zDcqknyzW0ofyFPCGtZtUGDKrVJ6l_y1OYqGVmrW3_y_yb0rdRB0yEsw8Rg4LgzIDtgGDsYP9l5uS1iDQPsas3kXQb9tQJl8nQFpvUtSNZdbVGI6mSqel70wZrmml-lFVMFLyRcNWTXJnX7QOpMI8Z9a21bbdp8',
            'X-locale' => 'en_US',
            'Content-Type' => 'application/json',
        ];

        // Set the API request body
        $body = [
            "trackingInfo" => [
                [
                    "trackingNumberInfo" => [
                        "trackingNumber" => "449044304137821"
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
        $responseBody = $response->getBody()->getContents();

        // Do something with the response body, such as echo it
        dd($responseBody);
    }

    public function import()
    {
        $today = Carbon::now()->format('Y-m-d');
        $csvData = file_get_contents('imports\FedEx_SampleTracking.csv');
        $rows = array_map('str_getcsv', explode("\n", $csvData));
        foreach ($rows as $row) {
            dd($row[0]);
            // Do something with each row
            // $row[0] contains the first column, $row[1] contains the second column, and so on
        }
    }
}
