<?php

// -------------------------
// HARD-CODED CONFIG
// -------------------------
$GEMINI_API_KEY = ""; // <--- PUT YOUR KEY HERE

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// -------------------------
// Helper: Extract JSON from model output
// -------------------------
function extractJson($text) {
    $cleaned = preg_replace('/```json/i', '', $text);
    $cleaned = preg_replace('/```/', '', $cleaned);
    $cleaned = trim($cleaned);

    $first = strpos($cleaned, '{');
    $last = strrpos($cleaned, '}');

    if ($first === false || $last === false) {
        throw new Exception("No JSON object found in output");
    }

    $json = substr($cleaned, $first, $last - $first + 1);

    return json_decode($json, true);
}

// -------------------------
// Only POST allowed
// -------------------------
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "POST only"]);
    exit;
}

// Read input
$input = json_decode(file_get_contents("php://input"), true);
$prompt = $input["prompt"] ?? "";

if (!$prompt || trim($prompt) == "") {
    http_response_code(400);
    echo json_encode(["error" => "Missing prompt"]);
    exit;
}

$systemPrompt = "
Return ONLY valid JSON:
{
  \"html\": \"\",
  \"css\": \"\",
  \"js\": \"\"
}

NO markdown. NO backticks.

IMAGE RULES:
- Must use REAL URLs:
  - https://picsum.photos/seed/<seed>/800/600
  - https://placehold.co/800x600.jpg
  - https://images.unsplash.com/photo-<id>?auto=format&fit=crop&w=1200&q=80

STYLE RULES:
- Use responsive layouts
- Clean classes
- No <html> or <body>

User wants:
$prompt
";

// -------------------------
// Call Gemini API
// -------------------------
$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $systemPrompt]
            ]
        ]
    ],
    "generationConfig" => [
        "maxOutputTokens" => 6000,
        "temperature" => 0.7
    ]
];

$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$GEMINI_API_KEY");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// Parse API response
$data = json_decode($response, true);

if (!isset($data["candidates"][0]["content"]["parts"][0]["text"])) {
    echo json_encode([
        "error" => "Gemini returned no text",
        "raw" => $data
    ]);
    exit;
}

$raw = $data["candidates"][0]["content"]["parts"][0]["text"];

// Try to extract JSON
try {
    $json = extractJson($raw);
} catch (Exception $e) {
    echo json_encode([
        "error" => "JSON parse failed",
        "rawPreview" => substr($raw, 0, 300),
        "details" => $e->getMessage()
    ]);
    exit;
}

// Safety check for keys
if (!isset($json["html"], $json["css"], $json["js"])) {
    echo json_encode([
        "error" => "JSON missing required keys",
        "keys" => array_keys($json)
    ]);
    exit;
}

// Final output
echo json_encode($json);
exit;
?>
