<?php
require_once 'config.php';
redirectIfNotAuthenticated();

// Get sales data with proper NULL handling
$dailySales = 0;
$weeklySales = 0;
$monthlySales = 0;

// Daily sales with COALESCE
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM quotations WHERE DATE(date) = CURDATE()");
$stmt->execute();
$stmt->bind_result($dailySales);
$stmt->fetch();
$stmt->close();

// Weekly sales with COALESCE
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM quotations WHERE YEARWEEK(date) = YEARWEEK(CURDATE())");
$stmt->execute();
$stmt->bind_result($weeklySales);
$stmt->fetch();
$stmt->close();

// Monthly sales with COALESCE
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM quotations WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
$stmt->execute();
$stmt->bind_result($monthlySales);
$stmt->fetch();
$stmt->close();

// Chart data (last 6 months) - already has COALESCE
$chartLabels = [];
$chartData = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M Y', strtotime($month . '-01'));
    
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM quotations WHERE DATE_FORMAT(date, '%Y-%m') = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $stmt->bind_result($amount);
    $stmt->fetch();
    $stmt->close();
    
    $chartData[] = $amount;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Auto Repair Shop</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js">
    <link rel="stylesheet" href="css/quotations.css">
    <link rel="stylesheet" href="css/mechanics.css">

    <style>
        /* Your existing CSS remains unchanged */
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
            margin-left: 200px !important;
            padding: 1rem;
        }
        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem;
            flex: 1;
            min-width: 300px;
        }
        .card h3 {
            margin-top: 0;
            color: #333;
        }
        .chart-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem;
            margin-bottom: 1rem;
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
    </style>
</head>
<body>
    <div class="header">
        <h2>Auto Repair Shop</h2>
        <div>
            <span>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <button class="logout-btn" id="logoutBtn">Logout</button>
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
        <h1>Dashboard</h1>
        
        <div class="card-container">
            <div class="card">
                <h3>Daily Sales</h3>
                <p id="dailySales">₱<?php echo number_format((float)$dailySales, 2); ?></p>
            </div>
            <div class="card">
                <h3>Weekly Sales</h3>
                <p id="weeklySales">₱<?php echo number_format((float)$weeklySales, 2); ?></p>
            </div>
            <div class="card">
                <h3>Monthly Sales</h3>
                <p id="monthlySales">₱<?php echo number_format((float)$monthlySales, 2); ?></p>
            </div>
        </div>
        
        <div class="chart-container">
            <h3>Sales Overview</h3>
            <canvas id="salesChart" height="100"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.getElementById('logoutBtn').addEventListener('click', function() {
            window.location.href = 'logout.php';
        });
        
        // Sales chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Sales',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₱' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>