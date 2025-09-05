<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle hospital approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && isset($_POST['hospital_id'])) {
        $hospital_id = $_POST['hospital_id'];
        $action = $_POST['action'];
        
        if ($action == 'approve' || $action == 'reject') {
            $status = ($action == 'approve') ? 'approved' : 'rejected';
            
            $query = "UPDATE hospitals SET status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $status);
            $stmt->bindParam(2, $hospital_id);
            
            if ($stmt->execute()) {
                $message = "Hospital " . ucfirst($action) . "d successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating hospital status.";
                $message_type = 'danger';
            }
        }
    }
}

// Get all hospitals
$query = "SELECT * FROM hospitals ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hospitals - COVID-19 Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content { margin-left: 250px; padding: 20px; }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .sidebar { position: fixed; z-index: 1000; width: 250px; transform: translateX(-100%); }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar position-fixed">
        <div class="p-4">
            <h4><i class="fas fa-virus me-2"></i>COVID-19 Admin</h4>
            <hr class="text-white-50">
        </div>
        <ul class="nav flex-column px-3">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="hospitals.php">
                    <i class="fas fa-hospital me-2"></i>Manage Hospitals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="vaccines.php">
                    <i class="fas fa-syringe me-2"></i>Manage Vaccines
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="patients.php">
                    <i class="fas fa-users me-2"></i>View Patients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="appointments.php">
                    <i class="fas fa-calendar-alt me-2"></i>View Appointments
                </a>
            </li>
            <hr class="text-white-50">
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-hospital me-2"></i>Manage Hospitals</h2>
                <p class="text-muted">Approve or reject hospital registrations</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Hospital List</h5>
            </div>
            <div class="card-body">
                <?php if (count($hospitals) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Hospital Name</th>
                                    <th>Address</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <tr>
                                        <td><?php echo $hospital['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($hospital['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($hospital['address']); ?></td>
                                        <td>
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($hospital['contact']); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $hospital['status']; ?>">
                                                <?php echo ucfirst($hospital['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($hospital['created_at'])); ?></td>
                                        <td>
                                            <?php if ($hospital['status'] == 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                                                    <button type="submit" name="action" value="approve" 
                                                            class="btn btn-success btn-sm me-1"
                                                            onclick="return confirm('Approve this hospital?')">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="hospital_id" value="<?php echo $hospital['id']; ?>">
                                                    <button type="submit" name="action" value="reject" 
                                                            class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Reject this hospital?')">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-<?php echo $hospital['status'] == 'approved' ? 'check' : 'times'; ?>"></i>
                                                    <?php echo ucfirst($hospital['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-hospital fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No hospitals found</h5>
                        <p class="text-muted">No hospitals have registered yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
