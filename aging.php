<?php
require_once 'config.php';
redirectIfNotAuthenticated();

// Get aging report data
$agingReport = [];
$stmt = $conn->prepare("SELECT q.id, q.customer_name, q.date, q.total_amount, q.balance 
                       FROM quotations q 
                       WHERE q.balance > 0 
                       ORDER BY q.date");
$stmt->execute();
$result = $stmt->get_result();
$agingData = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Process aging data
$agingBuckets = [
    '0-30' => 0,
    '31-60' => 0,
    '61-90' => 0,
    'over-90' => 0
];

$totalOutstanding = 0;
$totalBalance = 0;

foreach ($agingData as &$row) {
    $date = new DateTime($row['date']);
    $now = new DateTime();
    $interval = $date->diff($now);
    $days = $interval->days;
    
    $row['days_old'] = $days;
    
    if ($days <= 30) {
        $row['aging_bucket'] = '0-30 days';
        $agingBuckets['0-30'] += $row['balance'];
    } elseif ($days <= 60) {
        $row['aging_bucket'] = '31-60 days';
        $agingBuckets['31-60'] += $row['balance'];
    } elseif ($days <= 90) {
        $row['aging_bucket'] = '61-90 days';
        $agingBuckets['61-90'] += $row['balance'];
    } else {
        $row['aging_bucket'] = 'Over 90 days';
        $agingBuckets['over-90'] += $row['balance'];
    }
    
    $totalOutstanding += $row['total_amount'];
    $totalBalance += $row['balance'];
}

unset($row); // Break the reference
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aging Report - Auto Repair Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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
        .main-content {
            padding: 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            margin-top: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }
        tfoot th {
            background-color: #e9ecef;
        }
        .aging-0-30 {
            background-color: #d4edda;
        }
        .aging-31-60 {
            background-color: #fff3cd;
        }
        .aging-61-90 {
            background-color: #ffeeba;
        }
        .aging-over-90 {
            background-color: #f8d7da;
        }
        .chart-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem;
            margin-top: 2rem;
        }
        .print-btn {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .print-btn:hover {
            background-color: #5a6268;
        }
        .action-bar {
            margin-bottom: 1rem;
        }
        @media print {
            .header, .action-bar {
                display: none;
            }
            body {
                background-color: white;
            }
            table {
                box-shadow: none;
            }
        }
        .main-content {
            margin-left: 100px !important;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <h1>Aging Report</h1>
        
        <div style="overflow-x: auto;">
            <table id="agingTable">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Invoice Date</th>
                        <th>Days Outstanding</th>
                        <th>Aging Bucket</th>
                        <th>Total Amount</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agingData as $row): 
                        $rowClass = '';
                        if ($row['days_old'] <= 30) {
                            $rowClass = 'aging-0-30';
                        } elseif ($row['days_old'] <= 60) {
                            $rowClass = 'aging-31-60';
                        } elseif ($row['days_old'] <= 90) {
                            $rowClass = 'aging-61-90';
                        } else {
                            $rowClass = 'aging-over-90';
                        }
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                        <td><?php echo $row['days_old']; ?></td>
                        <td><?php echo $row['aging_bucket']; ?></td>
                        <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                        <td>₱<?php echo number_format($row['balance'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Total Outstanding</th>
                        <th></th>
                        <th>₱<?php echo number_format($totalOutstanding, 2); ?></th>
                        <th>₱<?php echo number_format($totalBalance, 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="chart-container">
            <h3>Aging Summary</h3>
            <canvas id="agingChart" height="100"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Aging chart
        const ctx = document.getElementById('agingChart').getContext('2d');
        const agingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['0-30 days', '31-60 days', '61-90 days', 'Over 90 days'],
                datasets: [{
                    label: 'Outstanding Balances',
                    data: [
                        <?php echo $agingBuckets['0-30']; ?>,
                        <?php echo $agingBuckets['31-60']; ?>,
                        <?php echo $agingBuckets['61-90']; ?>,
                        <?php echo $agingBuckets['over-90']; ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.2)',
                        'rgba(255, 193, 7, 0.2)',
                        'rgba(253, 126, 20, 0.2)',
                        'rgba(220, 53, 69, 0.2)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(253, 126, 20, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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

        // Add print functionality if needed
        // To use this, uncomment the print button in the HTML
        /*
        document.getElementById('printBtn')?.addEventListener('click', function() {
            window.print();
        });
        */
    </script>
</body>
</html>