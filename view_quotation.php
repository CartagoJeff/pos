<?php
require_once 'config.php';
redirectIfNotAuthenticated();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: quotations.php");
    exit();
}

$quotationId = intval($_GET['id']);

// Get quotation details
$stmt = $conn->prepare("SELECT q.*, u.username as created_by_name 
                       FROM quotations q 
                       LEFT JOIN users u ON q.created_by = u.id 
                       WHERE q.id = ?");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: quotations.php");
    exit();
}

$quotation = $result->fetch_assoc();
$stmt->close();

// Get job order details
$stmt = $conn->prepare("SELECT * FROM job_orders WHERE quotation_id = ?");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$jobOrder = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get job descriptions
$jobDescriptions = [];
if ($jobOrder) {
    $stmt = $conn->prepare("SELECT * FROM job_descriptions WHERE job_order_id = ?");
    $stmt->bind_param("i", $jobOrder['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $jobDescriptions = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quotation - Auto Repair Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        .customer-info, .vehicle-info {
            flex: 1;
        }
        .job-descriptions {
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f4f4f4;
        }
        .total-section {
            margin-top: 20px;
            text-align: right;
        }
        .action-buttons {
            margin-top: 30px;
            text-align: center;
        }
        .btn {
            padding: 10px 15px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-print {
            background-color: #17a2b8;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container">
        <div class="header-info">
            <div class="customer-info">
                <h2><?php echo htmlspecialchars($quotation['customer_name']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($quotation['address'])); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($quotation['date']); ?></p>
                <p><strong>Created by:</strong> <?php echo htmlspecialchars($quotation['created_by_name']); ?></p>
            </div>
            
            <div class="vehicle-info">
                <h3>Vehicle Information</h3>
                <p><strong>Plate Number:</strong> <?php echo htmlspecialchars($quotation['plate_number']); ?></p>
                <p><strong>Maker/Model:</strong> <?php echo htmlspecialchars($quotation['maker'] . ' ' . $quotation['model']); ?></p>
                <p><strong>Color:</strong> <?php echo htmlspecialchars($quotation['vehicle_color']); ?></p>
                <p><strong>Mileage:</strong> <?php echo number_format($quotation['mileage']); ?></p>
                <p><strong>Fuel Level:</strong> <?php echo htmlspecialchars($quotation['fuel_level']); ?></p>
            </div>
        </div>
        
        <?php if ($jobOrder): ?>
        <div class="job-order-info">
            <h3>Job Order: <?php echo htmlspecialchars($jobOrder['job_order_number']); ?></h3>
            <p><strong>Mechanic:</strong> <?php echo htmlspecialchars($jobOrder['mechanic_name']); ?></p>
            <p><strong>Time In:</strong> <?php echo htmlspecialchars($quotation['time_in']); ?></p>
            <p><strong>Date Release:</strong> <?php echo $quotation['date_release'] ? date('M d, Y', strtotime($quotation['date_release'])) : 'Not released'; ?></p>

        </div>
        <?php endif; ?>
        
        <div class="job-descriptions">
            <h3>Job Descriptions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Unit Price</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobDescriptions as $job): ?>
                    <tr>
                        <td><?php echo ucfirst($job['type']); ?></td>
                        <td><?php echo htmlspecialchars($job['description']); ?></td>
                        <td><?php echo $job['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($job['unit']); ?></td>
                        <td>₱<?php echo number_format($job['unit_price'], 2); ?></td>
                        <td>₱<?php echo number_format($job['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="total-section">
                <p><strong>Total Amount:</strong> ₱<?php echo number_format($quotation['total_amount'], 2); ?></p>
                <p><strong>Balance:</strong> ₱<?php echo number_format($quotation['balance'], 2); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($quotation['status']); ?></p>
            </div>
        </div>
        
        <div class="action-buttons">
            <a href="quotations.php" class="btn btn-secondary">Back to List</a>
            <a href="edit_quotation.php?id=<?php echo $quotation['id']; ?>" class="btn btn-primary">Edit</a>
            <a href="print_quotation.php?id=<?php echo $quotation['id']; ?>" class="btn btn-print">Print</a>
        </div>
    </div>
</body>
</html>