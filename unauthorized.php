<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized - Auto Repair Shop</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .error-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 400px;
            text-align: center;
        }
        .error-container h2 {
            color: #d9534f;
            margin-bottom: 1.5rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }
        .btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h2>Unauthorized Access</h2>
        <p>You don't have permission to access this page.</p>
        <p>Please contact your administrator if you believe this is an error.</p>
        <a href="dashboard.php" class="btn">Return to Dashboard</a>
    </div>
</body>
</html>