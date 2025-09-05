<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle date filter
$date_filter = '';
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$custom_date = isset($_GET['date']) ? $_GET['date'] : '';

switch ($filter_type) {
    case 'today':
        $date_filter = "AND DATE(a.date) = CURDATE()";
        break;
    case 'week':
        $date_filter = "AND YEARWEEK(a.date, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $date_filter = "AND YEAR(a.date) = YEAR(CURDATE()) AND MONTH(a.date) = MONTH(CURDATE())";
        break;
    case 'custom':
        if (!empty($custom_date)) {
            $date_filter = "AND DATE(a.date) = '" . $custom_date . "'";
        }
        break;
}

// Get appointments with patient and hospital details
$query = "SELECT a.*, 
                 u.name as patient_name, u.email as patient_email, p.phone as patient_phone,
                 h.name as hospital_name, h.address as hospital_address, h.contact as hospital_contact
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          JOIN users u ON p.user_id = u.id 
          JOIN hospitals h ON a.hospital_id = h.id 
          WHERE 1=1 $date_filter
          ORDER BY a.date DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN type = 'test' THEN 1 ELSE 0 END) as tests,
    SUM(CASE WHEN type = 'vaccination' THEN 1 ELSE 0 END) as vaccinations
FROM appointments a WHERE 1=1 $date_filter";

$stmt = $db->prepare($stats_query);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Appointments - COVID-19 Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #667eea; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.info { border-left-color: #17a2b8; }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
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
                <a class="nav-link active" href="appointments.php">
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
                <h2><i class="fas fa-calendar-alt me-2"></i>View Appointments</h2>
                <p class="text-muted">Manage and view all appointments with filtering options</p>
            </div>
            <button class="btn btn-success" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-2"></i>Export to Excel
            </button>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <h5><i class="fas fa-filter me-2"></i>Filter Appointments</h5>
            <div class="row">
                <div class="col-md-8">
                    <div class="btn-group" role="group">
                        <a href="appointments.php?filter=all" 
                           class="btn btn-<?php echo $filter_type == 'all' ? 'primary' : 'outline-primary'; ?>">
                            All Time
                        </a>
                        <a href="appointments.php?filter=today" 
                           class="btn btn-<?php echo $filter_type == 'today' ? 'primary' : 'outline-primary'; ?>">
                            Today
                        </a>
                        <a href="appointments.php?filter=week" 
                           class="btn btn-<?php echo $filter_type == 'week' ? 'primary' : 'outline-primary'; ?>">
                            This Week
                        </a>
                        <a href="appointments.php?filter=month" 
                           class="btn btn-<?php echo $filter_type == 'month' ? 'primary' : 'outline-primary'; ?>">
                            This Month
                        </a>
                    </div>
                </div>
                <div class="col-md-4">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="filter" value="custom">
                        <input type="date" name="date" class="form-control me-2" 
                               value="<?php echo $custom_date; ?>" required>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stat-card primary">
                    <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                    <p class="mb-0 small">Total</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card warning">
                    <h4 class="text-warning"><?php echo $stats['pending']; ?></h4>
                    <p class="mb-0 small">Pending</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card success">
                    <h4 class="text-success"><?php echo $stats['approved']; ?></h4>
                    <p class="mb-0 small">Approved</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card danger">
                    <h4 class="text-danger"><?php echo $stats['rejected']; ?></h4>
                    <p class="mb-0 small">Rejected</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card info">
                    <h4 class="text-info"><?php echo $stats['tests']; ?></h4>
                    <p class="mb-0 small">Tests</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card success">
                    <h4 class="text-success"><?php echo $stats['vaccinations']; ?></h4>
                    <p class="mb-0 small">Vaccinations</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Appointments List 
                    <?php if ($filter_type != 'all'): ?>
                        <span class="badge bg-primary"><?php echo ucfirst($filter_type); ?> Filter Active</span>
                    <?php endif; ?>
                </h5>
                <small class="text-muted">Showing <?php echo count($appointments); ?> appointments</small>
            </div>
            <div class="card-body">
                <?php if (count($appointments) > 0): ?>
                    <div class="table-responsive">
                        <table id="appointmentsTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Hospital</th>
                                    <th>Type</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Contact</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?php echo $appointment['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['patient_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['patient_email']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($appointment['hospital_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($appointment['hospital_address']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $appointment['type'] == 'test' ? 'info' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $appointment['type'] == 'test' ? 'vial' : 'syringe'; ?> me-1"></i>
                                                <?php echo ucfirst($appointment['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($appointment['date'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('H:i A', strtotime($appointment['date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $appointment['status'] == 'approved' ? 'success' : 
                                                    ($appointment['status'] == 'rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($appointment['patient_phone']); ?><br>
                                            <i class="fas fa-hospital me-1"></i><?php echo htmlspecialchars($appointment['hospital_contact']); ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($appointment['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No appointments found</h5>
                        <p class="text-muted">
                            <?php if ($filter_type != 'all'): ?>
                                No appointments found for the selected filter. <a href="appointments.php">View all appointments</a>
                            <?php else: ?>
                                No appointments have been scheduled yet.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#appointmentsTable').DataTable({
                "pageLength": 25,
                "order": [[ 4, "desc" ]],
                "language": {
                    "search": "Search appointments:",
                    "lengthMenu": "Show _MENU_ appointments per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ appointments"
                },
                "columnDefs": [
                    { "orderable": false, "targets": [6] }
                ]
            });
        });

        function exportToExcel() {
            // Get table data
            const table = document.getElementById('appointmentsTable');
            const wb = XLSX.utils.table_to_book(table, {sheet: "Appointments"});
            
            // Generate filename with current date and filter
            const now = new Date();
            const dateStr = now.toISOString().split('T')[0];
            const filterType = '<?php echo $filter_type; ?>';
            const filename = `appointments_${filterType}_${dateStr}.xlsx`;
            
            // Save file
            XLSX.writeFile(wb, filename);
        }
    </script>
</body>
</html>
