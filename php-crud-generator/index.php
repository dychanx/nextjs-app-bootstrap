<?php
session_start();

require_once 'Database.php';
require_once 'User.php';
require_once 'AccessLevel.php';
require_once 'CRUDGenerator.php';
require_once 'error_handler.php'; // Include error handler for mode-based error handling

// Simple user authentication simulation with password hashing
function authenticate($username, $password) {
    // In real app, fetch user record from DB and verify password hash
    // For demo, hardcoded users with hashed passwords

    $users = [
        'admin' => [
            'id' => 1,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'access_level' => new AccessLevel('admin', ['create', 'read', 'update', 'delete'])
        ],
        'user' => [
            'id' => 2,
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'access_level' => new AccessLevel('user', ['read'])
        ]
    ];

    if (isset($users[$username]) && password_verify($password, $users[$username]['password_hash'])) {
        return new User($users[$username]['id'], $username, $users[$username]['access_level']);
    }
    return null;
}

// Handle login
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $user = authenticate($username, $password);
    if ($user) {
        session_regenerate_id(true); // Prevent session fixation
        $_SESSION['user'] = serialize($user);
        $_SESSION['last_activity'] = time();
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Login - CRUD Generator</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 flex items-center justify-center h-screen">
        <div class="bg-white p-8 rounded shadow-md w-96">
            <h1 class="text-2xl font-bold mb-6 text-center">Login</h1>
            <?php if (isset($error)): ?>
                <div class="bg-red-100 text-red-700 p-2 mb-4 rounded"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="index.php">
                <label class="block mb-2 font-semibold" for="username">Username</label>
                <input class="w-full p-2 border border-gray-300 rounded mb-4" type="text" id="username" name="username" required />
                <label class="block mb-2 font-semibold" for="password">Password</label>
                <input class="w-full p-2 border border-gray-300 rounded mb-4" type="password" id="password" name="password" required />
                <button class="w-full bg-black text-white py-2 rounded hover:bg-gray-800 transition" type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// User is logged in
/** @var User $user */
$user = unserialize($_SESSION['user']);

// Connect to DB
$db = new Database();

// For demo, list tables owned by current user
$tables = [];
$stid = $db->query("SELECT table_name FROM user_tables");
$tables = $db->fetchAll($stid);

// Handle CRUD generation request
if (isset($_POST['generate_crud']) && isset($_POST['table_name'])) {
    $tableName = $_POST['table_name'];
    // For simplicity, just redirect to CRUD page for the table
    header("Location: crud.php?table=" . urlencode($tableName));
    exit;
}

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - CRUD Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #fff;
            color: #111;
            margin: 0;
            padding: 0;
        }
        header {
            background-color: #000;
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 1.25rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        main {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        h2 {
            font-weight: 600;
            font-size: 1.125rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #000;
            padding-bottom: 0.25rem;
        }
        select {
            border: 1px solid #ccc;
            border-radius: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            flex-grow: 1;
            transition: border-color 0.3s ease;
        }
        select:focus {
            outline: none;
            border-color: #111;
        }
        button {
            background-color: #000;
            color: #fff;
            padding: 0.5rem 1.5rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border: none;
        }
        button:hover {
            background-color: #333;
        }
        form {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 2rem;
        }
        #loading-overlay {
            position: fixed;
            inset: 0;
            background-color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .loader {
            border-top-color: #000;
            animation: spin 1s linear infinite;
            border-radius: 9999px;
            border: 8px solid #e5e7eb;
            width: 64px;
            height: 64px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div id="loading-overlay">
        <div class="loader"></div>
    </div>
    <header>
        CRUD GENERATOR DASHBOARD
        <div>
            <span style="font-weight: 400; font-size: 0.875rem; text-transform: none; letter-spacing: normal; margin-right: 1rem;">
                Hello, <?php echo htmlspecialchars($user->getUsername()); ?> (<?php echo htmlspecialchars($user->getAccessLevel()->getLevelName()); ?>)
            </span>
            <a href="index.php?logout=1" style="color: #fff; text-decoration: underline; font-weight: 600;">Logout</a>
        </div>
    </header>
    <main>
        <section>
            <h2>Available Tables</h2>
            <form method="POST" action="index.php">
                <select name="table_name" required>
                    <option value="">Select a table</option>
                    <?php foreach ($tables as $table): ?>
                        <option value="<?php echo htmlspecialchars($table['TABLE_NAME']); ?>"><?php echo htmlspecialchars($table['TABLE_NAME']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($user->hasPermission('create')): ?>
                <button type="submit" name="generate_crud">Generate CRUD</button>
                <?php endif; ?>
            </form>
        </section>
        <section>
            <h2>Dashboard Summary</h2>
            <p>Number of tables: <?php echo count($tables); ?></p>
        </section>
    </main>
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
