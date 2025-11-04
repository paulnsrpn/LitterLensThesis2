<?php
// =======================================================
// 📧 PHPMailer SMTP Email Sender — LitterLens Contact Form
// =======================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ✅ Include PHPMailer library
require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

// =======================================================
// 🌐 CORS (for JS fetch)
// =======================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// =======================================================
// 🧠 Get JSON payload
// =======================================================
$input = json_decode(file_get_contents("php://input"), true);

$firstName   = htmlspecialchars($input["firstName"] ?? "");
$lastName    = htmlspecialchars($input["lastName"] ?? "");
$email       = htmlspecialchars($input["email"] ?? "");
$countryCode = htmlspecialchars($input["countryCode"] ?? "");
$phone       = htmlspecialchars($input["phone"] ?? "");
$message     = htmlspecialchars($input["message"] ?? "");

// ✅ Validation
if (empty($firstName) || empty($lastName) || empty($email) || empty($message)) {
    echo json_encode(["success" => false, "error" => "Missing required fields."]);
    exit;
}

// =======================================================
// ✉️ PHPMailer SMTP CONFIGURATION
// =======================================================
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'eflorida1024@gmail.com';     // Your Gmail
    $mail->Password   = 'uqys dolu ijju wfdg';        // Gmail App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Sender & recipient
    $mail->setFrom('eflorida1024@gmail.com', 'LitterLens Contact');
    $mail->addAddress('eflorida1024@gmail.com'); // Receiver inbox
    $mail->addReplyTo($email, "$firstName $lastName");

    // Content
    $mail->isHTML(false);
    $mail->Subject = "📩 LitterLens Message from $firstName $lastName";
    $mail->Body    = "Name: $firstName $lastName\n"
                   . "Email: $email\n"
                   . "Phone: $countryCode $phone\n\n"
                   . "Message:\n$message";

    // Send
    $mail->send();
    echo json_encode(["success" => true, "message" => "✅ Message sent successfully!"]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "Mailer Error: " . $mail->ErrorInfo
    ]);
}
?>
