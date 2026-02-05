<?php
// Start session
session_start();

// Handle logout request (MUST BE AT THE TOP)
if (isset($_GET['logout'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Clear remember me cookie
    setcookie('remember_user', '', time() - 3600, "/");
    
    // Redirect to login page
    header('Location: index.php');
    exit();
}

// Check if user is logged in, redirect to login if not
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Include database functions
require_once 'db_functions.php';

// Initialize error/success messages
$error = '';
$success = '';

// Define missing functions if they don't exist in db_functions.php
if (!function_exists('getPatients')) {
    function getPatients($archived = false) {
        $pdo = getDBConnection();
        if (!$pdo) return [];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE is_archived = :archived ORDER BY created_at DESC");
            $stmt->execute([':archived' => $archived]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get patients error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getConsultationsByPatientId')) {
    function getConsultationsByPatientId($patient_id) {
        $pdo = getDBConnection();
        if (!$pdo) return [];
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM consultations WHERE patient_id = :patient_id ORDER BY consultation_date DESC");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get consultations error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getPatientStatistics')) {
    function getPatientStatistics() {
        $pdo = getDBConnection();
        if (!$pdo) return ['total' => 0, 'male' => 0, 'female' => 0, 'other' => 0, 'new' => 0];
        
        try {
            $stats = [
                'total' => 0,
                'male' => 0,
                'female' => 0,
                'other' => 0,
                'new' => 0
            ];
            
            // Total patients (non-archived)
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE is_archived = false");
            $result = $stmt->fetch();
            $stats['total'] = $result['count'];
            
            // Gender distribution
            $stmt = $pdo->query("SELECT sex, COUNT(*) as count FROM patients WHERE is_archived = false GROUP BY sex");
            $genders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($genders as $gender) {
                if ($gender['sex'] == 'Male') $stats['male'] = $gender['count'];
                elseif ($gender['sex'] == 'Female') $stats['female'] = $gender['count'];
                else $stats['other'] += $gender['count'];
            }
            
            // New patients in last 30 days
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients WHERE is_archived = false AND date_registered >= (CURRENT_DATE - INTERVAL '30 days')");
            $stmt->execute();
            $result = $stmt->fetch();
            $stats['new'] = $result['count'];
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Get statistics error: " . $e->getMessage());
            return ['total' => 0, 'male' => 0, 'female' => 0, 'other' => 0, 'new' => 0];
        }
    }
}

if (!function_exists('getUserById')) {
    function getUserById($user_id) {
        $pdo = getDBConnection();
        if (!$pdo) return null;
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->execute([':id' => $user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getPatientById')) {
    function getPatientById($patient_id) {
        $pdo = getDBConnection();
        if (!$pdo) return null;
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = :patient_id");
            $stmt->execute([':patient_id' => $patient_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get patient by ID error: " . $e->getMessage());
            return null;
        }
    }
}

// ADDED: Function to get age distribution data
if (!function_exists('getAgeDistribution')) {
    function getAgeDistribution() {
        $pdo = getDBConnection();
        if (!$pdo) return [];
        
        try {
            // Get all non-archived patients' ages
            $stmt = $pdo->query("SELECT age FROM patients WHERE is_archived = false AND age IS NOT NULL");
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Define age groups
            $ageGroups = [
                '0-17' => 0,
                '18-30' => 0,
                '31-45' => 0,
                '46-60' => 0,
                '60+' => 0
            ];
            
            // Count patients in each age group
            foreach ($patients as $patient) {
                $age = (int)$patient['age'];
                
                if ($age <= 17) {
                    $ageGroups['0-17']++;
                } elseif ($age <= 30) {
                    $ageGroups['18-30']++;
                } elseif ($age <= 45) {
                    $ageGroups['31-45']++;
                } elseif ($age <= 60) {
                    $ageGroups['46-60']++;
                } else {
                    $ageGroups['60+']++;
                }
            }
            
            return $ageGroups;
        } catch (PDOException $e) {
            error_log("Get age distribution error: " . $e->getMessage());
            return $ageGroups ?? [];
        }
    }
}

// ADDED: Function to get barangay distribution data
if (!function_exists('getBarangayDistribution')) {
    function getBarangayDistribution() {
        $pdo = getDBConnection();
        if (!$pdo) return ['labels' => [], 'data' => []];
        
        try {
            // Get top barangays with patient counts (non-archived)
            $stmt = $pdo->query("
                SELECT address, COUNT(*) as count 
                FROM patients 
                WHERE is_archived = false 
                AND address IS NOT NULL 
                AND address != '' 
                GROUP BY address 
                ORDER BY count DESC 
                LIMIT 8
            ");
            $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Extract barangay names from full addresses
            $barangayData = [];
            foreach ($barangays as $barangay) {
                $address = $barangay['address'];
                // Extract barangay name (assuming format is "BarangayName Anda Bohol")
                $parts = explode(' ', $address);
                $barangayName = $parts[0]; // First word is barangay name
                
                if (!isset($barangayData[$barangayName])) {
                    $barangayData[$barangayName] = 0;
                }
                $barangayData[$barangayName] += $barangay['count'];
            }
            
            // Sort by count
            arsort($barangayData);
            
            // Get top 5 barangays
            $topBarangays = array_slice($barangayData, 0, 5, true);
            
            return [
                'labels' => array_keys($topBarangays),
                'data' => array_values($topBarangays)
            ];
        } catch (PDOException $e) {
            error_log("Get barangay distribution error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
        }
    }
}

// Handle form submissions for patient operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Handle patient form submission
    if ($_POST['action'] == 'add_patient') {
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
        $age = isset($_POST['age']) ? intval($_POST['age']) : 0;
        $sex = isset($_POST['sex']) ? trim($_POST['sex']) : '';
        $contact = isset($_POST['contact']) ? trim($_POST['contact']) : '';
        $unique_id = isset($_POST['unique_id']) ? trim($_POST['unique_id']) : '';
        $date_registered = isset($_POST['date_registered']) ? trim($_POST['date_registered']) : date('Y-m-d');
        
        // Clean contact number - remove all non-numeric characters
        $contact = preg_replace('/[^0-9]/', '', $contact);
        
        // Validate all fields
        if (empty($full_name) || empty($address) || empty($dob) || empty($sex) || empty($contact)) {
            $error = "Please fill in all required fields!";
        } elseif (strlen($contact) !== 11 || !str_starts_with($contact, '09')) {
            $error = "Please enter a valid 11-digit Philippine mobile number starting with '09' (e.g., 09123456789)";
        } else {
            $pdo = getDBConnection();
            if (!$pdo) {
                $error = "Database connection failed. Please try again.";
            } else {
                try {
                    // Generate unique ID if not provided
                    if (empty($unique_id)) {
                        $initials = '';
                        $name_parts = explode(' ', $full_name);
                        foreach ($name_parts as $part) {
                            if (!empty($part)) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                        }
                        if (empty($initials)) {
                            $initials = 'PT';
                        }
                        $unique_id = $initials . '-' . date('ymd') . rand(100, 999);
                    }
                    
                    // FIXED: PostgreSQL syntax - get next patient ID
                    $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(REPLACE(patient_id, 'PAT-', '') AS INTEGER)), 0) as max_id FROM patients WHERE patient_id LIKE 'PAT-%'");
                    $result = $stmt->fetch();
                    $next_number = ($result['max_id'] ?? 0) + 1;
                    $patient_number = str_pad($next_number, 3, '0', STR_PAD_LEFT);
                    $patient_id = 'PAT-' . $patient_number;
                    
                    // Insert patient
                    $stmt = $pdo->prepare("INSERT INTO patients 
                        (patient_id, full_name, address, dob, age, sex, contact, unique_id, date_registered, is_archived, created_at) 
                        VALUES (:patient_id, :full_name, :address, :dob, :age, :sex, :contact, :unique_id, :date_registered, false, NOW())");
                    
                    $stmt->execute([
                        ':patient_id' => $patient_id,
                        ':full_name' => $full_name,
                        ':address' => $address,
                        ':dob' => $dob,
                        ':age' => $age,
                        ':sex' => $sex,
                        ':contact' => $contact,
                        ':unique_id' => $unique_id,
                        ':date_registered' => $date_registered
                    ]);
                    
                    $success = "Patient added successfully! Patient ID: " . $patient_id;
                    
                    // Redirect to show success message
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
                    exit();
                    
                } catch (PDOException $e) {
                    $error = "Failed to add patient. Please try again. Error: " . $e->getMessage();
                    error_log("Add patient error: " . $e->getMessage());
                }
            }
        }
    }
    
    // Handle archive patient
    elseif ($_POST['action'] == 'archive_patient') {
        $patient_id = isset($_POST['patient_id']) ? trim($_POST['patient_id']) : '';
        
        if (!empty($patient_id)) {
            $pdo = getDBConnection();
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE patients SET is_archived = true, date_archived = NOW() WHERE patient_id = :patient_id");
                    $stmt->execute([':patient_id' => $patient_id]);
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode("Patient archived successfully!"));
                    exit();
                    
                } catch (PDOException $e) {
                    $error = "Failed to archive patient.";
                    error_log("Archive patient error: " . $e->getMessage());
                }
            }
        }
    }
    
    // Handle restore patient
    elseif ($_POST['action'] == 'restore_patient') {
        $patient_id = isset($_POST['patient_id']) ? trim($_POST['patient_id']) : '';
        
        if (!empty($patient_id)) {
            $pdo = getDBConnection();
            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("UPDATE patients SET is_archived = false, date_archived = NULL WHERE patient_id = :patient_id");
                    $stmt->execute([':patient_id' => $patient_id]);
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode("Patient restored successfully!"));
                    exit();
                    
                } catch (PDOException $e) {
                    $error = "Failed to restore patient.";
                    error_log("Restore patient error: " . $e->getMessage());
                }
            }
        }
    }
    
    // Handle consultation form submission
    elseif ($_POST['action'] == 'add_consultation') {
        $patient_id = isset($_POST['patient_id']) ? trim($_POST['patient_id']) : '';
        $consultation_date = isset($_POST['consultation_date']) ? trim($_POST['consultation_date']) : date('Y-m-d H:i:s');
        $temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;
        $pulse = isset($_POST['pulse']) ? intval($_POST['pulse']) : null;
        $respiratory_rate = isset($_POST['respiratory_rate']) ? intval($_POST['respiratory_rate']) : null;
        $blood_pressure = isset($_POST['blood_pressure']) ? trim($_POST['blood_pressure']) : '';
        $oxygen_saturation = isset($_POST['oxygen_saturation']) ? intval($_POST['oxygen_saturation']) : null;
        $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : null;
        $height = isset($_POST['height']) ? floatval($_POST['height']) : null;
        $doctor_notes = isset($_POST['doctor_notes']) ? trim($_POST['doctor_notes']) : '';
        
        // Calculate BMI if weight and height are provided
        $bmi = null;
        if ($weight && $height && $height > 0) {
            // Convert height from cm to meters
            $height_m = $height / 100;
            $bmi = round($weight / ($height_m * $height_m), 2);
        }
        
        if (empty($patient_id) || empty($doctor_notes)) {
            $error = "Patient ID and doctor notes are required!";
        } else {
            $pdo = getDBConnection();
            if (!$pdo) {
                $error = "Database connection failed. Please try again.";
            } else {
                try {
                    // Insert consultation with your database structure
                    $stmt = $pdo->prepare("INSERT INTO consultations 
                        (patient_id, consultation_date, temperature, pulse, respiratory_rate, blood_pressure,
                         oxygen_saturation, weight, height, bmi, doctor_notes, created_by, created_at) 
                        VALUES (:patient_id, :consultation_date, :temperature, :pulse, :respiratory_rate, :blood_pressure,
                                :oxygen_saturation, :weight, :height, :bmi, :doctor_notes, :created_by, NOW())");
                    
                    $stmt->execute([
                        ':patient_id' => $patient_id,
                        ':consultation_date' => $consultation_date,
                        ':temperature' => $temperature,
                        ':pulse' => $pulse,
                        ':respiratory_rate' => $respiratory_rate,
                        ':blood_pressure' => $blood_pressure,
                        ':oxygen_saturation' => $oxygen_saturation,
                        ':weight' => $weight,
                        ':height' => $height,
                        ':bmi' => $bmi,
                        ':doctor_notes' => $doctor_notes,
                        ':created_by' => $_SESSION['user_id']
                    ]);
                    
                    $success = "Consultation recorded successfully for Patient ID: " . $patient_id;
                    
                    // Redirect to show success message
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . urlencode($success));
                    exit();
                    
                } catch (PDOException $e) {
                    $error = "Failed to record consultation. Please try again. Error: " . $e->getMessage();
                    error_log("Add consultation error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get patient info for consultation if requested via AJAX
if (isset($_GET['get_patient_info']) && isset($_GET['patient_id'])) {
    $patient = getPatientById($_GET['patient_id']);
    if ($patient) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'patient' => [
                'full_name' => $patient['full_name'],
                'address' => $patient['address'],
                'dob' => $patient['dob'],
                'age' => $patient['age'],
                'sex' => $patient['sex'],
                'contact' => $patient['contact'],
                'unique_id' => $patient['unique_id'],
                'date_registered' => $patient['date_registered']
            ]
        ]);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
}

// Get consultations for a patient if requested via AJAX
if (isset($_GET['get_consultations']) && isset($_GET['patient_id'])) {
    $consultations = getConsultationsByPatientId($_GET['patient_id']);
    if ($consultations) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'consultations' => $consultations
        ]);
        exit();
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No consultations found']);
        exit();
    }
}

// Get success message from URL if redirected after form submission
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}

// Get user info
$user = getUserById($_SESSION['user_id']);

// Get patient data
$activePatients = getPatients(false);
$archivedPatients = getPatients(true);

// Handle search functionality
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filteredPatients = [];

if (!empty($searchTerm)) {
    // Filter patients based on search term
    foreach ($activePatients as $patient) {
        // Search in full_name, patient_id, or contact
        if (stripos($patient['full_name'] ?? '', $searchTerm) !== false ||
            stripos($patient['patient_id'] ?? '', $searchTerm) !== false ||
            stripos($patient['contact'] ?? '', $searchTerm) !== false) {
            $filteredPatients[] = $patient;
        }
    }
} else {
    $filteredPatients = $activePatients;
}

// Get statistics
$stats = getPatientStatistics();
$maleCount = $stats['male'];
$femaleCount = $stats['female'];
$otherCount = $stats['other'];
$newPatients = $stats['new'];

// ADDED: Get age distribution data
$ageDistribution = getAgeDistribution();

// ADDED: Get barangay distribution data
$barangayDistribution = getBarangayDistribution();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Anda EHR</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Add styles for the logout button */
        .logout-btn {
            background: #ff4444;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #cc0000;
        }
        
        /* Modal overlay styling */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            padding: 0.5rem;
        }
        
        .close-modal:hover {
            color: #333;
        }
        
        /* Patient Info Section */
        .patient-info-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .patient-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .patient-info-item {
            padding: 0.5rem 0;
        }
        
        .patient-info-label {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .patient-info-value {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .consultation-id-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            margin-top: 1rem;
        }
        
        .consultation-id-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .consultation-id-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        /* Consultation History Section */
        .consultation-history-section {
            margin-top: 2rem;
        }
        
        .consultation-history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .consultation-history-title {
            font-size: 1.2rem;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .add-consultation-btn {
            background: #27ae60;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .add-consultation-btn:hover {
            background: #219653;
        }
        
        /* Consultation Cards */
        .consultation-cards {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .consultation-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #3498db;
        }
        
        .consultation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .consultation-date {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .consultation-id {
            font-size: 0.9rem;
            color: #666;
            background: #f8f9fa;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
        }
        
        /* Vitals Grid */
        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .vital-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            text-align: center;
        }
        
        .vital-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .vital-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .vital-unit {
            font-size: 0.85rem;
            color: #666;
            margin-left: 2px;
        }
        
        /* Medical Information */
        .medical-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .medical-info-title {
            font-size: 1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .doctor-notes {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            font-size: 0.95rem;
            line-height: 1.5;
            color: #333;
        }
        
        /* No Consultations */
        .no-consultations {
            text-align: center;
            padding: 3rem;
            color: #666;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }
        
        .no-consultations i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        
        .no-consultations h3 {
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        /* Consultation Form */
        .consultation-datetime {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .consultation-datetime-label {
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .consultation-datetime-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .section-separator {
            height: 1px;
            background: linear-gradient(to right, transparent, #ddd, transparent);
            margin: 1.5rem 0;
        }
        
        .form-section-title {
            margin: 1.5rem 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .input-group {
            margin-bottom: 1rem;
        }
        
        .input-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #555;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        .input-field:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .input-field.small {
            max-width: 150px;
        }
        
        .textarea-field {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }
        
        .textarea-field:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-save {
            background: #27ae60;
            color: white;
        }
        
        .btn-save:hover {
            background: #219653;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
        }
        
        .bmi-info {
            background: #e8f4fc;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .bmi-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        /* Table styling */
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0 1rem 0;
        }
        
        .add-btn {
            background: #3498db;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }
        
        .add-btn:hover {
            background: #2980b9;
        }
        
        .patient-table-container {
            overflow-x: auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patient-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #eee;
        }
        
        .patient-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .patient-table tr:hover {
            background: #f0f7ff;
        }
        
        .patient-table tr.clickable {
            cursor: pointer;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.3s;
        }
        
        .consult-btn {
            background: #3498db;
            color: white;
        }
        
        .consult-btn:hover {
            background: #2980b9;
        }
        
        .archive-btn {
            background: #f39c12;
            color: white;
        }
        
        .archive-btn:hover {
            background: #e67e22;
        }
        
        .restore-btn {
            background: #27ae60;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .restore-btn:hover {
            background: #219653;
        }
        
        /* Dashboard styling */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-icon.patients {
            background: #3498db;
        }
        
        .stat-icon.new {
            background: #27ae60;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .stat-trend {
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .stat-trend.positive {
            color: #27ae60;
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .chart-container {
            height: 250px;
            position: relative;
            margin-top: 1rem;
        }
        
        /* Alert styling */
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            padding: 1rem;
            border-radius: 5px;
            max-width: 300px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease;
        }
        
        .alert-error {
            background: #f44336;
            color: white;
        }
        
        .alert-success {
            background: #4CAF50;
            color: white;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ========== ENHANCED SEARCH BAR STYLES ========== */
        .search-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .search-section-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .search-section-title i {
            color: #3498db;
            font-size: 1.5rem;
        }
        
        .search-box-container {
            position: relative;
            max-width: 600px;
            margin-bottom: 0.5rem;
        }
        
        .search-box {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background-color: #f9fafb;
            color: #333;
        }
        
        .search-box:focus {
            outline: none;
            border-color: #3498db;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .search-box::placeholder {
            color: #8a9aad;
            font-style: italic;
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #7b8a8b;
            font-size: 1.2rem;
        }
        
        .search-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 1.5rem;
        }
        
        .search-btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
        
        .search-btn:active {
            transform: translateY(0);
        }
        
        .search-hint {
            color: #7b8a8b;
            font-size: 0.95rem;
            margin-top: 0.75rem;
            font-style: italic;
        }
        
        .clear-search-link {
            background: none;
            border: none;
            color: #3498db;
            cursor: pointer;
            text-decoration: underline;
            font-size: 0.9rem;
            padding: 0.5rem 0;
            margin-top: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .clear-search-link:hover {
            color: #2980b9;
        }
        
        .search-results-info {
            background: #e8f4fc;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            color: #2c3e50;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .search-results-info i {
            color: #3498db;
        }
        
        .search-results-info strong {
            color: #2c3e50;
        }
        
        .no-results-message {
            text-align: center;
            padding: 3rem;
            color: #7b8a8b;
        }
        
        .no-results-message i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #bdc3c7;
        }
        
        /* Responsive search */
        @media (max-width: 768px) {
            .search-section {
                padding: 1.5rem;
            }
            
            .search-section-title {
                font-size: 1.5rem;
            }
            
            .search-btn {
                width: 100%;
                justify-content: center;
            }
            
            .search-results-info {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Error/Success Messages -->
    <?php if (isset($error) && $error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($success) && $success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    
    <!-- Dashboard -->
    <div class="dashboard-container" id="dashboard-container">
        <header class="header">
            <div class="header-left">
                <div class="logo-small">
                    <i class="fas fa-heartbeat"></i> Anda EHR
                </div>
                <button class="nav-btn active" id="dashboard-nav">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </button>
            </div>
            
            <div class="header-right">
                <div class="user-info">
                    <div class="user-avatar" id="user-avatar">
                        <?php 
                        if ($user && isset($user['first_name']) && isset($user['last_name'])) {
                            $initials = substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1);
                            echo strtoupper($initials);
                        }
                        ?>
                    </div>
                    <div class="user-details">
                        <h3 id="user-name">
                            <?php 
                            if ($user) {
                                echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                            } else {
                                echo 'User';
                            }
                            ?>
                        </h3>
                        <p><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'Unknown'); ?></p>
                    </div>
                </div>
                
                <a href="?logout=true" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Log Out
                </a>
            </div>
        </header>
        
        <div class="main-content">
            <nav class="sidebar">
                <div class="sidebar-nav">
                    <a href="#" class="sidebar-item active" data-page="dashboard">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="#" class="sidebar-item" data-page="patients">
                        <i class="fas fa-user-injured"></i> Patient List
                    </a>
                    <a href="#" class="sidebar-item" data-page="archive">
                        <i class="fas fa-archive"></i> Archive
                    </a>
                </div>
            </nav>
            
            <main class="content">
                <!-- Dashboard Page -->
                <div class="page active" id="dashboard-page">
                    <h1 class="page-title">
                        <i class="fas fa-tachometer-alt"></i> Dashboard Overview
                    </h1>
                    
                    <div class="stats-container">
                        <div class="stat-card">
                            <div class="stat-icon patients">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <div class="stat-details">
                                <h3>Total Patients</h3>
                                <div class="stat-value" id="total-patients"><?php echo $stats['total']; ?></div>
                                <div class="stat-trend positive">
                                    <i class="fas fa-arrow-up"></i> 12% increase
                                </div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon new">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-details">
                                <h3>New Patients (30 days)</h3>
                                <div class="stat-value" id="new-patients"><?php echo $newPatients; ?></div>
                                <div class="stat-trend positive">
                                    <i class="fas fa-arrow-up"></i> 5% increase
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="charts-container">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">
                                    <i class="fas fa-venus-mars"></i> Gender Distribution
                                </h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="gender-chart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">
                                    <i class="fas fa-chart-line"></i> Age Distribution
                                </h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="age-chart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">
                                    <i class="fas fa-map-marker-alt"></i> Top Barangays
                                </h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="barangay-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Patient List Page -->
                <div class="page" id="patients-page">
                    <h1 class="page-title">
                        <i class="fas fa-user-injured"></i> Patient Management
                    </h1>
                    
                    <!-- Enhanced Search Section -->
                    <div class="search-section">
                        <h2 class="search-section-title">
                            <i class="fas fa-search"></i> Patient Search
                        </h2>
                        
                        <form method="GET" action="<?php echo basename(__FILE__); ?>" id="search-form">
                            <div class="search-box-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" 
                                       class="search-box" 
                                       name="search" 
                                       id="patient-search"
                                       placeholder="Search for patients by ID, name, or contact..."
                                       value="<?php echo htmlspecialchars($searchTerm); ?>"
                                       autocomplete="off">
                            </div>
                            
                            <p class="search-hint">Enter patient ID, full name, or contact information to search.</p>
                            
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i> Search
                            </button>
                            
                            <?php if (!empty($searchTerm)): ?>
                            <div>
                                <button type="button" onclick="clearSearch()" class="clear-search-link">
                                    <i class="fas fa-times"></i> Clear search and show all patients
                                </button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <?php if (!empty($searchTerm)): ?>
                    <div class="search-results-info">
                        <div>
                            <i class="fas fa-search"></i> 
                            Found <strong><?php echo count($filteredPatients); ?></strong> patient(s) matching "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>"
                        </div>
                        <div>
                            <button onclick="clearSearch()" class="clear-search-link">
                                <i class="fas fa-times"></i> Clear search
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="table-header">
                        <h2 class="table-title">
                            <?php if (!empty($searchTerm)): ?>
                                Search Results
                            <?php else: ?>
                                All Active Patients
                            <?php endif; ?>
                        </h2>
                        <button class="add-btn" id="add-patient-btn-2">
                            <i class="fas fa-plus"></i> Add New Patient
                        </button>
                    </div>
                    
                    <div class="patient-table-container">
                        <table class="patient-table">
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Name</th>
                                    <th>Birthday</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="patient-table-body">
                                <?php if (!empty($filteredPatients)): ?>
                                    <?php foreach ($filteredPatients as $patient): ?>
                                    <tr class="clickable" data-patient-id="<?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?>">
                                        <td><strong><?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($patient['full_name'] ?? ''); ?></td>
                                        <td>
                                            <?php 
                                            if (isset($patient['dob']) && !empty($patient['dob'])) {
                                                echo date('M d, Y', strtotime($patient['dob']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($patient['contact'] ?? 'N/A');
                                            ?>
                                        </td>
                                        <td><span class="status-badge active">Active</span></td>
                                        <td class="action-btns">
                                            <button class="action-btn consult-btn" data-action="consult" data-patient-id="<?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?>">
                                                <i class="fas fa-stethoscope"></i> New Consult
                                            </button>
                                            <button class="action-btn archive-btn" data-action="archive" data-patient-id="<?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?>">
                                                <i class="fas fa-archive"></i> Archive
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
                                            <?php if (!empty($searchTerm)): ?>
                                                <div class="no-results-message">
                                                    <i class="fas fa-search"></i>
                                                    <h3>No patients found</h3>
                                                    <p>No patients match "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>"</p>
                                                    <button class="clear-search-link" onclick="clearSearch()" style="margin-top: 15px; font-size: 1rem; display: inline-flex; align-items: center; gap: 8px;">
                                                        <i class="fas fa-undo"></i> Clear search and show all patients
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <div class="no-results-message">
                                                    <i class="fas fa-user-injured"></i>
                                                    <h3>No patients found</h3>
                                                    <p>No active patients in the system yet.</p>
                                                    <button class="add-btn" id="add-first-patient" style="margin-top: 15px;">
                                                        <i class="fas fa-plus"></i> Add First Patient
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Archive Page -->
                <div class="page" id="archive-page">
                    <h1 class="page-title">
                        <i class="fas fa-archive"></i> Archived Patients
                    </h1>
                    
                    <div class="table-header">
                        <h2 class="table-title">Inactive Patient Records</h2>
                        <div class="info-value" style="color: #666; font-weight: 500;">
                            <i class="fas fa-info-circle"></i> Archived patients can be restored when needed
                        </div>
                    </div>
                    
                    <div class="patient-table-container">
                        <table class="patient-table">
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Name</th>
                                    <th>Birthday</th>
                                    <th>Contact</th>
                                    <th>Date Archived</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="archive-table-body">
                                <?php if (!empty($archivedPatients)): ?>
                                    <?php foreach ($archivedPatients as $patient): ?>
                                    <tr data-patient-id="<?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?>">
                                        <td><strong><?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($patient['full_name'] ?? ''); ?></td>
                                        <td>
                                            <?php 
                                            if (isset($patient['dob']) && !empty($patient['dob'])) {
                                                echo date('M d, Y', strtotime($patient['dob']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            echo htmlspecialchars($patient['contact'] ?? 'N/A');
                                            ?>
                                        </td>
                                        <td><?php echo isset($patient['date_archived']) && !empty($patient['date_archived']) ? date('M d, Y', strtotime($patient['date_archived'])) : 'N/A'; ?></td>
                                        <td>
                                            <button class="restore-btn" data-patient-id="<?php echo htmlspecialchars($patient['patient_id'] ?? ''); ?>">
                                                <i class="fas fa-undo"></i> Restore
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 2rem; color: #666;">
                                            No archived patients found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Patient Modal -->
    <div class="modal-overlay" id="patient-modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="patient-modal-title">
                    <i class="fas fa-user-plus"></i> Add New Patient
                </h2>
                <button class="close-modal" id="close-patient-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <form id="patient-form" method="POST" action="<?php echo basename(__FILE__); ?>">
                    <input type="hidden" name="action" value="add_patient">
                    <input type="hidden" name="patient_id" id="patient-edit-id" value="">
                    
                    <h3 class="form-section-title">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </h3>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label class="input-label" for="patient-full-name">
                                <i class="fas fa-user"></i> Full Name *
                            </label>
                            <input type="text" class="input-field" id="patient-full-name" name="full_name" placeholder="Enter patient's full name" required>
                        </div>
                        
                        <div class="input-group">
                            <label class="input-label" for="patient-address">
                                <i class="fas fa-home"></i> Address *
                            </label>
                            <select class="input-field" id="patient-address" name="address" required>
                                <option value="">Select Address</option>
                                <option value="Almaria Anda Bohol">Almaria Anda Bohol</option>
                                <option value="Bacong Anda Bohol">Bacong Anda Bohol</option>
                                <option value="Badiang Anda Bohol">Badiang Anda Bohol</option>
                                <option value="Buenasuerte Anda Bohol">Buenasuerte Anda Bohol</option>
                                <option value="Candabong Anda Bohol">Candabong Anda Bohol</option>
                                <option value="Casica Anda Bohol">Casica Anda Bohol</option>
                                <option value="Katipunan Anda Bohol">Katipunan Anda Bohol</option>
                                <option value="Linawan Anda Bohol">Linawan Anda Bohol</option>
                                <option value="Lundag Anda Bohol">Lundag Anda Bohol</option>
                                <option value="Poblacion Anda Bohol">Poblacion Anda Bohol</option>
                                <option value="Santa Cruz Anda Bohol">Santa Cruz Anda Bohol</option>
                                <option value="Suba Anda Bohol">Suba Anda Bohol</option>
                                <option value="Talisay Anda Bohol">Talisay Anda Bohol</option>
                                <option value="Tanod Anda Bohol">Tanod Anda Bohol</option>
                                <option value="Tawid Anda Bohol">Tawid Anda Bohol</option>
                                <option value="Virgen Anda Bohol">Virgen Anda Bohol</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label class="input-label" for="patient-dob">
                                <i class="fas fa-calendar"></i> Date of Birth *
                            </label>
                            <input type="date" class="input-field" id="patient-dob" name="dob" required max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="input-group">
                            <label class="input-label" for="patient-age">
                                <i class="fas fa-birthday-cake"></i> Age
                            </label>
                            <input type="number" class="input-field" id="patient-age" name="age" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label class="input-label" for="patient-sex">
                                <i class="fas fa-venus-mars"></i> Sex *
                            </label>
                            <select class="input-field" id="patient-sex" name="sex" required>
                                <option value="">Select Sex</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="input-group">
                            <label class="input-label" for="patient-contact">
                                <i class="fas fa-phone"></i> Contact Number *
                            </label>
                            <input type="tel" class="input-field" id="patient-contact" name="contact" placeholder="09123456789" required>
                            <small style="display: block; margin-top: 0.25rem; color: #666;">Format: 09123456789 (11 digits starting with 09)</small>
                        </div>
                    </div>
                    
                    <h3 class="form-section-title">
                        <i class="fas fa-id-card"></i> Identification
                    </h3>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label class="input-label" for="patient-id">
                                <i class="fas fa-fingerprint"></i> Unique ID
                            </label>
                            <input type="text" class="input-field" id="patient-id" name="unique_id" placeholder="Will be auto-generated">
                        </div>
                        
                        <div class="input-group">
                            <label class="input-label" for="patient-registered">
                                <i class="fas fa-calendar-check"></i> Date Registered
                            </label>
                            <input type="date" class="input-field" id="patient-registered" name="date_registered" value="<?php echo date('Y-m-d'); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-save" id="save-patient-btn">
                            <i class="fas fa-save"></i> Save Patient
                        </button>
                        <button type="button" class="btn btn-cancel" id="cancel-patient-btn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Patient History Modal -->
    <div class="modal-overlay" id="patient-history-modal" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="patient-history-title">
                    <i class="fas fa-history"></i> Patient History
                </h2>
                <button class="close-modal" id="close-history-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <!-- Patient Information Section -->
                <div class="patient-info-section" id="history-patient-info">
                    <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-injured"></i> Patient Information
                    </h3>
                    
                    <div class="patient-info-grid" id="history-patient-info-grid">
                        <!-- Patient info will be loaded here via JavaScript -->
                    </div>
                    
                    <div class="consultation-id-section">
                        <div>
                            <div class="consultation-id-label">
                                <i class="fas fa-fingerprint"></i> Unique ID
                            </div>
                            <div class="consultation-id-value" id="history-patient-unique-id">Loading...</div>
                        </div>
                        <div>
                            <div class="consultation-id-label">
                                <i class="fas fa-calendar-check"></i> Date Registered
                            </div>
                            <div class="consultation-id-value" id="history-patient-date-registered">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <!-- Consultation History Section -->
                <div class="consultation-history-section">
                    <div class="consultation-history-header">
                        <h3 class="consultation-history-title">
                            <i class="fas fa-stethoscope"></i> Consultation History
                        </h3>
                        <button class="add-consultation-btn" id="add-consultation-from-history">
                            <i class="fas fa-plus"></i> New Consultation
                        </button>
                    </div>
                    
                    <div class="consultation-cards" id="consultation-cards-container">
                        <!-- Consultation cards will be loaded here via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Consultation Modal -->
    <div class="modal-overlay" id="consultation-modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="consultation-modal-title">
                    <i class="fas fa-stethoscope"></i> New Consultation
                </h2>
                <button class="close-modal" id="close-consultation-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <!-- Patient Information Section -->
                <div class="patient-info-section" id="consultation-patient-info">
                    <h3 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-injured"></i> Patient Information
                    </h3>
                    
                    <div class="patient-info-grid" id="consultation-patient-info-grid">
                        <!-- Patient info will be loaded here via JavaScript -->
                    </div>
                    
                    <div class="consultation-id-section">
                        <div>
                            <div class="consultation-id-label">
                                <i class="fas fa-fingerprint"></i> Unique ID
                            </div>
                            <div class="consultation-id-value" id="consultation-patient-unique-id">Loading...</div>
                        </div>
                        <div>
                            <div class="consultation-id-label">
                                <i class="fas fa-calendar-check"></i> Date Registered
                            </div>
                            <div class="consultation-id-value" id="consultation-patient-date-registered">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <!-- Consultation Date/Time -->
                <div class="consultation-datetime">
                    <div class="consultation-datetime-label">
                        <i class="fas fa-calendar-alt"></i> Consultation Date & Time
                    </div>
                    <div class="consultation-datetime-value" id="current-consultation-datetime">
                        Loading...
                    </div>
                </div>
                
                <!-- Section Separator -->
                <div class="section-separator"></div>
                
                <form id="consultation-form" method="POST" action="<?php echo basename(__FILE__); ?>">
                    <input type="hidden" name="action" value="add_consultation">
                    <input type="hidden" name="patient_id" id="consultation-patient-id" value="">
                    <input type="hidden" name="consultation_date" id="consultation-datetime" value="">
                    
                    <h3 class="form-section-title">
                        <i class="fas fa-heartbeat"></i> Vital Signs
                    </h3>
                    
                    <div class="vitals-grid">
                        <div class="vital-item">
                            <div class="vital-label">Temperature (°C)</div>
                            <input type="number" class="input-field small" name="temperature" placeholder="36.5" step="0.1" min="30" max="45">
                        </div>
                        <div class="vital-item">
                            <div class="vital-label">Pulse (bpm)</div>
                            <input type="number" class="input-field small" name="pulse" placeholder="72" min="30" max="200">
                        </div>
                        <div class="vital-item">
                            <div class="vital-label">Respiratory Rate</div>
                            <input type="number" class="input-field small" name="respiratory_rate" placeholder="16" min="10" max="60">
                        </div>
                        <div class="vital-item">
                            <div class="vital-label">Blood Pressure</div>
                            <input type="text" class="input-field small" name="blood_pressure" placeholder="120/80" pattern="\d{2,3}/\d{2,3}">
                        </div>
                        <div class="vital-item">
                            <div class="vital-label">O₂ Saturation (%)</div>
                            <input type="number" class="input-field small" name="oxygen_saturation" placeholder="98" min="70" max="100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label class="input-label" for="consultation-weight">
                                <i class="fas fa-weight"></i> Weight (kg)
                            </label>
                            <input type="number" class="input-field small" id="consultation-weight" name="weight" placeholder="65" step="0.1" min="1" max="300">
                        </div>
                        
                        <div class="input-group">
                            <label class="input-label" for="consultation-height">
                                <i class="fas fa-ruler-vertical"></i> Height (cm)
                            </label>
                            <input type="number" class="input-field small" id="consultation-height" name="height" placeholder="170" step="0.1" min="30" max="250">
                        </div>
                    </div>
                    
                    <div id="bmi-display" class="bmi-info" style="display: none;">
                        <strong>BMI:</strong> <span id="bmi-value" class="bmi-value"></span>
                        <div id="bmi-category" style="font-size: 0.85rem; margin-top: 0.25rem;"></div>
                    </div>
                    
                    <h3 class="form-section-title">
                        <i class="fas fa-file-medical"></i> Medical Information
                    </h3>
                    
                    <div class="input-group">
                        <label class="input-label" for="consultation-doctor-notes">
                            <i class="fas fa-sticky-note"></i> Doctor's Notes *
                        </label>
                        <textarea class="textarea-field" id="consultation-doctor-notes" name="doctor_notes" placeholder="Enter diagnosis, treatment plan, observations, etc." rows="8" required></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-save" id="save-consultation-btn">
                            <i class="fas fa-save"></i> Save Consultation
                        </button>
                        <button type="button" class="btn btn-cancel" id="cancel-consultation-btn">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Archive Confirmation Modal -->
    <div class="modal-overlay" id="archive-confirm-modal" style="display: none;">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-archive"></i> Confirm Archive
                </h2>
                <button class="close-modal" id="close-archive-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <p style="margin-bottom: 1rem;">Are you sure you want to archive this patient?</p>
                <form id="archive-form" method="POST" action="<?php echo basename(__FILE__); ?>">
                    <input type="hidden" name="action" value="archive_patient">
                    <input type="hidden" name="patient_id" id="archive-patient-id" value="">
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn btn-cancel" id="cancel-archive-btn">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-save" style="background: #ff9800;">
                            Archive Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Current patient ID for consultation history
        let currentPatientId = null;
        
        // ========== MODAL HANDLING ==========
        
        // Open patient modal
        const addPatientBtn2 = document.getElementById('add-patient-btn-2');
        if (addPatientBtn2) {
            addPatientBtn2.addEventListener('click', function() {
                document.getElementById('patient-modal-overlay').style.display = 'flex';
                resetPatientForm();
            });
        }
        
        // Add first patient button
        const addFirstPatientBtn = document.getElementById('add-first-patient');
        if (addFirstPatientBtn) {
            addFirstPatientBtn.addEventListener('click', function() {
                document.getElementById('patient-modal-overlay').style.display = 'flex';
                resetPatientForm();
            });
        }
        
        // Close patient modal
        const closePatientModal = document.getElementById('close-patient-modal');
        if (closePatientModal) {
            closePatientModal.addEventListener('click', function() {
                document.getElementById('patient-modal-overlay').style.display = 'none';
            });
        }
        
        const cancelPatientBtn = document.getElementById('cancel-patient-btn');
        if (cancelPatientBtn) {
            cancelPatientBtn.addEventListener('click', function() {
                document.getElementById('patient-modal-overlay').style.display = 'none';
            });
        }
        
        // Close history modal
        const closeHistoryModal = document.getElementById('close-history-modal');
        if (closeHistoryModal) {
            closeHistoryModal.addEventListener('click', function() {
                document.getElementById('patient-history-modal').style.display = 'none';
            });
        }
        
        // Close consultation modal
        const closeConsultationModal = document.getElementById('close-consultation-modal');
        if (closeConsultationModal) {
            closeConsultationModal.addEventListener('click', function() {
                document.getElementById('consultation-modal-overlay').style.display = 'none';
            });
        }
        
        const cancelConsultationBtn = document.getElementById('cancel-consultation-btn');
        if (cancelConsultationBtn) {
            cancelConsultationBtn.addEventListener('click', function() {
                document.getElementById('consultation-modal-overlay').style.display = 'none';
            });
        }
        
        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        });
        
        // ========== PATIENT TABLE CLICK HANDLER ==========
        
        // Make patient rows clickable to show history
        document.querySelectorAll('.patient-table tbody tr.clickable').forEach(row => {
            row.addEventListener('click', async function(e) {
                // Don't trigger if clicking on action buttons
                if (e.target.closest('.action-btn')) {
                    return;
                }
                
                const patientId = this.getAttribute('data-patient-id');
                currentPatientId = patientId;
                
                // Get patient info from database via AJAX
                try {
                    const response = await fetch(`?get_patient_info=true&patient_id=${encodeURIComponent(patientId)}`);
                    const data = await response.json();
                    
                    if (data.success && data.patient) {
                        const patient = data.patient;
                        
                        // Format date of birth
                        const dob = new Date(patient.dob);
                        const formattedDOB = dob.toLocaleDateString('en-US', { 
                            month: 'long', 
                            day: 'numeric', 
                            year: 'numeric' 
                        });
                        
                        // Format date registered
                        const dateRegistered = new Date(patient.date_registered);
                        const formattedDateRegistered = dateRegistered.toLocaleDateString('en-US', { 
                            month: 'long', 
                            day: 'numeric', 
                            year: 'numeric' 
                        });
                        
                        // Update patient info display
                        document.getElementById('history-patient-info-grid').innerHTML = `
                            <div class="patient-info-item">
                                <div class="patient-info-label">
                                    <i class="fas fa-user"></i> FULL NAME
                                </div>
                                <div class="patient-info-value">${patient.full_name}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">
                                    <i class="fas fa-home"></i> ADDRESS
                                </div>
                                <div class="patient-info-value">${patient.address}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">
                                    <i class="fas fa-calendar"></i> DATE OF BIRTH
                                </div>
                                <div class="patient-info-value">${formattedDOB}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">
                                    <i class="fas fa-birthday-cake"></i> AGE
                                </div>
                                <div class="patient-info-value">${patient.age} years</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">
                                    <i class="fas fa-venus-mars"></i> SEX
                                </div>
                                <div class="patient-info-value">${patient.sex}</div>
                            </div>
                            <div class="patient-info-item">
                                <div class="patient-info-label">
                                    <i class="fas fa-phone"></i> CONTACT NUMBER
                                </div>
                                <div class="patient-info-value">${patient.contact}</div>
                            </div>
                        `;
                        
                        // Update unique ID and date registered
                        document.getElementById('history-patient-unique-id').textContent = patient.unique_id || 'N/A';
                        document.getElementById('history-patient-date-registered').textContent = formattedDateRegistered;
                        
                        // Update modal title
                        document.getElementById('patient-history-title').innerHTML = `<i class="fas fa-history"></i> ${patient.full_name}'s Consultation History`;
                        
                        // Load consultation history
                        await loadConsultationHistory(patientId);
                        
                        // Show history modal
                        document.getElementById('patient-history-modal').style.display = 'flex';
                    }
                } catch (error) {
                    console.error('Error loading patient info:', error);
                    alert('Failed to load patient information. Please try again.');
                }
            });
        });
        
        // ========== LOAD CONSULTATION HISTORY ==========
        
        async function loadConsultationHistory(patientId) {
            try {
                const response = await fetch(`?get_consultations=true&patient_id=${encodeURIComponent(patientId)}`);
                const data = await response.json();
                
                const container = document.getElementById('consultation-cards-container');
                
                if (data.success && data.consultations && data.consultations.length > 0) {
                    let html = '';
                    
                    data.consultations.forEach(consultation => {
                        // Format consultation date
                        const consultationDate = new Date(consultation.consultation_date);
                        const formattedDate = consultationDate.toLocaleDateString('en-US', { 
                            weekday: 'long',
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        // Create vital signs HTML
                        const vitals = [];
                        if (consultation.temperature) vitals.push(`<div class="vital-item"><div class="vital-label">Temperature</div><div class="vital-value">${consultation.temperature}<span class="vital-unit">°C</span></div></div>`);
                        if (consultation.pulse) vitals.push(`<div class="vital-item"><div class="vital-label">Pulse</div><div class="vital-value">${consultation.pulse}<span class="vital-unit">bpm</span></div></div>`);
                        if (consultation.respiratory_rate) vitals.push(`<div class="vital-item"><div class="vital-label">Respiratory Rate</div><div class="vital-value">${consultation.respiratory_rate}</div></div>`);
                        if (consultation.blood_pressure) vitals.push(`<div class="vital-item"><div class="vital-label">Blood Pressure</div><div class="vital-value">${consultation.blood_pressure}</div></div>`);
                        if (consultation.oxygen_saturation) vitals.push(`<div class="vital-item"><div class="vital-label">O₂ Saturation</div><div class="vital-value">${consultation.oxygen_saturation}<span class="vital-unit">%</span></div></div>`);
                        if (consultation.weight) vitals.push(`<div class="vital-item"><div class="vital-label">Weight</div><div class="vital-value">${consultation.weight}<span class="vital-unit">kg</span></div></div>`);
                        if (consultation.height) vitals.push(`<div class="vital-item"><div class="vital-label">Height</div><div class="vital-value">${consultation.height}<span class="vital-unit">cm</span></div></div>`);
                        
                        // Add BMI if calculated
                        let bmiHtml = '';
                        if (consultation.bmi) {
                            let bmiCategory = '';
                            let bmiColor = '';
                            if (consultation.bmi < 18.5) {
                                bmiCategory = 'Underweight';
                                bmiColor = '#3498db';
                            } else if (consultation.bmi < 25) {
                                bmiCategory = 'Normal weight';
                                bmiColor = '#27ae60';
                            } else if (consultation.bmi < 30) {
                                bmiCategory = 'Overweight';
                                bmiColor = '#f39c12';
                            } else {
                                bmiCategory = 'Obese';
                                bmiColor = '#e74c3c';
                            }
                            bmiHtml = `<div class="vital-item"><div class="vital-label">BMI</div><div class="vital-value" style="color: ${bmiColor};">${consultation.bmi} <span style="font-size: 0.85rem; color: #666;">(${bmiCategory})</span></div></div>`;
                        }
                        
                        html += `
                            <div class="consultation-card">
                                <div class="consultation-header">
                                    <div class="consultation-date">
                                        <i class="fas fa-calendar-alt"></i> ${formattedDate}
                                    </div>
                                    <div class="consultation-id">Consultation #${consultation.id}</div>
                                </div>
                                
                                <div class="vitals-grid">
                                    ${vitals.join('')}
                                    ${bmiHtml}
                                </div>
                                
                                <div class="medical-info">
                                    <h4 class="medical-info-title">
                                        <i class="fas fa-sticky-note"></i> Doctor's Notes
                                    </h4>
                                    <div class="doctor-notes">
                                        ${consultation.doctor_notes.replace(/\n/g, '<br>')}
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `
                        <div class="no-consultations">
                            <i class="fas fa-clipboard-list"></i>
                            <h3>No Consultation History</h3>
                            <p>This patient has no recorded consultations yet.</p>
                            <button class="add-consultation-btn" id="add-first-consultation" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i> Add First Consultation
                            </button>
                        </div>
                    `;
                    
                    // Add event listener to the "Add First Consultation" button
                    document.getElementById('add-first-consultation')?.addEventListener('click', function() {
                        openConsultationModal(currentPatientId);
                    });
                }
            } catch (error) {
                console.error('Error loading consultation history:', error);
                const container = document.getElementById('consultation-cards-container');
                container.innerHTML = `
                    <div class="no-consultations">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Error Loading History</h3>
                        <p>Failed to load consultation history. Please try again.</p>
                    </div>
                `;
            }
        }
        
        // ========== CONSULTATION FUNCTIONALITY ==========
        
        // Add consultation from history modal
        document.getElementById('add-consultation-from-history')?.addEventListener('click', function() {
            if (currentPatientId) {
                openConsultationModal(currentPatientId);
            }
        });
        
        // Consultation button click handler in patient table
        document.querySelectorAll('.consult-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent row click event
                const patientId = this.getAttribute('data-patient-id');
                openConsultationModal(patientId);
            });
        });
        
        // Function to open consultation modal
        async function openConsultationModal(patientId) {
            document.getElementById('consultation-patient-id').value = patientId;
            currentPatientId = patientId;
            
            // Close history modal if open
            document.getElementById('patient-history-modal').style.display = 'none';
            
            // Get patient info from database via AJAX
            try {
                const response = await fetch(`?get_patient_info=true&patient_id=${encodeURIComponent(patientId)}`);
                const data = await response.json();
                
                if (data.success && data.patient) {
                    const patient = data.patient;
                    
                    // Format date of birth
                    const dob = new Date(patient.dob);
                    const formattedDOB = dob.toLocaleDateString('en-US', { 
                        month: 'long', 
                        day: 'numeric', 
                        year: 'numeric' 
                    });
                    
                    // Format date registered
                    const dateRegistered = new Date(patient.date_registered);
                    const formattedDateRegistered = dateRegistered.toLocaleDateString('en-US', { 
                        month: 'long', 
                        day: 'numeric', 
                        year: 'numeric' 
                    });
                    
                    // Update patient info display
                    document.getElementById('consultation-patient-info-grid').innerHTML = `
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-user"></i> FULL NAME
                            </div>
                            <div class="patient-info-value">${patient.full_name}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-home"></i> ADDRESS
                            </div>
                            <div class="patient-info-value">${patient.address}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-calendar"></i> DATE OF BIRTH
                            </div>
                            <div class="patient-info-value">${formattedDOB}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-birthday-cake"></i> AGE
                            </div>
                            <div class="patient-info-value">${patient.age} years</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-venus-mars"></i> SEX
                            </div>
                            <div class="patient-info-value">${patient.sex}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-phone"></i> CONTACT NUMBER
                            </div>
                            <div class="patient-info-value">${patient.contact}</div>
                        </div>
                    `;
                    
                    // Update unique ID and date registered
                    document.getElementById('consultation-patient-unique-id').textContent = patient.unique_id || 'N/A';
                    document.getElementById('consultation-patient-date-registered').textContent = formattedDateRegistered;
                    
                    // Update modal title
                    document.getElementById('consultation-modal-title').innerHTML = `<i class="fas fa-stethoscope"></i> New Consultation for ${patient.full_name}`;
                } else {
                    // Fallback to basic info if AJAX fails
                    const row = document.querySelector(`tr[data-patient-id="${patientId}"]`);
                    const patientName = row?.querySelector('td:nth-child(2)').textContent || 'Patient';
                    document.getElementById('consultation-patient-info-grid').innerHTML = `
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-user"></i> PATIENT ID
                            </div>
                            <div class="patient-info-value">${patientId}</div>
                        </div>
                        <div class="patient-info-item">
                            <div class="patient-info-label">
                                <i class="fas fa-user"></i> NAME
                            </div>
                            <div class="patient-info-value">${patientName}</div>
                        </div>
                    `;
                    document.getElementById('consultation-patient-unique-id').textContent = 'Not available';
                    document.getElementById('consultation-patient-date-registered').textContent = 'Not available';
                    document.getElementById('consultation-modal-title').innerHTML = `<i class="fas fa-stethoscope"></i> New Consultation`;
                }
            } catch (error) {
                console.error('Error loading patient info:', error);
                // Fallback
                const row = document.querySelector(`tr[data-patient-id="${patientId}"]`);
                const patientName = row?.querySelector('td:nth-child(2)').textContent || 'Patient';
                document.getElementById('consultation-patient-info-grid').innerHTML = `
                    <div class="patient-info-item">
                        <div class="patient-info-label">
                            <i class="fas fa-user"></i> PATIENT ID
                        </div>
                        <div class="patient-info-value">${patientId}</div>
                    </div>
                    <div class="patient-info-item">
                        <div class="patient-info-label">
                            <i class="fas fa-user"></i> NAME
                        </div>
                        <div class="patient-info-value">${patientName}</div>
                    </div>
                `;
                document.getElementById('consultation-patient-unique-id').textContent = 'Not available';
                document.getElementById('consultation-patient-date-registered').textContent = 'Not available';
                document.getElementById('consultation-modal-title').innerHTML = `<i class="fas fa-stethoscope"></i> New Consultation`;
            }
            
            // Set current datetime for consultation
            const now = new Date();
            const currentDateTime = now.toISOString().slice(0, 19).replace('T', ' ');
            document.getElementById('consultation-datetime').value = currentDateTime;
            
            // Format and display current datetime
            const formattedDateTime = now.toLocaleDateString('en-US', { 
                weekday: 'long',
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('current-consultation-datetime').textContent = formattedDateTime;
            
            // Reset consultation form
            document.getElementById('consultation-form').reset();
            document.getElementById('bmi-display').style.display = 'none';
            
            // Show consultation modal
            document.getElementById('consultation-modal-overlay').style.display = 'flex';
            
            // Focus on first vital sign field
            setTimeout(() => {
                document.querySelector('input[name="temperature"]').focus();
            }, 100);
        }
        
        // ========== BMI CALCULATION ==========
        
        const weightInput = document.getElementById('consultation-weight');
        const heightInput = document.getElementById('consultation-height');
        const bmiDisplay = document.getElementById('bmi-display');
        const bmiValue = document.getElementById('bmi-value');
        const bmiCategory = document.getElementById('bmi-category');
        
        function calculateBMI() {
            const weight = parseFloat(weightInput.value);
            const height = parseFloat(heightInput.value);
            
            if (weight && height && height > 0) {
                // Convert height from cm to meters
                const heightM = height / 100;
                const bmi = weight / (heightM * heightM);
                const roundedBMI = bmi.toFixed(2);
                
                bmiValue.textContent = roundedBMI;
                bmiDisplay.style.display = 'block';
                
                // Determine BMI category
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
                
                bmiCategory.textContent = category;
                bmiCategory.style.color = color;
            } else {
                bmiDisplay.style.display = 'none';
            }
        }
        
        if (weightInput && heightInput) {
            weightInput.addEventListener('input', calculateBMI);
            heightInput.addEventListener('input', calculateBMI);
        }
        
        // ========== FORM VALIDATION ==========
        
        // Calculate age from date of birth
        const dobInput = document.getElementById('patient-dob');
        const ageInput = document.getElementById('patient-age');
        
        if (dobInput && ageInput) {
            dobInput.addEventListener('change', function() {
                if (this.value) {
                    const birthDate = new Date(this.value);
                    const today = new Date();
                    let age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();
                    
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }
                    
                    ageInput.value = age;
                } else {
                    ageInput.value = '';
                }
            });
        }
        
        // Fix contact number input - NO DASHES
        const contactInput = document.getElementById('patient-contact');
        if (contactInput) {
            // Remove any existing dashes
            contactInput.value = contactInput.value.replace(/\D/g, '');
            
            // Listen for input
            contactInput.addEventListener('input', function() {
                // Remove ALL non-numeric characters
                let value = this.value.replace(/\D/g, '');
                
                // Limit to 11 digits
                if (value.length > 11) {
                    value = value.substring(0, 11);
                }
                
                // Update value WITHOUT dashes
                this.value = value;
            });
            
            // Validate on blur
            contactInput.addEventListener('blur', function() {
                const value = this.value.replace(/\D/g, '');
                
                if (value.length > 0 && value.length !== 11) {
                    alert('Please enter exactly 11 digits for the contact number');
                    this.focus();
                } else if (value.length === 11 && !value.startsWith('09')) {
                    alert('Philippine mobile numbers should start with "09"');
                    this.focus();
                }
            });
        }
        
        // Validate patient form before submission
        const patientForm = document.getElementById('patient-form');
        if (patientForm) {
            patientForm.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (field.id === 'patient-contact') {
                        // Special handling for contact number
                        const cleanValue = field.value.replace(/\D/g, '');
                        if (!cleanValue || cleanValue.length !== 11 || !cleanValue.startsWith('09')) {
                            isValid = false;
                            field.style.borderColor = 'red';
                            alert('Please enter a valid 11-digit Philippine mobile number starting with "09" (e.g., 09123456789)');
                        } else {
                            field.style.borderColor = '';
                        }
                    } else if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = 'red';
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
                
                // Clean contact number before submission
                if (contactInput) {
                    contactInput.value = contactInput.value.replace(/\D/g, '');
                }
                
                return true;
            });
        }
        
        // Validate consultation form before submission
        const consultationForm = document.getElementById('consultation-form');
        if (consultationForm) {
            consultationForm.addEventListener('submit', function(e) {
                const doctorNotesField = document.getElementById('consultation-doctor-notes');
                if (!doctorNotesField.value.trim()) {
                    e.preventDefault();
                    doctorNotesField.style.borderColor = 'red';
                    doctorNotesField.focus();
                    alert('Please enter doctor\'s notes.');
                    return false;
                }
                
                // Validate blood pressure format if provided
                const bpField = document.querySelector('input[name="blood_pressure"]');
                if (bpField.value && !/\d{2,3}\/\d{2,3}/.test(bpField.value)) {
                    e.preventDefault();
                    bpField.style.borderColor = 'red';
                    bpField.focus();
                    alert('Please enter blood pressure in the format: 120/80');
                    return false;
                }
                
                return true;
            });
        }
        
        // ========== PAGE NAVIGATION ==========
        
        // Store current page state
        let currentPage = 'dashboard';
        
        // Check URL for search parameter to determine which page to show
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search') || window.location.href.includes('patients-page')) {
            currentPage = 'patients';
            showPage('patients');
        }
        
        // Sidebar navigation - FIXED VERSION
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const page = this.getAttribute('data-page');
                currentPage = page;
                
                // Update active states
                document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Show selected page
                showPage(page);
            });
        });
        
        // Function to show page
        function showPage(page) {
            document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
            const targetPage = document.getElementById(page + '-page');
            if (targetPage) {
                targetPage.classList.add('active');
                
                // If showing patients page, focus on search input
                if (page === 'patients') {
                    setTimeout(() => {
                        const searchInput = document.getElementById('patient-search');
                        if (searchInput) {
                            searchInput.focus();
                        }
                    }, 100);
                }
            }
        }
        
        // ========== PATIENT ACTIONS ==========
        
        // Archive patient buttons
        document.querySelectorAll('.archive-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent row click event
                const patientId = this.getAttribute('data-patient-id');
                document.getElementById('archive-patient-id').value = patientId;
                document.getElementById('archive-confirm-modal').style.display = 'flex';
            });
        });
        
        // Close archive modal
        const closeArchiveModal = document.getElementById('close-archive-modal');
        if (closeArchiveModal) {
            closeArchiveModal.addEventListener('click', function() {
                document.getElementById('archive-confirm-modal').style.display = 'none';
            });
        }
        
        const cancelArchiveBtn = document.getElementById('cancel-archive-btn');
        if (cancelArchiveBtn) {
            cancelArchiveBtn.addEventListener('click', function() {
                document.getElementById('archive-confirm-modal').style.display = 'none';
            });
        }
        
        // Restore patient buttons
        document.querySelectorAll('.restore-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const patientId = this.getAttribute('data-patient-id');
                if (confirm('Are you sure you want to restore this patient?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'restore_patient';
                    form.appendChild(actionInput);
                    
                    const patientInput = document.createElement('input');
                    patientInput.type = 'hidden';
                    patientInput.name = 'patient_id';
                    patientInput.value = patientId;
                    form.appendChild(patientInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
        
        // ========== CHARTS ==========
        
        // Initialize gender chart if canvas exists
        const genderCtx = document.getElementById('gender-chart');
        if (genderCtx) {
            // Get gender data from PHP
            const maleCount = <?php echo $maleCount; ?>;
            const femaleCount = <?php echo $femaleCount; ?>;
            const otherCount = <?php echo $otherCount; ?>;
            
            const genderChart = new Chart(genderCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Male', 'Female', 'Other'],
                    datasets: [{
                        data: [maleCount, femaleCount, otherCount],
                        backgroundColor: [
                            '#3498db',
                            '#e74c3c',
                            '#f39c12'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Initialize age distribution chart
        const ageCtx = document.getElementById('age-chart');
        if (ageCtx) {
            // Get age distribution data from PHP
            const ageData = <?php echo json_encode(array_values($ageDistribution)); ?>;
            const ageLabels = <?php echo json_encode(array_keys($ageDistribution)); ?>;
            
            const ageChart = new Chart(ageCtx, {
                type: 'bar',
                data: {
                    labels: ageLabels,
                    datasets: [{
                        label: 'Number of Patients',
                        data: ageData,
                        backgroundColor: [
                            '#3498db',
                            '#2ecc71',
                            '#f39c12',
                            '#e74c3c',
                            '#9b59b6'
                        ],
                        borderColor: [
                            '#2980b9',
                            '#27ae60',
                            '#d35400',
                            '#c0392b',
                            '#8e44ad'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Patients by Age Group'
                        }
                    }
                }
            });
        }
        
        // Initialize barangay distribution chart
        const barangayCtx = document.getElementById('barangay-chart');
        if (barangayCtx) {
            // Get barangay data from PHP
            const barangayLabels = <?php echo json_encode($barangayDistribution['labels']); ?>;
            const barangayData = <?php echo json_encode($barangayDistribution['data']); ?>;
            
            const barangayChart = new Chart(barangayCtx, {
                type: 'bar',
                data: {
                    labels: barangayLabels,
                    datasets: [{
                        label: 'Number of Patients',
                        data: barangayData,
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // Horizontal bar chart
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Patients by Barangay'
                        }
                    }
                }
            });
        }
        
        // ========== AUTO-HIDE ALERTS ==========
        
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        // ========== ENHANCED SEARCH FUNCTIONALITY ==========
        
        // Function to clear search
        window.clearSearch = function() {
            // Remove search parameter and show patients page
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }
        
        // Real-time search (optional - for better UX)
        const searchInput = document.getElementById('patient-search');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                // Only search after user stops typing for 500ms
                searchTimeout = setTimeout(() => {
                    // For real-time search, you could implement AJAX here
                    // For now, we'll just submit the form if search term is empty or at least 2 characters
                    if (this.value.length >= 2 || this.value.length === 0) {
                        // Make sure we're on patients page before submitting
                        if (currentPage !== 'patients') {
                            showPage('patients');
                        }
                        document.getElementById('search-form').submit();
                    }
                }, 500);
            });
        }
        
        // Auto-focus search input when on patient page
        if (currentPage === 'patients') {
            setTimeout(() => {
                if (searchInput) {
                    searchInput.focus();
                }
            }, 100);
        }
        
        // Highlight search terms in table (if you want to implement)
        function highlightSearchTerms(searchTerm) {
            if (!searchTerm) return;
            
            const tableCells = document.querySelectorAll('.patient-table td');
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            
            tableCells.forEach(cell => {
                const originalHTML = cell.innerHTML;
                const highlightedHTML = originalHTML.replace(regex, '<mark style="background-color: #ffeaa7; padding: 2px 4px; border-radius: 3px;">$1</mark>');
                cell.innerHTML = highlightedHTML;
            });
        }
        
        // Call highlight function if search term exists
        <?php if (!empty($searchTerm)): ?>
            highlightSearchTerms('<?php echo addslashes($searchTerm); ?>');
        <?php endif; ?>
        
        // ========== HELPER FUNCTIONS ==========
        
        function resetPatientForm() {
            const form = document.getElementById('patient-form');
            if (form) {
                form.reset();
                document.getElementById('patient-age').value = '';
                document.getElementById('patient-modal-title').innerHTML = '<i class="fas fa-user-plus"></i> Add New Patient';
                document.getElementById('patient-edit-id').value = '';
                
                // Set today's date as max for DOB
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('patient-dob').setAttribute('max', today);
                
                // Set default date registered to today
                document.getElementById('patient-registered').value = today;
                
                // Clear any dashes from contact field
                const contactInput = document.getElementById('patient-contact');
                if (contactInput) {
                    contactInput.value = '';
                }
            }
        }
        
        // Initialize page based on URL
        function initializePage() {
            const urlParams = new URLSearchParams(window.location.search);
            
            // If there's a search parameter, show patients page
            if (urlParams.has('search')) {
                currentPage = 'patients';
                showPage('patients');
                
                // Update sidebar active state
                document.querySelectorAll('.sidebar-item').forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('data-page') === 'patients') {
                        item.classList.add('active');
                    }
                });
            }
        }
        
        // Initialize page on load
        initializePage();
    });
    
    // Auto-calculate age on page load if DOB is filled
    window.addEventListener('load', function() {
        const dobInput = document.getElementById('patient-dob');
        const ageInput = document.getElementById('patient-age');
        
        if (dobInput && dobInput.value && ageInput) {
            dobInput.dispatchEvent(new Event('change'));
        }
    });
    </script>
</body>
</html>