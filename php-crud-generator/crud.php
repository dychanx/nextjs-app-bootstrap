<?php
session_start();

require_once 'Database.php';
require_once 'User.php';
require_once 'AccessLevel.php';
require_once 'CRUDGenerator.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

/** @var User $user */
$user = unserialize($_SESSION['user']);

// Check table parameter
if (!isset($_GET['table'])) {
    die("Table name is required.");
}

$tableName = $_GET['table'];

// Config file path
$configDir = __DIR__ . '/config';
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}
$configFile = $configDir . '/' . strtoupper($tableName) . '.json';

// Load config
$config = [
    'visible_columns' => [],
    'editable_columns' => [],
    'web_service_enabled' => false,
    'api_token' => ''
];
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
}

// Connect to DB
$db = new Database();

// Instantiate CRUD generator
$crud = new CRUDGenerator($db, $tableName);

// Check permissions
if (!$user->hasPermission('read')) {
    die("You do not have permission to view this page.");
}

// CSRF token generation and validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle form submissions for create, update, delete, and config save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Invalid CSRF token");
    }

    if (isset($_POST['save_config']) && $user->hasPermission('update')) {
        $visible = $_POST['visible_columns'] ?? [];
        $editable = $_POST['editable_columns'] ?? [];
        $webServiceEnabled = isset($_POST['web_service_enabled']) ? true : false;
        $apiToken = $config['api_token'] ?? '';

        // Generate new token if enabling web service and no token exists
        if ($webServiceEnabled && empty($apiToken)) {
            $apiToken = bin2hex(random_bytes(16));
        }

        $config['visible_columns'] = $visible;
        $config['editable_columns'] = $editable;
        $config['web_service_enabled'] = $webServiceEnabled;
        $config['api_token'] = $apiToken;

        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        header("Location: crud.php?table=" . urlencode($tableName));
        exit;
    } elseif (isset($_POST['create']) && $user->hasPermission('create')) {
        $data = $_POST['data'] ?? [];
        $crud->insert($data);
        header("Location: crud.php?table=" . urlencode($tableName));
        exit;
    } elseif (isset($_POST['update']) && $user->hasPermission('update')) {
        $id = $_POST['id'] ?? null;
        $data = $_POST['data'] ?? [];
        if ($id !== null) {
            $crud->update($id, $data);
            header("Location: crud.php?table=" . urlencode($tableName));
            exit;
        }
    } elseif (isset($_POST['delete']) && $user->hasPermission('delete')) {
        $id = $_POST['id'] ?? null;
        if ($id !== null) {
            $crud->delete($id);
            header("Location: crud.php?table=" . urlencode($tableName));
            exit;
        }
    }
}

// Fetch all rows
$rows = $crud->fetchAll();
$columns = $crud->getColumns();
$primaryKey = $crud->getPrimaryKey();

// Filter columns based on config visible_columns if set
if (!empty($config['visible_columns'])) {
    $columns = array_filter($columns, function($col) use ($config) {
        return in_array($col['COLUMN_NAME'], $config['visible_columns']);
    });
}

