<?php
require_once 'config.php';
redirectIfNotAuthenticated();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_quotation'])) {
        // Add new quotation
        $customerName = $conn->real_escape_string($_POST['customer_name']);
        $address = $conn->real_escape_string($_POST['address']);
        $date = $conn->real_escape_string($_POST['date']);
        $plateNumber = $conn->real_escape_string($_POST['plate_number']);
        $maker = $conn->real_escape_string($_POST['maker']);
        $model = $conn->real_escape_string($_POST['model']);
        $vehicleColor = $conn->real_escape_string($_POST['vehicle_color']);
        $timeIn = $conn->real_escape_string($_POST['time_in']);
        $motorChasis = $conn->real_escape_string($_POST['motor_chasis']);
        $fuelLevel = $conn->real_escape_string($_POST['fuel_level']);
        $mileage = intval($_POST['mileage']);
        $dateRelease = $conn->real_escape_string($_POST['date_release']);
        $balance = floatval($_POST['balance']);
        
        // Insert quotation
        $stmt = $conn->prepare("INSERT INTO quotations (customer_name, address, date, plate_number, maker, model, 
                              vehicle_color, time_in, motor_chasis, fuel_level, mileage, date_release, balance, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssidsi", $customerName, $address, $date, $plateNumber, $maker, $model, 
                         $vehicleColor, $timeIn, $motorChasis, $fuelLevel, $mileage, $dateRelease, $balance, $_SESSION['user_id']);
        $stmt->execute();
        $quotationId = $stmt->insert_id;
        $stmt->close();
        
        // Insert job order
        $jobOrderNumber = 'JO-' . str_pad($quotationId, 5, '0', STR_PAD_LEFT);
        $mechanicName = $conn->real_escape_string($_POST['mechanic_name']);
        
        $stmt = $conn->prepare("INSERT INTO job_orders (quotation_id, job_order_number, mechanic_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $quotationId, $jobOrderNumber, $mechanicName);
        $stmt->execute();
        $jobOrderId = $stmt->insert_id;
        $stmt->close();
        
        // Insert job descriptions
        if (isset($_POST['job_type'])) {
            $totalAmount = 0;
            
            foreach ($_POST['job_type'] as $index => $type) {
                $description = $conn->real_escape_string($_POST['job_description'][$index]);
                $quantity = floatval($_POST['job_quantity'][$index]);
                $unit = $conn->real_escape_string($_POST['job_unit'][$index]);
                $unitPrice = floatval($_POST['job_unit_price'][$index]);
                $amount = $quantity * $unitPrice;
                $totalAmount += $amount;
                
                $stmt = $conn->prepare("INSERT INTO job_descriptions (job_order_id, type, description, quantity, unit, unit_price) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issdsd", $jobOrderId, $type, $description, $quantity, $unit, $unitPrice);
                $stmt->execute();
                $stmt->close();
            }
            
            // Update total amount in quotation
            $stmt = $conn->prepare("UPDATE quotations SET total_amount = ? WHERE id = ?");
            $stmt->bind_param("di", $totalAmount, $quotationId);
            $stmt->execute();
            $stmt->close();
        }
        
        $_SESSION['success'] = 'Quotation added successfully!';
        header("Location: quotations.php");
        exit();
    }
}

// Get all quotations
$quotations = [];
$stmt = $conn->prepare("SELECT q.*, u.username as created_by_name 
                       FROM quotations q 
                       LEFT JOIN users u ON q.created_by = u.id 
                       ORDER BY q.date DESC");
$stmt->execute();
$result = $stmt->get_result();
$quotations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotations - Auto Repair Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/quotations.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <h1>Quotations</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <div class="action-bar">
            <a href="add_quotation.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Quotation
            </a>
        </div>
        
        <table id="quotationsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Date</th>
                    <th>Vehicle</th>
                    <th>Total Amount</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotations as $quotation): ?>
                <tr>
                    <td>QT-<?php echo str_pad($quotation['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($quotation['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($quotation['date']); ?></td>
                    <td><?php echo htmlspecialchars($quotation['maker'] . ' ' . $quotation['model'] . ' (' . $quotation['plate_number'] . ')'); ?></td>
                    <td>₱<?php echo number_format($quotation['total_amount'], 2); ?></td>
                    <td>₱<?php echo number_format($quotation['balance'], 2); ?></td>
                    <td><?php echo ucfirst($quotation['status']); ?></td>
                    <td>
                        <a href="view_quotation.php?id=<?php echo $quotation['id']; ?>" class="action-btn view-btn" title="View"><i class="fas fa-eye"></i></a>
                        <a href="edit_quotation.php?id=<?php echo $quotation['id']; ?>" class="action-btn edit-btn" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="print_quotation.php?id=<?php echo $quotation['id']; ?>" class="action-btn print-btn" title="Print"><i class="fas fa-print"></i></a>
                        <a href="delete_quotation.php?id=<?php echo $quotation['id']; ?>" class="action-btn delete-btn" title="Delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>