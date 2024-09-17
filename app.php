<?php
// Load environment variables
//$ngrok_token = "2m8lVLp3y5Kk70RN5wEqORL2GeF_297nCQHqzyAKrfX97im8w";
$domain_url="https://hello.com";
$signalwire_project_id = "a0627763-9b0f-4676-b612-080675477a56";
$signalwire_api_token = "PT19906fd177dcb98ce52a288f11797db5cf73d621ffe012c6";
$signalwire_space = "warriorsforlight.signalwire.com";
$from_number = "+12015795576";
$port = "3000";

// Use curl to connect to ngrok and get the public URL
// function connectToNgrok($ngrok_token, $port) {
//     $curl = curl_init();
    
//     curl_setopt_array($curl, [
//         CURLOPT_URL => "http://localhost:4040/api/tunnels",
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_HTTPHEADER => [
//             "Authorization: Bearer $ngrok_token",
//             "Content-Type: application/json"
//         ],
//         CURLOPT_POSTFIELDS => json_encode([
//             "addr" => $port
//         ])
//     ]);

//     $response = curl_exec($curl);
//     curl_close($curl);

//     $data = json_decode($response, true);
//     return $data['public_url'] ?? '';
// }

//$ngrok_url = connectToNgrok($ngrok_token, $port);
//echo $ngrok_url;

// Simple REST client using cURL for SignalWire API
function createSignalWireCall($to, $from, $url, $amdCallbackUrl) {
    global $signalwire_project_id, $signalwire_api_token, $signalwire_space;
    
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://$signalwire_space.signalwire.com/api/laml/2010-04-01/Accounts/$signalwire_project_id/Calls.json",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$signalwire_project_id:$signalwire_api_token",
        CURLOPT_POSTFIELDS => http_build_query([
            "To" => $to,
            "From" => $from,
            "Url" => $url,
            "MachineDetection" => "DetectMessageEnd",
            "MachineDetectionTimeout" => 45,
            "AsyncAmd" => true,
            "AsyncAmdStatusCallback" => $amdCallbackUrl,
            "AsyncAmdStatusCallbackMethod" => "POST"
        ])
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}

// Simulated availability data
$availability = [
    "2023-07-05" => [],
    "2024-09-16" => ["11:15", "14:30"],
    "2023-07-11" => ["13:00"]
];

// Start Reminder Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/startReminder') {
    $name = $_POST['name'];
    $number = $_POST['number'];
    $date = $_POST['date'];
    $time = $_POST['time'];

    $url = $domain_url . '/agent?name=' . urlencode($name) . '&date=' . urlencode($date) . '&time=' . urlencode($time);
    $amdCallbackUrl = $domain_url . "/amd?name=$name&date=$date&time=$time";

    createSignalWireCall($number, $from_number, $url, $amdCallbackUrl);

    http_response_code(200);
    exit;
}

// AMD (Answering Machine Detection) Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/amd') {
    $name = $_GET['name'];
    $date = $_GET['date'];
    $time = $_GET['time'];

    $answeredBy = $_POST['AnsweredBy'] ?? '';
    
    if (in_array($answeredBy, ["machine_end_beep", "machine_end_silence", "machine_end_other"])) {
        $callSid = $_POST['CallSid'];
        $url = $domain_url . "/leaveVoicemail?name=$name&date=$date&time=$time";

        updateCall($callSid, $url); // Update call for voicemail
    }

    http_response_code(200);
    exit;
}

// Update a SignalWire call
function updateCall($callSid, $url) {
    global $signalwire_project_id, $signalwire_api_token, $signalwire_space;
    
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://$signalwire_space.signalwire.com/api/laml/2010-04-01/Accounts/$signalwire_project_id/Calls/$callSid.json",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "$signalwire_project_id:$signalwire_api_token",
        CURLOPT_POSTFIELDS => http_build_query([
            "Url" => $url
        ])
    ]);

    curl_exec($curl);
    curl_close($curl);
}

// Agent Interaction Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/agent') {
    $name = $_GET['name'];
    $date = $_GET['date'];
    $time = $_GET['time'];

    $response = '<?xml version="1.0" encoding="UTF-8"?>';
    $response .= '<Response>';
    $response .= '<Connect>';
    $response .= '<AI>';
    $response .= '<Prompt confidence="0.2" temperature="0">';
    $response .= 'You are Gordon, an assistant at Doctor Fibonacci\'s office...'; // Insert full AI prompt here
    $response .= '</Prompt>';
    $response .= '</AI>';
    $response .= '</Connect>';
    $response .= '</Response>';

    echo $response;
    exit;
}

// Leave Voicemail Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/leaveVoicemail') {
    $name = $_GET['name'];
    $date = $_GET['date'];
    $time = $_GET['time'];

    $response = '<?xml version="1.0" encoding="UTF-8"?>';
    $response .= '<Response>';
    $response .= '<Connect>';
    $response .= '<AI>';
    $response .= '<Prompt confidence="0.2" temperature="0">';
    $response .= 'You are Gordon, an assistant at Doctor Fibonacci\'s office leaving a voicemail...'; // Insert voicemail prompt
    $response .= '</Prompt>';
    $response .= '</AI>';
    $response .= '</Connect>';
    $response .= '</Response>';

    echo $response;
    exit;
}

// Function Handler (for AI functions like get_available_times and update_appointment_schedule)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/functionHandler') {
    $functionName = $_POST['function'];
    $argument = $_POST['argument']['raw'];

    switch ($functionName) {
        case 'get_available_times':
            $day = $argument;
            if (!isset($availability[$day])) {
                $availability[$day] = [];
            }

            $response = json_encode(['response' => json_encode($availability[$day])]);
            echo $response;
            break;

        case 'update_appointment_schedule':
            $arguments = explode(",", $argument);
            $newDate = $arguments[0];
            $newTime = $arguments[1];
            $oldDate = $arguments[2];
            $oldTime = $arguments[3];

            // Remove new appointment time from available times
            if (isset($availability[$newDate])) {
                $availability[$newDate] = array_filter($availability[$newDate], function($time) use ($newTime) {
                    return $time !== $newTime;
                });
            }

            // Add old appointment time back to available times
            $availability[$oldDate][] = $oldTime;

            echo json_encode(['response' => 'Appointment rescheduled.']);
            break;
    }
    exit;
}

?>
