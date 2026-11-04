<?php
require_once 'auth.php';
header('Content-Type: application/json');

if (!hasPermission('candidates', 'create')) {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$candidates = $data['candidates'] ?? [];

if (empty($candidates)) {
    echo json_encode(['status' => 'error', 'message' => 'No candidates to save']);
    exit();
}

global $conn;
$userId = $_SESSION['user_id'];
$savedCount = 0;
$candidateIds = [];

foreach ($candidates as $candidate) {
    // Skip if missing required fields
    if (empty($candidate['email']) || empty($candidate['name']) || strlen($candidate['name']) < 2) {
        continue;
    }
    
    // Check for duplicates
    $checkStmt = $conn->prepare("SELECT id FROM candidates WHERE email = ?");
    $checkStmt->bind_param("s", $candidate['email']);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        continue;
    }
    $checkStmt->close();
    
    // Move file to main uploads directory
    $mainUploadDir = 'uploads/';
    $newFilename = uniqid() . '_' . basename($candidate['filename']);
    $newPath = $mainUploadDir . $newFilename;
    
    if (isset($candidate['saved_path']) && file_exists($candidate['saved_path'])) {
        if (rename($candidate['saved_path'], $newPath)) {
            // Insert into database (same structure as your add_candidate.php)
            $stmt = $conn->prepare("INSERT INTO candidates 
                (candidate_name, email, phone, resume_filename, created_by_id, created_at, parsed_data) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?)");
            
            $parsedData = json_encode([
                'raw_content' => $candidate['raw_content'] ?? '',
                'upload_method' => 'bulk_upload',
                'initial_parse' => [
                    'name' => $candidate['name'],
                    'email' => $candidate['email'],
                    'phone' => $candidate['phone']
                ]
            ], JSON_UNESCAPED_UNICODE);
            
            $stmt->bind_param("ssssis", 
                trim($candidate['name']),
                trim($candidate['email']),
                trim($candidate['phone'] ?? ''),
                $newFilename,
                $userId,
                $parsedData
            );
            
            if ($stmt->execute()) {
                $savedCount++;
                $candidateIds[] = $stmt->insert_id;
                
                // Clean up temporary file
                @unlink($candidate['saved_path']);
            } else {
                // If insert fails, delete the moved file
                @unlink($newPath);
            }
            $stmt->close();
        }
    } else {
        // File doesn't exist, insert without file
        $stmt = $conn->prepare("INSERT INTO candidates 
            (candidate_name, email, phone, created_by_id, created_at, parsed_data) 
            VALUES (?, ?, ?, ?, NOW(), ?)");
        
        $parsedData = json_encode([
            'upload_method' => 'bulk_upload',
            'initial_parse' => [
                'name' => $candidate['name'],
                'email' => $candidate['email'],
                'phone' => $candidate['phone']
            ]
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt->bind_param("sssis", 
            trim($candidate['name']),
            trim($candidate['email']),
            trim($candidate['phone'] ?? ''),
            $userId,
            $parsedData
        );
        
        if ($stmt->execute()) {
            $savedCount++;
            $candidateIds[] = $stmt->insert_id;
        }
        $stmt->close();
    }
}

// Clean up empty temp directory if all files processed
$tempDir = dirname($candidates[0]['saved_path'] ?? '');
if ($tempDir && is_dir($tempDir) && count(scandir($tempDir)) <= 2) {
    @rmdir($tempDir);
}

echo json_encode([
    'status' => 'success',
    'saved_count' => $savedCount,
    'candidate_ids' => $candidateIds,
    'message' => "Saved $savedCount candidates successfully"
]);
?>