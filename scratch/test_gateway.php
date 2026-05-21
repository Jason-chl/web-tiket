<?php
$url = "http://localhost/UK/tiketkonser/payment_gateway.php";
$data = http_build_query([
    'order_code' => 'TIX-XYZ', // fake, but should not crash
    'action_type' => 'pay_now'
]);

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $data
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "Result length: " . strlen($result) . "\n";
?>
