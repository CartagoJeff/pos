<?php
require_once 'config.php';
redirectIfNotAuthenticated();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: quotations.php");
    exit();
}

$quotationId = intval($_GET['id']);

// Get inventory parts for dropdown
$parts = [];
$result = $conn->query("SELECT id, part_name, quantity, unit_price FROM inventory ORDER BY part_name");
if ($result) {
    $parts = $result->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quotation'])) {
    // Update quotation
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
    $balance = floatval($_POST['balance']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update quotation
        $stmt = $conn->prepare("UPDATE quotations SET 
                              customer_name = ?, 
                              address = ?, 
                              date = ?, 
                              plate_number = ?, 
                              maker = ?, 
                              model = ?,
                              vehicle_color = ?, 
                              time_in = ?, 
                              motor_chasis = ?, 
                              fuel_level = ?, 
                              mileage = ?,
                              balance = ?, 
                              status = ?
                              WHERE id = ?");
        
        $stmt->bind_param("sssssssssssssi", 
            $customerName, 
            $address, 
            $date, 
            $plateNumber, 
            $maker, 
            $model,
            $vehicleColor, 
            $timeIn, 
            $motorChasis, 
            $fuelLevel, 
            $mileage,
            $balance, 
            $status, 
            $quotationId
        );
        
        $stmt->execute();
        $stmt->close();
        
        // Update job order mechanic
        $mechanicName = $conn->real_escape_string($_POST['mechanic_name']);
        
        $stmt = $conn->prepare("UPDATE job_orders SET mechanic_name = ? WHERE quotation_id = ?");
        $stmt->bind_param("si", $mechanicName, $quotationId);
        $stmt->execute();
        $stmt->close();
        
        // Restore quantities from old job descriptions (parts only)
        $stmt = $conn->prepare("SELECT description, quantity FROM job_descriptions 
                              WHERE job_order_id = (SELECT id FROM job_orders WHERE quotation_id = ?) 
                              AND type = 'part'");
        $stmt->bind_param("i", $quotationId);
        $stmt->execute();
        $result = $stmt->get_result();
        $oldParts = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($oldParts as $part) {
            $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE part_name = ?");
            $stmt->bind_param("is", $part['quantity'], $part['description']);
            $stmt->execute();
            $stmt->close();
        }
        
        // Delete existing job descriptions
        $stmt = $conn->prepare("DELETE FROM job_descriptions WHERE job_order_id = (SELECT id FROM job_orders WHERE quotation_id = ?)");
        $stmt->bind_param("i", $quotationId);
        $stmt->execute();
        $stmt->close();
        
        // Insert new job descriptions and process inventory
        if (isset($_POST['job_type'])) {
            $totalAmount = 0;
            
            // Get job order ID
            $stmt = $conn->prepare("SELECT id FROM job_orders WHERE quotation_id = ?");
            $stmt->bind_param("i", $quotationId);
            $stmt->execute();
            $result = $stmt->get_result();
            $jobOrder = $result->fetch_assoc();
            $stmt->close();
            
            if ($jobOrder) {
                foreach ($_POST['job_type'] as $index => $type) {
                    $description = $conn->real_escape_string($_POST['job_description'][$index]);
                    $quantity = floatval($_POST['job_quantity'][$index]);
                    $unit = $conn->real_escape_string($_POST['job_unit'][$index]);
                    $unitPrice = floatval($_POST['job_unit_price'][$index]);
                    $amount = $quantity * $unitPrice;
                    $totalAmount += $amount;
                    
                    $stmt = $conn->prepare("INSERT INTO job_descriptions (job_order_id, type, description, quantity, unit, unit_price) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issdsd", $jobOrder['id'], $type, $description, $quantity, $unit, $unitPrice);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Reduce inventory for parts
                    if ($type === 'part' && isset($_POST['part_id'][$index])) {
                        $partId = intval($_POST['part_id'][$index]);
                        $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                        $stmt->bind_param("iii", $quantity, $partId, $quantity);
                        $stmt->execute();
                        
                        if ($stmt->affected_rows === 0) {
                            throw new Exception("Insufficient quantity for selected part");
                        }
                        $stmt->close();
                    }
                }
                
                // Update total amount in quotation
                $stmt = $conn->prepare("UPDATE quotations SET total_amount = ? WHERE id = ?");
                $stmt->bind_param("di", $totalAmount, $quotationId);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = 'Quotation updated successfully!';
        header("Location: view_quotation.php?id=" . $quotationId);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: edit_quotation.php?id=$quotationId");
        exit();
    }
}

// Get quotation details
$stmt = $conn->prepare("SELECT * FROM quotations WHERE id = ?");
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
    <title>Edit Quotation - Auto Repair Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/quotations.css">
    <style>
        .part-select-container {
            display: none;
        }
        .job-description-block {
            position: relative;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .remove-job-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
        }
        .quantity-warning {
            color: #dc3545;
            font-size: 0.8em;
            display: none;
        }
        #quotationModal .modal-content {
            max-width: 1000px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <h1>Edit Quotation</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div id="quotationModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="window.location.href='view_quotation.php?id=<?php echo $quotationId; ?>'">&times;</span>
                <h2>Edit Quotation</h2>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="customerName">Name/Company</label>
                                <input type="text" id="customerName" name="customer_name" value="<?php echo htmlspecialchars($quotation['customer_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($quotation['address']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($quotation['date']); ?>" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="plateNumber">Plate Number</label>
                                <input type="text" id="plateNumber" name="plate_number" value="<?php echo htmlspecialchars($quotation['plate_number']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="maker">Maker</label>
                                <input type="text" id="maker" name="maker" value="<?php echo htmlspecialchars($quotation['maker']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="model">Model</label>
                                <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($quotation['model']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="vehicleColor">Vehicle Color</label>
                                <input type="text" id="vehicleColor" name="vehicle_color" value="<?php echo htmlspecialchars($quotation['vehicle_color']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="timeIn">Time In</label>
                                <input type="time" id="timeIn" name="time_in" value="<?php echo htmlspecialchars($quotation['time_in']); ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="motorChasis">Motor/Chasis No.</label>
                                <input type="text" id="motorChasis" name="motor_chasis" value="<?php echo htmlspecialchars($quotation['motor_chasis']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="fuelLevel">Fuel Level</label>
                                <input type="text" id="fuelLevel" name="fuel_level" value="<?php echo htmlspecialchars($quotation['fuel_level']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="mileage">Mileage</label>
                                <input type="number" id="mileage" name="mileage" value="<?php echo htmlspecialchars($quotation['mileage']); ?>">
                            </div>
                        </div>

                        <div class="form-col">
                            <div class="form-group">
                                <label for="dateRelease">Date Release</label>
                                <input type="date" id="dateRelease" name="date_release" value="<?php echo htmlspecialchars($quotation['date_release']); ?>">
                            </div>
                        </div>

                        <div class="form-col">
                            <div class="form-group">
                                <label for="balance">Balance</label>
                                <input type="number" id="balance" name="balance" step="0.01" value="<?php echo htmlspecialchars($quotation['balance']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="mechanicName">Mechanic Name</label>
                                <input type="text" id="mechanicName" name="mechanic_name" value="<?php echo htmlspecialchars($jobOrder ? $jobOrder['mechanic_name'] : ''); ?>">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="pending" <?php echo $quotation['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in-progress" <?php echo $quotation['status'] === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $quotation['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <h3>Job Descriptions</h3>
                    <div id="jobDescriptionsContainer">
                        <?php foreach ($jobDescriptions as $job): ?>
                        <div class="job-description-block">
                            <button type="button" class="remove-job-btn"><i class="fas fa-times"></i></button>
                            <div class="form-group">
                                <label>Type</label>
                                <select class="job-type" name="job_type[]" onchange="updateDescriptionField(this)">
                                    <option value="part" <?php echo $job['type'] === 'part' ? 'selected' : ''; ?>>Part</option>
                                    <option value="labor" <?php echo $job['type'] === 'labor' ? 'selected' : ''; ?>>Labor</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <div class="part-select-container" <?php echo $job['type'] === 'part' ? '' : 'style="display: none;"'; ?>>
                                    <select class="part-select" name="part_id[]" onchange="updatePartDetails(this)">
                                        <option value="">Select a part</option>
                                        <?php foreach ($parts as $part): ?>
                                            <option value="<?php echo $part['id']; ?>" 
                                                data-quantity="<?php echo $part['quantity']; ?>"
                                                data-price="<?php echo $part['unit_price']; ?>"
                                                <?php if ($job['type'] === 'part' && $job['description'] === $part['part_name']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($part['part_name']); ?> (<?php echo $part['quantity']; ?> available)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="quantity-warning">Insufficient quantity</div>
                                </div>
                                <textarea class="job-description" name="job_description[]" rows="2" <?php echo $job['type'] === 'part' ? 'style="display: none;"' : ''; ?>><?php echo htmlspecialchars($job['description']); ?></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group" style="flex: 1;">
                                    <label>Quantity</label>
                                    <input type="number" class="job-qty" name="job_quantity[]" value="<?php echo htmlspecialchars($job['quantity']); ?>" min="1" step="0.01" onchange="validateQuantity(this)">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>Unit</label>
                                    <input type="text" class="job-unit" name="job_unit[]" value="<?php echo htmlspecialchars($job['unit']); ?>">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>Unit Price</label>
                                    <input type="number" class="job-unit-price" name="job_unit_price[]" value="<?php echo htmlspecialchars($job['unit_price']); ?>" min="0" step="0.01">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <label>Amount</label>
                                    <input type="text" class="job-amount" value="<?php echo number_format($job['quantity'] * $job['unit_price'], 2); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-job-btn" id="addJobBtn">
                        <i class="fas fa-plus"></i> Add Job Description
                    </button>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label>Total Amount: <span id="totalAmountDisplay">₱<?php echo number_format($quotation['total_amount'], 2); ?></span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn" onclick="window.location.href='view_quotation.php?id=<?php echo $quotationId; ?>'">Cancel</button>
                        <button type="submit" name="update_quotation" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Parts data for JavaScript
        const parts = <?php echo json_encode($parts); ?>;
        
        // Add job description block
        document.getElementById('addJobBtn').addEventListener('click', function() {
            const jobBlock = document.createElement('div');
            jobBlock.className = 'job-description-block';
            jobBlock.innerHTML = `
                <button type="button" class="remove-job-btn"><i class="fas fa-times"></i></button>
                <div class="form-group">
                    <label>Type</label>
                    <select class="job-type" name="job_type[]" onchange="updateDescriptionField(this)">
                        <option value="part">Part</option>
                        <option value="labor">Labor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <div class="part-select-container">
                        <select class="part-select" name="part_id[]" onchange="updatePartDetails(this)">
                            <option value="">Select a part</option>
                            ${parts.map(part => 
                                `<option value="${part.id}" 
                                  data-quantity="${part.quantity}"
                                  data-price="${part.unit_price}">${part.part_name} (${part.quantity} available)</option>`
                            ).join('')}
                        </select>
                        <div class="quantity-warning">Insufficient quantity</div>
                    </div>
                    <textarea class="job-description" name="job_description[]" rows="2" style="display: none;"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>Quantity</label>
                        <input type="number" class="job-qty" name="job_quantity[]" value="1" min="1" step="0.01" onchange="validateQuantity(this)">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Unit</label>
                        <input type="text" class="job-unit" name="job_unit[]" value="pc">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Unit Price</label>
                        <input type="number" class="job-unit-price" name="job_unit_price[]" min="0" step="0.01">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Amount</label>
                        <input type="text" class="job-amount" readonly>
                    </div>
                </div>
            `;
            
            document.getElementById('jobDescriptionsContainer').appendChild(jobBlock);
            updateDescriptionField(jobBlock.querySelector('.job-type'));
            addJobBlockEventListeners(jobBlock);
        });
        
        function updateDescriptionField(select) {
            const container = select.closest('.job-description-block');
            const partSelectContainer = container.querySelector('.part-select-container');
            const descriptionTextarea = container.querySelector('.job-description');
            
            if (select.value === 'part') {
                partSelectContainer.style.display = 'block';
                descriptionTextarea.style.display = 'none';
                descriptionTextarea.required = false;
                container.querySelector('.part-select').required = true;
            } else {
                partSelectContainer.style.display = 'none';
                descriptionTextarea.style.display = 'block';
                descriptionTextarea.required = true;
                container.querySelector('.part-select').required = false;
            }
        }
        
        function updatePartDetails(select) {
            const container = select.closest('.job-description-block');
            const descriptionTextarea = container.querySelector('.job-description');
            const unitPriceInput = container.querySelector('.job-unit-price');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                descriptionTextarea.value = selectedOption.text.split(' (')[0];
                unitPriceInput.value = selectedOption.dataset.price || '0';
                
                // Update quantity validation
                const maxQuantity = parseInt(selectedOption.dataset.quantity) || 0;
                const qtyInput = container.querySelector('.job-qty');
                qtyInput.max = maxQuantity;
                qtyInput.value = 1;
                validateQuantity(qtyInput);
            }
        }
        
        function validateQuantity(input) {
            const container = input.closest('.job-description-block');
            const partSelect = container.querySelector('.part-select');
            const warning = container.querySelector('.quantity-warning');
            
            if (partSelect.value && container.querySelector('.job-type').value === 'part') {
                const selectedOption = partSelect.options[partSelect.selectedIndex];
                const maxQuantity = parseInt(selectedOption.dataset.quantity) || 0;
                const quantity = parseFloat(input.value) || 0;
                
                if (quantity > maxQuantity) {
                    warning.style.display = 'block';
                    input.setCustomValidity('Quantity exceeds available stock');
                } else {
                    warning.style.display = 'none';
                    input.setCustomValidity('');
                }
            }
        }
        
        function addJobBlockEventListeners(jobBlock) {
            const qtyInput = jobBlock.querySelector('.job-qty');
            const unitPriceInput = jobBlock.querySelector('.job-unit-price');
            const amountInput = jobBlock.querySelector('.job-amount');
            
            function calculateAmount() {
                const qty = parseFloat(qtyInput.value) || 0;
                const unitPrice = parseFloat(unitPriceInput.value) || 0;
                const amount = qty * unitPrice;
                amountInput.value = amount.toFixed(2);
                updateTotalAmount();
            }
            
            qtyInput.addEventListener('input', calculateAmount);
            unitPriceInput.addEventListener('input', calculateAmount);
            
            // Remove job block
            jobBlock.querySelector('.remove-job-btn').addEventListener('click', function() {
                jobBlock.remove();
                updateTotalAmount();
            });
        }
        
        // Initialize event listeners for existing job blocks
        document.querySelectorAll('.job-description-block').forEach(block => {
            const qtyInput = block.querySelector('.job-qty');
            const unitPriceInput = block.querySelector('.job-unit-price');
            const amountInput = block.querySelector('.job-amount');
            
            function calculateAmount() {
                const qty = parseFloat(qtyInput.value) || 0;
                const unitPrice = parseFloat(unitPriceInput.value) || 0;
                const amount = qty * unitPrice;
                amountInput.value = amount.toFixed(2);
                updateTotalAmount();
            }
            
            qtyInput.addEventListener('input', calculateAmount);
            unitPriceInput.addEventListener('input', calculateAmount);
            
            // Remove job block
            block.querySelector('.remove-job-btn').addEventListener('click', function() {
                block.remove();
                updateTotalAmount();
            });
            
            // Initialize quantity validation for parts
            if (block.querySelector('.job-type').value === 'part') {
                validateQuantity(qtyInput);
            }
        });
        
        // Calculate total amount
        function updateTotalAmount() {
            const amounts = Array.from(document.querySelectorAll('.job-amount'))
                .map(input => parseFloat(input.value) || 0);
            const total = amounts.reduce((sum, amount) => sum + amount, 0);
            document.getElementById('totalAmountDisplay').textContent = `₱${total.toFixed(2)}`;
        }
    </script>
</body>
</html>