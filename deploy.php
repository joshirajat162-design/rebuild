<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

$html = $input["html"] ?? "";
$css  = $input["css"] ?? "";
$js   = $input["js"] ?? "";

if (!$html && !$css && !$js) {
    echo json_encode(["error" => "No content"]);
    exit;
}

$folder = "site_" . bin2hex(random_bytes(5));
$path = __DIR__ . "/sites/" . $folder;

if (!is_dir(__DIR__ . "/sites")) mkdir(__DIR__ . "/sites");
mkdir($path);

$index = "<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>ReBuild Site</title>
<style>$css</style>
</head>
<body>
$html
<script>$js</script>
</body>
</html>";

file_put_contents("$path/index.html", $index);

$scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https://" : "http://";
$url = $scheme . $_SERVER["HTTP_HOST"] . "/sites/$folder/";

echo json_encode(["url" => $url]);
exit;
?>
