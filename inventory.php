<?php
require_once 'config.php';
redirectIfNotAuthenticated();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $partName = $conn->real_escape_string(trim($_POST['part_name']));
        $unitPrice = floatval($_POST['unit_price']);
        $quantity = intval($_POST['quantity']);
        $minStock = intval($_POST['min_stock']);
        
        // Validate inputs
        if (empty($partName)) {
            $_SESSION['error'] = 'Part name is required';
        } elseif ($unitPrice <= 0) {
            $_SESSION['error'] = 'Unit price must be positive';
        } elseif ($quantity < 0) {
            $_SESSION['error'] = 'Quantity cannot be negative';
        } else {
            $stmt = $conn->prepare("INSERT INTO inventory (part_name, unit_price, quantity, min_stock_level) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdii", $partName, $unitPrice, $quantity, $minStock);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Inventory item added successfully!';
            } else {
                $_SESSION['error'] = 'Failed to add inventory item: ' . $conn->error;
            }
            $stmt->close();
        }
        
        header("Location: inventory.php");
        exit();
        
    } elseif (isset($_POST['update_item'])) {
        $id = intval($_POST['id']);
        $partName = $conn->real_escape_string(trim($_POST['part_name']));
        $unitPrice = floatval($_POST['unit_price']);
        $quantity = intval($_POST['quantity']);
        $minStock = intval($_POST['min_stock']);
        
        // Validate inputs
        if (empty($partName)) {
            $_SESSION['error'] = 'Part name is required';
        } elseif ($unitPrice <= 0) {
            $_SESSION['error'] = 'Unit price must be positive';
        } elseif ($quantity < 0) {
            $_SESSION['error'] = 'Quantity cannot be negative';
        } else {
            $stmt = $conn->prepare("UPDATE inventory SET part_name = ?, unit_price = ?, quantity = ?, min_stock_level = ? WHERE id = ?");
            $stmt->bind_param("sdiii", $partName, $unitPrice, $quantity, $minStock, $id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Inventory item updated successfully!';
            } else {
                $_SESSION['error'] = 'Failed to update inventory item: ' . $conn->error;
            }
            $stmt->close();
        }
        
        header("Location: inventory.php");
        exit();
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Inventory item deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete inventory item: ' . $conn->error;
    }
    
    $stmt->close();
    header("Location: inventory.php");
    exit();
}

// Get all inventory items
$inventory = [];
$stmt = $conn->prepare("SELECT * FROM inventory ORDER BY part_name");
$stmt->execute();
$result = $stmt->get_result();
$inventory = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Auto Repair Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- <link rel="stylesheet" href="css/inventory.css"> -->
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
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .action-bar {
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
        }
        .out-of-stock {
            background-color: #f8d7da;
        }
        .low-stock {
            background-color: #fff3cd;
        }
        .action-btn {
            padding: 6px 10px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .edit-btn {
            background-color: #17a2b8;
        }
        .delete-btn {
            background-color: #dc3545;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-col {
            flex: 1;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
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
        <h1>Inventory</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="action-bar">
            <div class="action-buttons">
                <button class="btn btn-primary" id="addItemBtn">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                <button class="btn btn-warning" id="lowStockBtn">
                    <i class="fas fa-exclamation-triangle"></i> Low Stock
                </button>
            </div>
        </div>
        
        <table id="inventoryTable">
            <thead>
                <tr>
                    <th>Part Name</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory as $item): 
                    $status = "In Stock";
                    $rowClass = "";
                    
                    if ($item['quantity'] == 0) {
                        $status = "Out of Stock";
                        $rowClass = "out-of-stock";
                    } elseif ($item['quantity'] <= $item['min_stock_level']) {
                        $status = "Low Stock";
                        $rowClass = "low-stock";
                    }
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                    <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo $status; ?></td>
                    <td>
                        <button class="action-btn edit-btn" title="Edit" onclick="editItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['part_name']); ?>', <?php echo $item['unit_price']; ?>, <?php echo $item['quantity']; ?>, <?php echo $item['min_stock_level']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="inventory.php?delete=<?php echo $item['id']; ?>" class="action-btn delete-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this item?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add/Edit Item Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add Inventory Item</h2>
            
            <form method="POST" action="inventory.php" id="itemForm">
                <input type="hidden" id="itemId" name="id">
                
                <div class="form-group">
                    <label for="partName">Part Name</label>
                    <input type="text" id="partName" name="part_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="unitPrice">Unit Price</label>
                            <input type="number" id="unitPrice" name="unit_price" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" id="quantity" name="quantity" min="0" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="minStock">Minimum Stock Level</label>
                    <input type="number" id="minStock" name="min_stock" min="0" value="5">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" name="add_item" class="btn btn-primary" id="submitBtn">Add Item</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Get modal elements
        const modal = document.getElementById('itemModal');
        const addBtn = document.getElementById('addItemBtn');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancelBtn');
        const form = document.getElementById('itemForm');
        
        // Show modal when add button is clicked
        addBtn.onclick = function() {
            document.getElementById('modalTitle').textContent = 'Add Inventory Item';
            form.reset();
            document.getElementById('itemId').value = '';
            
            // Set up form for adding
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.name = 'add_item';
            submitBtn.textContent = 'Add Item';
            
            modal.style.display = 'block';
        }
        
        // Close modal when close button is clicked
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        // Close modal when cancel button is clicked
        cancelBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside the modal
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Low stock button functionality
        document.getElementById('lowStockBtn').addEventListener('click', function() {
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            const showAll = this.textContent.includes('All Items');
            
            rows.forEach(row => {
                if (showAll || row.classList.contains('low-stock') || row.classList.contains('out-of-stock')) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Toggle button text
            this.innerHTML = showAll 
                ? '<i class="fas fa-exclamation-triangle"></i> Low Stock' 
                : '<i class="fas fa-list"></i> All Items';
        });
        
        // Edit item function
        function editItem(id, name, unitPrice, quantity, minStock) {
            document.getElementById('modalTitle').textContent = 'Edit Inventory Item';
            document.getElementById('itemId').value = id;
            document.getElementById('partName').value = name;
            document.getElementById('unitPrice').value = unitPrice;
            document.getElementById('quantity').value = quantity;
            document.getElementById('minStock').value = minStock;
            
            // Change the form to update mode
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.name = 'update_item';
            submitBtn.textContent = 'Update Item';
            
            modal.style.display = 'block';
        }
    </script>
</body>
</html>