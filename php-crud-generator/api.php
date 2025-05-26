<?php
// api.php
// Web service API for CRUD generator tables with token authentication

require_once 'Database.php';
require_once 'CRUDGenerator.php';

// Get table and token from query parameters or headers
$tableName = $_GET['table'] ?? null;
$token = $_GET['token'] ?? null;

// Check required parameters
if (!$tableName || !$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing table or token parameter']);
    exit;
}

$tableName = strtoupper($tableName);

// Load config for the table
$configFile = __DIR__ . '/config/' . $tableName . '.json';
if (!file_exists($configFile)) {
    http_response_code(404);
    echo json_encode(['error' => 'Table configuration not found']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);

// Check if web service is enabled and token matches
if (empty($config['web_service_enabled']) || $config['api_token'] !== $token) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Connect to DB
$db = new Database();
$crud = new CRUDGenerator($db, $tableName);

// Set response header
header('Content-Type: application/json');

// Handle HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // If id parameter is provided, fetch single record
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                $record = $crud->fetchById($id);
                if ($record) {
                    echo json_encode($record);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Record not found']);
                }
            } else {
                // Fetch all records
                $records = $crud->fetchAll();
                echo json_encode($records);
            }
            break;

        case 'PATCH':
            // Update record by id
            parse_str(file_get_contents("php://input"), $patchData);
            if (!isset($patchData['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing id for update']);
                break;
            }
            $id = $patchData['id'];
            unset($patchData['id']);
            $crud->update($id, $patchData);
            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            // Delete record by id
            parse_str(file_get_contents("php://input"), $deleteData);
            if (!isset($deleteData['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing id for delete']);
                break;
            }
            $id = $deleteData['id'];
            $crud->delete($id);
            echo json_encode(['success' => true]);
            break;

        case 'HEAD':
            // Return metadata headers
            header('X-Table-Name: ' . $tableName);
            header('X-Record-Count: ' . count($crud->fetchAll()));
            http_response_code(200);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$db->close();
?>