// Prepare editable columns set for quick lookup
$editableColumns = [];
if (!empty($config['editable_columns'])) {
    $editableColumns = array_flip($config['editable_columns']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CRUD - <?php echo htmlspecialchars($tableName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="loading-overlay" class="fixed inset-0 bg-white flex items-center justify-center z-50">
        <div class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-16 w-16"></div>
    </div>
    <header class="bg-black text-white p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold">CRUD for Table: <?php echo htmlspecialchars($tableName); ?></h1>
        <div>
            <a href="index.php" class="underline hover:text-gray-300 mr-4">Dashboard</a>
            <a href="index.php?logout=1" class="underline hover:text-gray-300">Logout</a>
        </div>
    </header>
    <main class="p-6 max-w-6xl mx-auto space-y-8">
        <section>
            <h2 class="text-lg font-semibold mb-4">Manage Columns & Web Service</h2>
            <form method="POST" action="crud.php?table=<?php echo urlencode($tableName); ?>" class="bg-white p-4 rounded shadow space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
                <div>
                    <h3 class="font-semibold mb-2">Visible Columns</h3>
                    <div class="grid grid-cols-4 gap-2">
                        <?php foreach ($crud->getColumns() as $col): ?>
                            <label class="inline-flex items-center space-x-2">
                                <input type="checkbox" name="visible_columns[]" value="<?php echo htmlspecialchars($col['COLUMN_NAME']); ?>" <?php echo in_array($col['COLUMN_NAME'], $config['visible_columns']) ? 'checked' : ''; ?> />
                                <span><?php echo htmlspecialchars($col['COLUMN_NAME']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold mb-2">Editable Columns</h3>
                    <div class="grid grid-cols-4 gap-2">
                        <?php foreach ($crud->getColumns() as $col): ?>
                            <label class="inline-flex items-center space-x-2">
                                <input type="checkbox" name="editable_columns[]" value="<?php echo htmlspecialchars($col['COLUMN_NAME']); ?>" <?php echo in_array($col['COLUMN_NAME'], $config['editable_columns']) ? 'checked' : ''; ?> />
                                <span><?php echo htmlspecialchars($col['COLUMN_NAME']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold mb-2">Enable Web Service</h3>
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" name="web_service_enabled" value="1" <?php echo $config['web_service_enabled'] ? 'checked' : ''; ?> />
                        <span>Enable JSON Web Service API for this table</span>
                    </label>
                    <?php if ($config['web_service_enabled']): ?>
                        <p class="mt-2 text-sm text-gray-600">API Token: <code><?php echo htmlspecialchars($config['api_token']); ?></code></p>
                        <p class="text-sm text-gray-600">API Base URL: <code><?php echo htmlspecialchars((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/api.php?table=" . urlencode($tableName)); ?></code></p>
                    <?php endif; ?>
                </div>
                <button type="submit" name="save_config" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">Save Settings</button>
            </form>
        </section>
        <section>
            <h2 class="text-lg font-semibold mb-4">Records</h2>
            <table class="min-w-full bg-white border border-gray-300 rounded">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th class="border px-4 py-2 bg-gray-200"><?php echo htmlspecialchars($col['COLUMN_NAME']); ?></th>
                        <?php endforeach; ?>
                        <?php if ($user->hasPermission('update') || $user->hasPermission('delete')): ?>
                            <th class="border px-4 py-2 bg-gray-200">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <td class="border px-4 py-2"><?php echo htmlspecialchars($row[$col['COLUMN_NAME']]); ?></td>
                            <?php endforeach; ?>
                            <?php if ($user->hasPermission('update') || $user->hasPermission('delete')): ?>
                                <td class="border px-4 py-2 space-x-2">
                                    <?php if ($user->hasPermission('update')): ?>
                                    <form method="POST" action="crud.php?table=<?php echo urlencode($tableName); ?>" class="inline-block">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row[$primaryKey]); ?>" />
                                        <?php foreach ($columns as $col): ?>
                                            <input type="hidden" name="data[<?php echo htmlspecialchars($col['COLUMN_NAME']); ?>]" value="<?php echo htmlspecialchars($row[$col['COLUMN_NAME']]); ?>" />
                                        <?php endforeach; ?>
                                        <button type="submit" name="update" class="bg-blue-600 text-white px-2 py-1 rounded hover:bg-blue-700">Edit</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($user->hasPermission('delete')): ?>
                                    <form method="POST" action="crud.php?table=<?php echo urlencode($tableName); ?>" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($row[$primaryKey]); ?>" />
                                        <button type="submit" name="delete" class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php if ($user->hasPermission('create')): ?>
        <section>
            <h2 class="text-lg font-semibold mb-4">Add New Record</h2>
            <form method="POST" action="crud.php?table=<?php echo urlencode($tableName); ?>" class="bg-white p-4 rounded shadow space-y-4">
                <?php foreach ($columns as $col): ?>
                    <div>
                        <label class="block font-semibold mb-1" for="data_<?php echo htmlspecialchars($col['COLUMN_NAME']); ?>"><?php echo htmlspecialchars($col['COLUMN_NAME']); ?></label>
                        <input class="w-full border border-gray-300 rounded p-2" type="text" id="data_<?php echo htmlspecialchars($col['COLUMN_NAME']); ?>" name="data[<?php echo htmlspecialchars($col['COLUMN_NAME']); ?>]" />
                    </div>
                <?php endforeach; ?>
                <button type="submit" name="create" class="bg-black text-white px-4 py-2 rounded hover:bg-gray-800 transition">Add Record</button>
            </form>
        </section>
        <?php endif; ?>
    </main>
    <style>
        .loader {
            border-top-color: #000000;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <script>
        window.addEventListener('load', function() {
            var overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        });
    </script>
</body>
</html>
