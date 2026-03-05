<?php
session_start();

// Security headers
header("Content-Security-Policy: default-src 'self'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Check authentication
if (!isset($_SESSION['authenticated'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Database configuration
$servername = "localhost";
$username = "brightvi_root";
$password = "Art@dalvik197";
$dbname = "brightvi_BVMain";

// Get input data
$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : null;
$status = isset($data['status']) ? intval($data['status']) : null;
$phone = isset($data['phone']) ? htmlspecialchars($data['phone']) : null;
$exam_date = isset($data['exam_date']) ? htmlspecialchars($data['exam_date']) : null;
$exam_time = isset($data['exam_time']) ? htmlspecialchars($data['exam_time']) : null;

if (!$id || !in_array($status, [0, 1])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid request']));
}

// Update database
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Update approval status
    $stmt = $conn->prepare("UPDATE reservations SET approved = :status WHERE id = :id");
    $stmt->bindParam(':status', $status, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // If approved, send SMS
    if ($status === 1 && $phone) {
        $msg = "Bright Vision Englis Academy \nYour Exam Approved\n\n" . 
               "Exam Date: " . $exam_date . "\n Exam Time: " . $exam_time ."\n Powered by Apexdigital";

        $smsResponse = send_quicksend_sms_single(getConfigValue("SenderID"), $phone, $msg);
        $smsStatus = (strpos($smsResponse, '"error"') !== false) ? 'Error' : 'Success';
    }

    echo json_encode(['success' => true, 'phone' => $phone, 'sms_status' => $smsStatus ?? 'Not Sent']);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

// SMS Sending Function
function send_quicksend_sms_single($senderID, $to, $msg)
{
    $username = getConfigValue("sms_user");
    $password = getConfigValue("sms_password");

    $curl = curl_init();

    $postData = json_encode([
        "senderID" => $senderID,
        "to" => $to,
        "msg" => $msg,
    ]);

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://sms.apexinventives.com/api.php?FUN=SEND_SINGLE",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Basic " . base64_encode($username . ":" . $password),
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

// Function to Get Configuration Values (Replace with actual implementation)
function getConfigValue($key)
{
    $config = [
        "sms_user" => "dulip.kawshalya@gmail.com",
        "sms_password" => "15465692167c1a538d4cd1073559336",
        "SenderID" => "apexdigital"
    ];
    return $config[$key] ?? null;
}
?>
