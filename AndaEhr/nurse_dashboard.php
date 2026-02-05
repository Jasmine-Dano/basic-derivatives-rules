<?php
require_once 'common_functions.php';

// Check if user is nurse
$user = checkRoleAccess(['nurse']);
$user_id = $user['user_id'];
$user_role = $user['user_role'];
$user_name = $user['user_name'];

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'update_vitals') {
            $consultation_id = $_POST['consultation_id'];
            
            // Prepare data for update
            $data = [
                ':temperature' => !empty($_POST['temperature']) ? $_POST['temperature'] : null,
                ':pulse' => !empty($_POST['pulse']) ? $_POST['pulse'] : null,
                ':respiratory_rate' => !empty($_POST['respiratory_rate']) ? $_POST['respiratory_rate'] : null,
                ':blood_pressure' => !empty($_POST['blood_pressure']) ? $_POST['blood_pressure'] : null,
                ':oxygen_saturation' => !empty($_POST['oxygen_saturation']) ? $_POST['oxygen_saturation'] : null,
                ':weight' => !empty($_POST['weight']) ? $_POST['weight'] : null,
                ':height' => !empty($_POST['height']) ? $_POST['height'] : null,
                ':bmi' => null,
                ':nurse_notes' => !empty($_POST['nurse_notes']) ? $_POST['nurse_notes'] : null
            ];
            
            // Calculate BMI if weight and height provided
            if ($data[':weight'] && $data[':height'] && $data[':height'] > 0) {
                $height_m = $data[':height'] / 100;
                $data[':bmi'] = round($data[':weight'] / ($height_m * $height_m), 2);
            }
            
            if (updateVitalSigns($consultation_id, $data, $user_id)) {
                $success = "Vital signs recorded successfully!";
            } else {
                $error = "Failed to record vital signs. Please try again.";
            }
        }
    }
}

// Get data
$pending_vitals = getPendingVitals();
$my_vitals = getConsultationsByRole($user_id, $user_role);
$stats = getDashboardStats($user_role, $user_id);

