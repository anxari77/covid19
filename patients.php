<?php
require_once 'config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get all patients with user information
$query = "SELECT p.*, u.name, u.email, u.created_at as user_created_at 
          FROM patients p 
          JOIN users u ON p.user_id = u.id 
          ORDER BY p.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patient statistics
$query = "SELECT COUNT(*) as total_patients FROM patients";
$stmt = $db->prepare($query);
$stmt->execute();
$total_patients = $stmt->fetch(PDO::FETCH_ASSOC)['total_patients'];

$query = "SELECT COUNT(*) as total_appointments FROM appointments";
$stmt = $db->prepare($query);
$stmt->execute();
$total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['total_appointments'];

$query = "SELECT COUNT(*) as total_vaccinations FROM vaccinations WHERE status = 'done'";
$stmt = $db->prepare($query);
$stmt->execute();
$total_vaccinations = $stmt->fetch(PDO::FETCH_ASSOC)['total_vaccinations'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patients - COVID-19 Management System</title>
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
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #667eea; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.info { border-left-color: #17a2b8; }
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
                <a class="nav-link active" href="patients.php">
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
                <h2><i class="fas fa-users me-2"></i>View Patients</h2>
                <p class="text-muted">Manage and view all registered patients</p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card primary">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-primary"><?php echo $total_patients; ?></h3>
                            <p class="mb-0">Total Patients</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-info"><?php echo $total_appointments; ?></h3>
                            <p class="mb-0">Total Appointments</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-calendar-alt fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h3 class="text-success"><?php echo $total_vaccinations; ?></h3>
                            <p class="mb-0">Vaccinations Done</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-syringe fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Patient List</h5>
            </div>
            <div class="card-body">
                <?php if (count($patients) > 0): ?>
                    <div class="table-responsive">
                        <table id="patientsTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Date of Birth</th>
                                    <th>Address</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td><?php echo $patient['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($patient['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['email']); ?></td>
                                        <td>
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($patient['phone']); ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($patient['dob'])); ?></td>
                                        <td><?php echo htmlspecialchars($patient['address']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-sm" 
                                                    onclick="viewPatientDetails(<?php echo $patient['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No patients found</h5>
                        <p class="text-muted">No patients have registered yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <div class="modal fade" id="patientDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Patient Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="patientDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#patientsTable').DataTable({
                "pageLength": 25,
                "order": [[ 6, "desc" ]],
                "language": {
                    "search": "Search patients:",
                    "lengthMenu": "Show _MENU_ patients per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ patients"
                }
            });
        });

        function viewPatientDetails(patientId) {
            // Load patient details via AJAX
            fetch('patient_details.php?id=' + patientId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('patientDetailsContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('patientDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading patient details');
                });
        }
    </script>
</body>
</html>
