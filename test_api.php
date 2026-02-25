<?php
$url = 'admin/api.php';
$data = ['action' => 'settings'];
$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'GET',
    ],
];
$context = stream_context_create($options);
$result = file_get_contents($url . '?' . http_build_query($data), false, $context);
echo $result;
?>