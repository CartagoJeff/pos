<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Auto Repair Shop'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Common CSS styles for all pages */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .sidebar {
            width: 200px;
            background-color: #222;
            height: calc(100vh - 60px);
            position: fixed;
            color: white;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar li {
            padding: 1rem;
            border-bottom: 1px solid #444;
            cursor: pointer;
        }
        .sidebar li:hover {
            background-color: #444;
        }
        .main-content {
            margin-left: 200px;
            padding: 1rem;
        }
        .logout-btn {
            background: #d9534f;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }
        .logout-btn:hover {
            background: #c9302c;
        }
        .alert {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Auto Repair Shop</h2>
        <div>
            <span>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
    </div>
    
    <div class="sidebar">
        <ul>
            <li onclick="window.location.href='dashboard.php'">Dashboard</li>
            <li onclick="window.location.href='quotations.php'">Quotations</li>
            <li onclick="window.location.href='mechanics.php'">Mechanics</li>
            <li onclick="window.location.href='inventory.php'">Inventory</li>
            <li onclick="window.location.href='aging.php'">Aging Report</li>
        </ul>
    </div>
    
    <div class="main-content">