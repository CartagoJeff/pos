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
    <title>Print Quotation - Auto Repair Shop</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .customer-info, .vehicle-info {
            width: 48%;
        }
        .info-box {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        .info-box h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f4f4f4;
        }
        .total-section {
            text-align: right;
            margin-bottom: 30px;
        }
        .total-section p {
            margin: 5px 0;
            font-size: 16px;
        }
        .total-section p strong {
            font-size: 18px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        .signature {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 200px;
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 10px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Auto Repair Shop</h1>
        <p>123 Repair Street, Auto City</p>
        <p>Phone: (123) 456-7890 | Email: info@autorepairshop.com</p>
    </div>
    
    <div class="info-section">
        <div class="customer-info">
            <div class="info-box">
                <h3>Customer Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($quotation['customer_name']); ?></p>
                <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($quotation['address'])); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($quotation['date']); ?></p>
                <p><strong>Quotation #:</strong> QT-<?php echo str_pad($quotation['id'], 4, '0', STR_PAD_LEFT); ?></p>
            </div>
        </div>
        
        <div class="vehicle-info">
            <div class="info-box">
                <h3>Vehicle Information</h3>
                <p><strong>Plate Number:</strong> <?php echo htmlspecialchars($quotation['plate_number']); ?></p>
                <p><strong>Maker</strong> <?php echo htmlspecialchars($quotation['maker']); ?></p>
                <p><strong>Model</strong> <?php echo htmlspecialchars($quotation['model']); ?></p>
                <p><strong>Color:</strong> <?php echo htmlspecialchars($quotation['vehicle_color']); ?></p>
                <p><strong>Mileage:</strong> <?php echo number_format($quotation['mileage']); ?></p>
                <p><strong>Fuel Level:</strong> <?php echo htmlspecialchars($quotation['fuel_level']); ?></p>
            </div>
        </div>
    </div>
    
    <?php if ($jobOrder): ?>
    <div class="info-section">
        <div class="info-box" style="width: 100%;">
            <h3>Qoutation Information</h3>
            <p><strong>Qoutation #:</strong> <?php echo htmlspecialchars($jobOrder['job_order_number']); ?></p>
            <p><strong>Mechanic:</strong> <?php echo htmlspecialchars($jobOrder['mechanic_name']); ?></p>
            <p><strong>Time In:</strong> <?php echo htmlspecialchars($quotation['time_in']); ?></p>
            <!-- <p><strong>Date Release:</strong> <?php echo htmlspecialchars($quotation['date_release']); ?></p> -->
        </div>
    </div>
    <?php endif; ?>
    
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
    
    <div class="signature">
        <div class="signature-box">
            <p>Customer's Signature</p>
        </div>
        <div class="signature-box">
            <p>Authorized Representative</p>
        </div>
    </div>
    
    <div class="footer">
        <p>Thank you for choosing our services!</p>
        <p>For inquiries, please call us at (123) 456-7890</p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Print Quotation
        </button>
        <button onclick="window.location.href='view_quotation.php?id=<?php echo $quotationId; ?>'" style="padding: 10px 20px; background-color: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            Back to View
        </button>
    </div>
    
    <script>
        window.onload = function() {
            // Auto-print when page loads
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>