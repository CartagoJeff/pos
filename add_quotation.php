<?php
require_once 'config.php';
redirectIfNotAuthenticated();

// Get inventory parts for dropdown
$parts = [];
$result = $conn->query("SELECT id, part_name, quantity, unit_price FROM inventory WHERE quantity > 0 ORDER BY part_name");
if ($result) {
    $parts = $result->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_quotation'])) {
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
    $balance = floatval($_POST['balance']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert quotation
        $stmt = $conn->prepare("INSERT INTO quotations (customer_name, address, date, plate_number, maker, model, 
                              vehicle_color, time_in, motor_chasis, fuel_level, mileage, balance, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssssidsi", $customerName, $address, $date, $plateNumber, $maker, $model, 
                         $vehicleColor, $timeIn, $motorChasis, $fuelLevel, $mileage, $balance, $_SESSION['user_id']);
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
        
        // Insert job descriptions and process inventory
        if (isset($_POST['job_type'])) {
            $totalAmount = 0;
            
            foreach ($_POST['job_type'] as $index => $type) {
                $description = $conn->real_escape_string($_POST['job_description'][$index]);
                $quantity = intval($_POST['job_quantity'][$index]);
                $unit = $conn->real_escape_string($_POST['job_unit'][$index]);
                $unitPrice = floatval($_POST['job_unit_price'][$index]);
                $amount = $quantity * $unitPrice;
                $totalAmount += $amount;
                
                $stmt = $conn->prepare("INSERT INTO job_descriptions (job_order_id, type, description, quantity, unit, unit_price) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issisd", $jobOrderId, $type, $description, $quantity, $unit, $unitPrice);
                $stmt->execute();
                $stmt->close();
                
                // Reduce inventory for parts
                if ($type === 'part' && isset($_POST['part_id'][$index])) {
                    $partId = intval($_POST['part_id'][$index]);
                    $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                    $stmt->bind_param("iii", $quantity, $partId, $quantity);
                    $stmt->execute();
                    
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("Insufficient quantity for part: $description");
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
        
        $conn->commit();
        $_SESSION['success'] = 'Quotation added successfully!';
        header("Location: quotations.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        header("Location: add_quotation.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Quotation - Auto Repair Shop</title>
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
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <h1>Add New Quotation</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="quotation-form">
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="customerName">Customer Name</label>
                        <input type="text" id="customerName" name="customer_name" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="plateNumber">Plate Number</label>
                        <input type="text" id="plateNumber" name="plate_number" required>
                    </div>
                    <div class="form-group">
                        <label for="maker">Maker</label>
                        <input type="text" id="maker" name="maker" required>
                    </div>
                    <div class="form-group">
                        <label for="model">Model</label>
                        <input type="text" id="model" name="model" required>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="vehicleColor">Vehicle Color</label>
                        <input type="text" id="vehicleColor" name="vehicle_color">
                    </div>
                    <div class="form-group">
                        <label for="timeIn">Time In</label>
                        <input type="time" id="timeIn" name="time_in" value="<?php echo date('H:i'); ?>">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="motorChasis">Motor/Chasis No.</label>
                        <input type="text" id="motorChasis" name="motor_chasis">
                    </div>
                    <div class="form-group">
                        <label for="fuelLevel">Fuel Level</label>
                        <input type="text" id="fuelLevel" name="fuel_level">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="mileage">Mileage</label>
                        <input type="number" id="mileage" name="mileage">
                    </div>
                </div>

                <div class="form-col">
        <div class="form-group">
            <label for="dateRelease">Date Release</label>
            <input type="date" id="dateRelease" name="date_release">
        </div>
    </div>
    
                <div class="form-col">
                    <div class="form-group">
                        <label for="balance">Balance</label>
                        <input type="number" id="balance" name="balance" step="0.01" value="0">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="mechanicName">Mechanic Name</label>
                <input type="text" id="mechanicName" name="mechanic_name">
            </div>
            
            <h3>Job Descriptions</h3>
            <div id="jobDescriptionsContainer">
                <!-- Job description blocks will be added here -->
            </div>
            <button type="button" class="add-job-btn" id="addJobBtn">
                <i class="fas fa-plus"></i> Add Job Description
            </button>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label>Total Amount: <span id="totalAmountDisplay">₱0.00</span></label>
                    </div>
                </div>
            </div>
            
            <div class="form-footer">
                <a href="quotations.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="add_quotation" class="btn btn-primary">Save</button>
            </div>
        </form>
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
                        <input type="number" class="job-qty" name="job_quantity[]" value="1" min="1" step="1" onchange="validateQuantity(this)">
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
                const quantity = parseInt(input.value) || 0;
                
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
                const qty = parseInt(qtyInput.value) || 0;
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