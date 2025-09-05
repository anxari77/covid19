<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';

// Handle vaccine operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $name = trim($_POST['name']);
            $manufacturer = trim($_POST['manufacturer']);
            $description = trim($_POST['description']);
            $status = $_POST['status'];
            
            if (!empty($name) && !empty($manufacturer)) {
                $query = "INSERT INTO vaccines (name, manufacturer, description, status) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(1, $name);
                $stmt->bindParam(2, $manufacturer);
                $stmt->bindParam(3, $description);
                $stmt->bindParam(4, $status);
                
                if ($stmt->execute()) {
                    $message = "Vaccine added successfully!";
                    $message_type = 'success';
                } else {
                    $message = "Error adding vaccine.";
                    $message_type = 'danger';
                }
            } else {
                $message = "Please fill in all required fields.";
                $message_type = 'danger';
            }
        } elseif ($action == 'update_status') {
            $vaccine_id = $_POST['vaccine_id'];
            $status = $_POST['status'];
            
            $query = "UPDATE vaccines SET status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $status);
            $stmt->bindParam(2, $vaccine_id);
            
            if ($stmt->execute()) {
                $message = "Vaccine status updated successfully!";
                $message_type = 'success';
            } else {
                $message = "Error updating vaccine status.";
                $message_type = 'danger';
            }
        }
    }
}

// Get all vaccines
$query = "SELECT * FROM vaccines ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vaccines - COVID-19 Management System</title>
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
        .status-available { background-color: #d4edda; color: #155724; }
        .status-unavailable { background-color: #f8d7da; color: #721c24; }
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
                <a class="nav-link" href="hospitals.php">
                    <i class="fas fa-hospital me-2"></i>Manage Hospitals
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="vaccines.php">
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
                <h2><i class="fas fa-syringe me-2"></i>Manage Vaccines</h2>
                <p class="text-muted">Add new vaccines and manage their availability</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVaccineModal">
                <i class="fas fa-plus me-2"></i>Add New Vaccine
            </button>
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
                <h5 class="mb-0">Vaccine List</h5>
            </div>
            <div class="card-body">
                <?php if (count($vaccines) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vaccine Name</th>
                                    <th>Manufacturer</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vaccines as $vaccine): ?>
                                    <tr>
                                        <td><?php echo $vaccine['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vaccine['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($vaccine['manufacturer']); ?></td>
                                        <td><?php echo htmlspecialchars($vaccine['description']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $vaccine['status']; ?>">
                                                <?php echo ucfirst($vaccine['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($vaccine['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="vaccine_id" value="<?php echo $vaccine['id']; ?>">
                                                <input type="hidden" name="action" value="update_status">
                                                <select name="status" class="form-select form-select-sm d-inline-block w-auto me-2" 
                                                        onchange="this.form.submit()">
                                                    <option value="available" <?php echo $vaccine['status'] == 'available' ? 'selected' : ''; ?>>
                                                        Available
                                                    </option>
                                                    <option value="unavailable" <?php echo $vaccine['status'] == 'unavailable' ? 'selected' : ''; ?>>
                                                        Unavailable
                                                    </option>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-syringe fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No vaccines found</h5>
                        <p class="text-muted">No vaccines have been added yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Vaccine Modal -->
    <div class="modal fade" id="addVaccineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Vaccine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Vaccine Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="manufacturer" class="form-label">Manufacturer *</label>
                            <input type="text" class="form-control" id="manufacturer" name="manufacturer" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="available">Available</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Vaccine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
