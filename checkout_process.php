<?php
require 'includes/requirements.php';
require 'includes/auth.php';
require 'includes/db.php';
require 'includes/url.php';
$client = require 'includes/square.php';

$token = $_POST['token'] ?? '';
$listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;

if (!$token || !$listing_id) {
    header('Location: cancel.php');
    exit;
}

$stmt = $conn->prepare('SELECT price FROM listings WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$stmt->bind_result($price);
if (!$stmt->fetch()) {
    $stmt->close();
    header('Location: cancel.php');
    exit;
}
$stmt->close();

$amount = (int)round($price * 100);

use Square\Exceptions\ApiException;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;

$paymentsApi = $client->getPaymentsApi();

$money = new Money();
$money->setAmount($amount);
$money->setCurrency('USD');

$paymentRequest = new CreatePaymentRequest(uniqid('', true), $token);
$paymentRequest->setAmountMoney($money);
$paymentRequest->setLocationId($squareConfig['location_id']);

try {
    $apiResponse = $paymentsApi->createPayment($paymentRequest);
    $result = $apiResponse->getResult();
    $status = $result->getPayment()->getStatus();
    $paymentId = $result->getPayment()->getId();
} catch (ApiException $e) {
    $status = 'FAILED';
    $paymentId = null;
}

if ($stmt = $conn->prepare('INSERT INTO payments (user_id, listing_id, amount, payment_id, status) VALUES (?,?,?,?,?)')) {
    $stmt->bind_param('iiiss', $user_id, $listing_id, $amount, $paymentId, $status);
    $stmt->execute();
    $stmt->close();
}

if ($status === 'COMPLETED') {
    header('Location: success.php');
    exit;
}

header('Location: cancel.php');
exit;
?>
