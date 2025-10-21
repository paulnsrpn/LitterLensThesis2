<?php
require_once 'system_config.php';

// Sample admins
$admins = [
    [
        'admin_name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => password_hash('123456', PASSWORD_DEFAULT), // hashed
        'role' => 'superadmin'
    ],
    [
        'admin_name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'password' => password_hash('password', PASSWORD_DEFAULT),
        'role' => 'admin'
    ],
    [
        'admin_name' => 'Bob Johnson',
        'email' => 'bob@example.com',
        'password' => password_hash('letmein', PASSWORD_DEFAULT),
        'role' => 'admin'
    ]
];

foreach ($admins as $admin) {
    $res = insertRecord('admin', $admin);
    if (isset($res['error'])) {
        echo "Insert Error: " . $res['error'] . "<br>";
    } else {
        echo "Inserted Admin: ";
        print_r($res);
        echo "<br><br>";
    }
}
?>
