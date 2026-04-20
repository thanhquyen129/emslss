<?php

require_once __DIR__ . '/../config/db.php';
$_system = require __DIR__ . '/../config/system.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| GLOBAL HEADERS
|--------------------------------------------------------------------------
*/

if (!defined('EMSLSS_SKIP_JSON_HEADER')) {
    header('Content-Type: application/json; charset=utf-8');
}

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

function requireApiKey()
{
    global $_system;
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $expectedApiKey = $_system['security']['api_key'] ?? '';

    if ($expectedApiKey === '' || $apiKey !== $expectedApiKey) {
        responseError('Unauthorized', 401);
    }
}

/*
|--------------------------------------------------------------------------
| METHOD CHECK
|--------------------------------------------------------------------------
*/

function requireMethod($method = 'POST')
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        responseError('Method not allowed', 405);
    }
}

/*
|--------------------------------------------------------------------------
| JSON PARSER
|--------------------------------------------------------------------------
*/

function getJsonInput()
{
    $payload = file_get_contents("php://input");

    if (!$payload || trim($payload) === '') {
        responseError('Empty payload', 400);
    }

    $data = json_decode($payload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        responseError('Invalid JSON', 400);
    }

    return [$data, $payload];
}

/*
|--------------------------------------------------------------------------
| VALIDATOR
|--------------------------------------------------------------------------
*/

function validateRequired($data, $fields = [])
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            responseError("Missing field: $field", 422);
        }
    }
}

/*
|--------------------------------------------------------------------------
| PHONE NORMALIZER
|--------------------------------------------------------------------------
*/

function normalizePhone($phone)
{
    return preg_replace('/[^0-9]/', '', $phone);
}

/*
|--------------------------------------------------------------------------
| SERVICE VALIDATOR
|--------------------------------------------------------------------------
*/

function validateServiceType($service)
{
    $allowed = [
        'door_to_door',
        'door_to_hub',
        'hub_to_door'
    ];

    if (!in_array($service, $allowed)) {
        responseError('Invalid service_type', 422);
    }
}

/*
|--------------------------------------------------------------------------
| DUPLICATE CHECK
|--------------------------------------------------------------------------
*/

function checkDuplicateOrder($ems_code)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT id,status
        FROM emslss_orders
        WHERE ems_code=?
        LIMIT 1
    ");

    $stmt->bind_param("s", $ems_code);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

/*
|--------------------------------------------------------------------------
| LOGGER
|--------------------------------------------------------------------------
*/

function apiLog($source, $payload, $response)
{
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO emslss_api_logs(source,payload,response)
        VALUES(?,?,?)
    ");

    $stmt->bind_param("sss", $source, $payload, $response);
    $stmt->execute();
}

/*
|--------------------------------------------------------------------------
| TRANSACTION HELPERS
|--------------------------------------------------------------------------
*/

function beginTx()
{
    global $conn;
    $conn->begin_transaction();
}

function commitTx()
{
    global $conn;
    $conn->commit();
}

function rollbackTx()
{
    global $conn;
    $conn->rollback();
}

/*
|--------------------------------------------------------------------------
| STANDARD RESPONSE
|--------------------------------------------------------------------------
*/

function responseSuccess($data = [])
{
    echo json_encode(array_merge([
        'status' => 'success'
    ], $data), JSON_UNESCAPED_UNICODE);

    exit;
}

function responseDuplicate($order,$ems_code)
{
    echo json_encode([
        'status' => 'duplicate',
        'ems_code' => $ems_code,
        'current_status' => $order['status']
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

function responseError($message, $code = 400)
{
    http_response_code($code);

    echo json_encode([
        'status' => 'error',
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
|--------------------------------------------------------------------------
| SAFE EXECUTION WRAPPER
|--------------------------------------------------------------------------
*/

function safeExecute($callback)
{
    try {
        $callback();
    } catch (Exception $e) {

        rollbackTx();

        $msg = $e->getMessage();

        apiLog(
            'SYSTEM_ERROR',
            '',
            json_encode([
                'error' => $msg
            ], JSON_UNESCAPED_UNICODE)
        );

        responseError($msg, 500);
    }
}
?>