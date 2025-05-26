<?php
session_start();

require_once 'User.php';
require_once 'AccessLevel.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

/** @var User $user */
$user = unserialize($_SESSION['user']);

if ($user->getAccessLevel()->getLevelName() !== 'admin') {
    die("Access denied. Admins only.");
}

// Handle form submissions for settings (to be implemented)

// Load current settings (to be implemented)

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Panel - CRUD Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-black text-white p-4 flex justify-between items-center">
        <h1 class="text-xl font-bold">Admin Panel</h1>
        <div>
            <span class="mr-4">Hello, <?php echo htmlspecialchars($user->getUsername()); ?> (Admin)</span>
            <a href="index.php?logout=1" class="underline hover:text-gray-300">Logout</a>
        </div>
    </header>
    <main class="p-6 max-w-6xl mx-auto space-y-8">
        <section>
            <h2 class="text-lg font-semibold mb-4">User Access Management</h2>
            <p>Feature to manage user access rights and web service permissions will be implemented here.</p>
        </section>
        <section>
            <h2 class="text-lg font-semibold mb-4">Logo and Header Logo</h2>
            <p>Feature to upload and change logos will be implemented here.</p>
        </section>
        <section>
            <h2 class="text-lg font-semibold mb-4">Theme Customization</h2>
            <p>Feature to customize and save personalized themes per user will be implemented here.</p>
        </section>
        <section>
            <h2 class="text-lg font-semibold mb-4">Dashboard Logs</h2>
            <p>Feature to view detailed logs of user activity and access will be implemented here.</p>
        </section>
    </main>
</body>
</html>