// Get AJAX requests
if (isset($_GET['get_consultation'])) {
    header('Content-Type: application/json');
    $consultation_id = $_GET['consultation_id'];
    $pdo = getDBConnection();
    
    try {
        $stmt = $pdo->prepare("SELECT c.*, p.full_name, p.patient_id as patient_uid 
                              FROM consultations c 
                              JOIN patients p ON c.patient_id = p.patient_id 
                              WHERE c.id = :consultation_id");
        $stmt->execute([':consultation_id' => $consultation_id]);
        $consultation = $stmt->fetch();
        
        echo json_encode(['success' => true, 'consultation' => $consultation]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading consultation']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Dashboard - Anda EHR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-color: #9b59b6;
            --secondary-color: #34495e;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
            --sidebar-width: 250px;
        }
        
        body {
            background: #f5f7fb;
            color: #333;
        }
        
        /* Dashboard Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 15px;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .user-role {
            background: var(--light-bg);
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
        }
        
        .sidebar-nav {
            flex: 1;
            padding: 20px 0;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .nav-item:hover {
            background: var(--light-bg);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        
        .nav-item.active {
            background: #f3e6f8;
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 500;
        }
        
        .logout-btn {
            margin: 20px;
            padding: 12px 20px;
            background: var(--light-bg);
            color: var(--danger-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: var(--danger-color);
            color: white;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .header h1 {
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #8e44ad;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.pending {
            background: var(--warning-color);
        }
        
        .stat-icon.vitals {
            background: var(--primary-color);
        }
        
        .stat-icon.emergency {
            background: var(--danger-color);
        }
        
        .stat-icon.patients {
            background: #3498db;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Section Cards */
        .section-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: var(--secondary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Tables */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: var(--light-bg);
            color: var(--secondary-color);
            font-weight: 600;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: var(--light-bg);
        }
        
        /* Badges */
        .priority-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .priority-high {
            background: #fde8e8;
            color: #e74c3c;
        }
        
        .priority-medium {
            background: #fff4e6;
            color: #f39c12;
        }
        
        .priority-low {
            background: #e8f5e9;
            color: #27ae60;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-pending {
            background: #fff4e6;
            color: #f39c12;
        }
        
        .status-vitals {
            background: #e8d4f5;
            color: #8e44ad;
        }
        
        /* Action Buttons */
        .action-btn {
            padding: 8px 15px;
            font-size: 0.9rem;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #999;
        }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #666;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .patient-info {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Form Styles */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .vital-item {
            margin-bottom: 15px;
        }
        
        .vital-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
        }
        
        .input-field {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .vital-value {
            padding: 10px;
            background: var(--light-bg);
            border-radius: 6px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .textarea-field {
            width: 100%;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            resize: vertical;
        }
        
        .textarea-field:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2,
            .user-info > div:not(.user-avatar),
            .nav-item span:not(.fas),
            .logout-btn span {
                display: none;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .nav-item {
                justify-content: center;
                padding: 15px;
            }
            
            .logout-btn {
                justify-content: center;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .modal {
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-heartbeat"></i> Anda EHR</h2>
            </div>
            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role">Nurse</div>
            </div>
            
            <div class="sidebar-nav">
                <a href="#" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-heartbeat"></i>
                    <span>Pending Vitals</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>My Records</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-user-injured"></i>
                    <span>Patients</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-ambulance"></i>
                    <span>Emergency</span>
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            
            <button class="logout-btn" onclick="window.location.href='?logout=true'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-user-nurse"></i> Nurse Dashboard</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="window.location.href='?page=emergency'">
                        <i class="fas fa-ambulance"></i>
                        Emergency Triage
                    </button>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['pending_vitals'] ?? 0; ?></div>
                        <div class="stat-label">Pending Vitals</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon vitals">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['vitals_recorded'] ?? 0; ?></div>
                        <div class="stat-label">Vitals Recorded</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon emergency">
                        <i class="fas fa-ambulance"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['emergency_cases'] ?? 0; ?></div>
                        <div class="stat-label">Emergency Cases</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon patients">
                        <i class="fas fa-user-injured"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $stats['total_patients'] ?? 0; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Vitals Section -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-heartbeat"></i>
                    Pending Vital Signs
                </h2>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Consultation Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($pending_vitals)): ?>
                                <?php foreach ($pending_vitals as $consultation): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($consultation['full_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($consultation['patient_uid']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y h:i A', strtotime($consultation['consultation_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo strtolower($consultation['priority']); ?>">
                                                <?php echo ucfirst($consultation['priority']); ?>
                                            </span>
                                            <?php if ($consultation['is_emergency']): ?>
                                                <span class="priority-badge" style="background: #fde8e8; color: #e74c3c; margin-left: 5px;">
                                                    <i class="fas fa-exclamation-triangle"></i> Emergency
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-clock"></i> Awaiting Vitals
                                            </span>
                                        </td>
                                        <td>
                                            <button class="action-btn btn-primary" onclick="openVitalsModal(<?php echo $consultation['id']; ?>)">
                                                <i class="fas fa-edit"></i> Record Vitals
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-check-circle"></i>
                                            <h3>No Pending Vitals</h3>
                                            <p>All vital signs have been recorded.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- My Recent Records -->
            <div class="section-card">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    My Recent Vital Sign Records
                </h2>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Date Recorded</th>
                                <th>Temperature</th>
                                <th>BP</th>
                                <th>Pulse</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($my_vitals)): ?>
                                <?php 
                                $recent_vitals = array_slice($my_vitals, 0, 5);
                                foreach ($recent_vitals as $consultation): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($consultation['full_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($consultation['patient_uid']); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($consultation['vital_signs_recorded_at']) {
                                                echo date('M d, Y h:i A', strtotime($consultation['vital_signs_recorded_at']));
                                            } else {
                                                echo 'Not recorded';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo $consultation['temperature'] ? $consultation['temperature'] . '°C' : '--'; ?>
                                        </td>
                                        <td>
                                            <?php echo $consultation['blood_pressure'] ?: '--'; ?>
                                        </td>
                                        <td>
                                            <?php echo $consultation['pulse'] ? $consultation['pulse'] . ' bpm' : '--'; ?>
                                        </td>
                                        <td>
                                            <?php if ($consultation['workflow_status'] == 'vitals_completed'): ?>
                                                <span class="status-badge status-vitals">
                                                    <i class="fas fa-check"></i> Ready for Doctor
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending">
                                                    <i class="fas fa-clock"></i> In Progress
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="action-btn btn-primary" onclick="viewVitals(<?php echo $consultation['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-heartbeat"></i>
                                            <h3>No Records Yet</h3>
                                            <p>You haven't recorded any vital signs yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Vitals Modal -->
    <div class="modal-overlay" id="vitalsModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-heartbeat"></i>
                    Record Vital Signs
                </h2>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="patient-info">
                    <h3 id="modalPatientName">Loading...</h3>
                    <p id="modalPatientInfo">Loading patient information...</p>
                </div>
                
                <form id="vitalsForm" method="POST">
                    <input type="hidden" name="action" value="update_vitals">
                    <input type="hidden" name="consultation_id" id="consultationId">
                    
                    <div class="vitals-grid">
                        <div class="vital-item">
                            <label class="vital-label">Temperature (°C)</label>
                            <input type="number" class="input-field" name="temperature" 
                                   step="0.1" min="30" max="45" placeholder="36.5">
                        </div>
                        
                        <div class="vital-item">
                            <label class="vital-label">Blood Pressure</label>
                            <input type="text" class="input-field" name="blood_pressure" 
                                   placeholder="120/80" pattern="\d{2,3}/\d{2,3}">
                        </div>
                        
                        <div class="vital-item">
                            <label class="vital-label">Pulse (bpm)</label>
                            <input type="number" class="input-field" name="pulse" 
                                   min="30" max="200" placeholder="72">
                        </div>
                        
                        <div class="vital-item">
                            <label class="vital-label">Respiratory Rate</label>
                            <input type="number" class="input-field" name="respiratory_rate" 
                                   min="10" max="60" placeholder="16">
                        </div>
                        
                        <div class="vital-item">
                            <label class="vital-label">O₂ Saturation (%)</label>
                            <input type="number" class="input-field" name="oxygen_saturation" 
                                   min="70" max="100" placeholder="98">
                        </div>
                        
                        <div class="vital-item">
                            <label class="vital-label">Weight (kg)</label>
                            <input type="number" class="input-field" name="weight" 
                                   step="0.1" min="1" max="300" placeholder="65">
                        </div>
                        
                        <div class="vital-item">
                            <label class="vital-label">Height (cm)</label>
                            <input type="number" class="input-field" name="height" 
                                   step="0.1" min="30" max="250" placeholder="170">
                        </div>
                        
                        <div class="vital-item" id="bmiDisplay" style="display: none; grid-column: span 2;">
                            <label class="vital-label">BMI</label>
                            <div class="vital-value" id="bmiValue">--</div>
                            <div id="bmiCategory" style="font-size: 0.85rem; color: #666;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="nurse_notes">
                            <i class="fas fa-sticky-note"></i> Nurse's Notes
                        </label>
                        <textarea class="textarea-field" id="nurse_notes" name="nurse_notes" 
                                  placeholder="Enter observations, patient complaints, or other notes..." 
                                  rows="4"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" onclick="closeModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Vital Signs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // BMI calculation
        function calculateBMI() {
            const weight = parseFloat(document.querySelector('input[name="weight"]').value);
            const height = parseFloat(document.querySelector('input[name="height"]').value);
            
            if (weight && height && height > 0) {
                const heightM = height / 100;
                const bmi = weight / (heightM * heightM);
                const roundedBMI = bmi.toFixed(2);
                
                document.getElementById('bmiValue').textContent = roundedBMI;
                document.getElementById('bmiDisplay').style.display = 'block';
                
                // Determine category
                let category = '';
                let color = '';
                
                if (bmi < 18.5) {
                    category = 'Underweight';
                    color = '#3498db';
                } else if (bmi < 25) {
                    category = 'Normal weight';
                    color = '#27ae60';
                } else if (bmi < 30) {
                    category = 'Overweight';
                    color = '#f39c12';
                } else {
                    category = 'Obese';
                    color = '#e74c3c';
                }
                
                document.getElementById('bmiCategory').innerHTML = 
                    `<span style="color: ${color}">${category}</span>`;
            } else {
                document.getElementById('bmiDisplay').style.display = 'none';
            }
        }
        
        // Attach BMI calculation to weight and height inputs
        document.addEventListener('DOMContentLoaded', function() {
            const weightInput = document.querySelector('input[name="weight"]');
            const heightInput = document.querySelector('input[name="height"]');
            
            if (weightInput && heightInput) {
                weightInput.addEventListener('input', calculateBMI);
                heightInput.addEventListener('input', calculateBMI);
            }
        });
        
        // Modal functions
        function openVitalsModal(consultationId) {
            fetch(`?get_consultation=true&consultation_id=${consultationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const c = data.consultation;
                        
                        document.getElementById('consultationId').value = consultationId;
                        document.getElementById('modalPatientName').textContent = c.full_name;
                        document.getElementById('modalPatientInfo').innerHTML = `
                            Patient ID: ${c.patient_uid} | 
                            Consultation: ${new Date(c.consultation_date).toLocaleDateString()}
                        `;
                        
                        // Pre-fill existing values
                        if (c.temperature) document.querySelector('input[name="temperature"]').value = c.temperature;
                        if (c.blood_pressure) document.querySelector('input[name="blood_pressure"]').value = c.blood_pressure;
                        if (c.pulse) document.querySelector('input[name="pulse"]').value = c.pulse;
                        if (c.respiratory_rate) document.querySelector('input[name="respiratory_rate"]').value = c.respiratory_rate;
                        if (c.oxygen_saturation) document.querySelector('input[name="oxygen_saturation"]').value = c.oxygen_saturation;
                        if (c.weight) document.querySelector('input[name="weight"]').value = c.weight;
                        if (c.height) document.querySelector('input[name="height"]').value = c.height;
                        if (c.nurse_notes) document.getElementById('nurse_notes').value = c.nurse_notes;
                        
                        // Calculate BMI if values exist
                        calculateBMI();
                        
                        // Show modal
                        document.getElementById('vitalsModal').style.display = 'flex';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load consultation data');
                });
        }
        
        function viewVitals(consultationId) {
            openVitalsModal(consultationId);
            
            // Make all inputs read-only
            document.querySelectorAll('#vitalsForm input, #vitalsForm textarea').forEach(input => {
                input.setAttribute('readonly', true);
            });
            
            // Hide save button
            document.querySelector('button[type="submit"]').style.display = 'none';
        }
        
        function closeModal() {
            document.getElementById('vitalsModal').style.display = 'none';
            
            // Reset form
            document.getElementById('vitalsForm').reset();
            document.getElementById('bmiDisplay').style.display = 'none';
            
            // Remove readonly if viewing
            document.querySelectorAll('#vitalsForm input, #vitalsForm textarea').forEach(input => {
                input.removeAttribute('readonly');
            });
            
            // Show save button if hidden
            document.querySelector('button[type="submit"]').style.display = 'block';
        }
        
        // Close modal when clicking outside
        document.getElementById('vitalsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // Form validation
        document.getElementById('vitalsForm').addEventListener('submit', function(e) {
            const bpInput = document.querySelector('input[name="blood_pressure"]');
            if (bpInput.value && !/\d{2,3}\/\d{2,3}/.test(bpInput.value)) {
                e.preventDefault();
                bpInput.style.borderColor = '#e74c3c';
                alert('Please enter blood pressure in format: 120/80');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>