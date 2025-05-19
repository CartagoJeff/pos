<?php
require_once 'config.php';
redirectIfNotAuthenticated();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_mechanic'])) {
        $name = $conn->real_escape_string($_POST['name']);
        
        // In our system, we just need to ensure the mechanic name exists in job orders
        $_SESSION['success'] = 'Mechanic added to system';
        header("Location: mechanics.php");
        exit();
    } elseif (isset($_POST['update_mechanic'])) {
        $oldName = $conn->real_escape_string($_POST['old_name']);
        $newName = $conn->real_escape_string($_POST['name']);
        
        // Update all job orders with the new mechanic name
        $stmt = $conn->prepare("UPDATE job_orders SET mechanic_name = ? WHERE mechanic_name = ?");
        $stmt->bind_param("ss", $newName, $oldName);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = 'Mechanic updated successfully!';
        header("Location: mechanics.php");
        exit();
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $name = $conn->real_escape_string($_GET['delete']);
    
    // Remove mechanic name from all job orders
    $stmt = $conn->prepare("UPDATE job_orders SET mechanic_name = NULL WHERE mechanic_name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['success'] = 'Mechanic removed successfully!';
    header("Location: mechanics.php");
    exit();
}

// Get all unique mechanics from job orders with their job counts
$mechanics = [];
$stmt = $conn->prepare("SELECT mechanic_name, COUNT(*) as job_count 
                       FROM job_orders 
                       WHERE mechanic_name IS NOT NULL AND mechanic_name != '' 
                       GROUP BY mechanic_name 
                       ORDER BY mechanic_name");
$stmt->execute();
$result = $stmt->get_result();
$mechanics = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mechanics - Auto Repair Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/mechanics.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="main-content">
        <h1>Mechanics</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <!-- <div class="action-bar">
            <button class="btn btn-primary" id="addMechanicBtn">
                <i class="fas fa-plus"></i> Add Mechanic
            </button>
        </div> -->
        
        <table id="mechanicsTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Assigned Jobs</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mechanics as $mechanic): ?>
                <tr>
                    <td><?php echo htmlspecialchars($mechanic['mechanic_name']); ?></td>
                    <td><?php echo $mechanic['job_count']; ?></td>
                    <td>
                        <a href="mechanic_jobs.php?name=<?php echo urlencode($mechanic['mechanic_name']); ?>" class="action-btn view-btn" title="View Jobs">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="action-btn edit-btn" title="Edit" onclick="editMechanic('<?php echo htmlspecialchars($mechanic['mechanic_name']); ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="mechanics.php?delete=<?php echo urlencode($mechanic['mechanic_name']); ?>" class="action-btn delete-btn" title="Delete" onclick="return confirm('Are you sure? This will remove this mechanic from all job orders.')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Add/Edit Mechanic Modal -->
    <div id="mechanicModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">Add Mechanic</h2>
            
            <form method="POST" id="mechanicForm">
                <input type="hidden" id="updateFlag" name="update_mechanic" value="0">
                <input type="hidden" id="oldName" name="old_name" value="">
                
                <div class="form-group">
                    <label for="mechanicName">Name</label>
                    <input type="text" id="mechanicName" name="name" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Get modal elements
        const modal = document.getElementById('mechanicModal');
        const addBtn = document.getElementById('addMechanicBtn');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancelBtn');
        const form = document.getElementById('mechanicForm');
        const modalTitle = document.getElementById('modalTitle');
        const updateFlag = document.getElementById('updateFlag');
        const oldName = document.getElementById('oldName');
        const mechanicName = document.getElementById('mechanicName');
        
        // Show modal when add button is clicked
        addBtn.onclick = function() {
            modalTitle.textContent = 'Add Mechanic';
            form.reset();
            updateFlag.value = '0';
            oldName.value = '';
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
        
        // Edit mechanic function
        function editMechanic(name) {
            modalTitle.textContent = 'Edit Mechanic';
            mechanicName.value = name;
            oldName.value = name;
            updateFlag.value = '1';
            modal.style.display = 'block';
        }
    </script>
</body>
</html>