<?php
require_once 'config.php';
redirectIfNotAuthenticated();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: quotations.php");
    exit();
}

$quotationId = intval($_GET['id']);

// Check if quotation exists
$stmt = $conn->prepare("SELECT id FROM quotations WHERE id = ?");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: quotations.php");
    exit();
}

$stmt->close();

// Delete job descriptions (via job order)
$stmt = $conn->prepare("DELETE jd FROM job_descriptions jd 
                       JOIN job_orders jo ON jd.job_order_id = jo.id 
                       WHERE jo.quotation_id = ?");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$stmt->close();

// Delete job order
$stmt = $conn->prepare("DELETE FROM job_orders WHERE quotation_id = ?");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$stmt->close();

// Delete quotation
$stmt = $conn->prepare("DELETE FROM quotations WHERE id = ?");
$stmt->bind_param("i", $quotationId);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = 'Quotation deleted successfully!';
header("Location: quotations.php");
exit();
?>