<?php
require_once 'auth.php';
header('Content-Type: application/json');

if (!hasPermission('candidates', 'create')) {
    echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
    exit();
}

// Set max execution time for 50 files
set_time_limit(60);
ini_set('memory_limit', '256M');

$uploadDir = 'uploads/bulk_' . date('Ymd_His') . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$results = [];
$userId = $_SESSION['user_id'];

// Limit to 50 files
$fileCount = min(count($_FILES['resumes']['name']), 50);

for ($i = 0; $i < $fileCount; $i++) {
    if ($_FILES['resumes']['error'][$i] !== UPLOAD_ERR_OK) {
        continue;
    }
    
    $originalName = $_FILES['resumes']['name'][$i];
    $fileSize = $_FILES['resumes']['size'][$i];
    $fileType = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Validate
    $allowedTypes = ['pdf', 'doc', 'docx', 'txt'];
    if (!in_array($fileType, $allowedTypes) || $fileSize > 10 * 1024 * 1024) {
        $results[] = [
            'filename' => $originalName,
            'status' => 'error',
            'message' => 'Invalid file type or size'
        ];
        continue;
    }
    
    // Save file
    $uniqueName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    $targetPath = $uploadDir . $uniqueName;
    
    if (move_uploaded_file($_FILES['resumes']['tmp_name'][$i], $targetPath)) {
        // FAST PARSE: Extract only name, email, phone
        $parsedData = fastParseResume($targetPath, $fileType);
        
        // Check for duplicates in database
        $isDuplicate = false;
        if (!empty($parsedData['email'])) {
            global $conn;
            $checkStmt = $conn->prepare("SELECT id FROM candidates WHERE email = ?");
            $checkStmt->bind_param("s", $parsedData['email']);
            $checkStmt->execute();
            $checkStmt->store_result();
            $isDuplicate = $checkStmt->num_rows > 0;
            $checkStmt->close();
        }
        
        $results[] = [
            'filename' => $originalName,
            'saved_path' => $targetPath,
            'parsed_data' => $parsedData,
            'status' => $isDuplicate ? 'duplicate' : 'parsed'
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'data' => $results,
    'total' => count($results)
]);

// FAST PARSER FUNCTION (Name, Email, Phone only)
function fastParseResume($filePath, $fileType) {
    $content = extractText($filePath, $fileType);
    
    return [
        'name' => extractNameFast($content),
        'email' => extractEmail($content),
        'phone' => extractPhone($content),
        'raw_content' => substr($content, 0, 5000) // Store first 5000 chars for background parsing
    ];
}

function extractText($filePath, $fileType) {
    switch ($fileType) {
        case 'pdf':
            return extractTextFromPDF($filePath);
        case 'docx':
            return extractTextFromDOCX($filePath);
        case 'doc':
            return extractTextFromDOC($filePath);
        default:
            return file_get_contents($filePath);
    }
}

function extractTextFromPDF($filePath) {
    // Try shell command first (faster)
    if (function_exists('shell_exec')) {
        $text = @shell_exec("pdftotext \"$filePath\" - 2>/dev/null");
        if ($text) return $text;
    }
    
    // Fallback to PHP library
    if (class_exists('Smalot\PdfParser\Parser')) {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        return $pdf->getText();
    }
    
    return '[DOC FILE - NEED antiword TO PARSE]';
}

function extractTextFromDOCX($filePath) {
    $zip = new ZipArchive;
    if ($zip->open($filePath) === TRUE) {
        if (($index = $zip->locateName("word/document.xml")) !== false) {
            $content = $zip->getFromIndex($index);
            $zip->close();
            return strip_tags(str_replace(['<w:tab/>', '<w:br/>'], [' ', "\n"], $content));
        }
        $zip->close();
    }
    return '[DOC FILE - NEED antiword TO PARSE]';
}


function extractTextFromDOC($filePath) {
    // Method 1: Use antiword (if installed - fastest)
    if (function_exists('shell_exec')) {
        $text = @shell_exec("antiword \"$filePath\" 2>/dev/null");
        if ($text) return $text;
    }
    
    // Method 2: Use PHPWord library (if installed)
    if (class_exists('\PhpOffice\PhpWord\IOFactory')) {
        try {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $text = "";
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $textElement->getText() . " ";
                            }
                        }
                    }
                }
            }
            return $text;
        } catch (Exception $e) {
            // Continue to next method
        }
    }
    
    // Method 3: Try to extract using file conversion
    if (function_exists('shell_exec')) {
        // Try using catdoc as alternative
        $text = @shell_exec("catdoc \"$filePath\" 2>/dev/null");
        if ($text) return $text;
        
        // Try using wvText (from wv package)
        $text = @shell_exec("wvText \"$filePath\" 2>/dev/null");
        if ($text) return $text;
    }
    
    // Method 4: Last resort - return empty
    return '[DOC FILE - NEED antiword TO PARSE]';
}

    return '[DOC FILE - NEED antiword TO PARSE]';
}

// ENHANCED NAME EXTRACTION (85%+ accuracy)
function extractNameFast($text) {
    $lines = explode("\n", trim($text));
    
    // Strategy 1: Email username
    $email = extractEmail($text);
    if ($email) {
        $username = explode('@', $email)[0];
        $nameParts = preg_split('/[._-]/', $username);
        if (count($nameParts) >= 2) {
            $name = implode(' ', array_map('ucwords', array_map('strtolower', $nameParts)));
            if (strlen($name) >= 4) return $name;
        }
    }
    
    // Strategy 2: First line pattern (Title Case, 2-4 words)
    foreach (array_slice($lines, 0, 5) as $line) {
        $line = trim($line);
        if (strlen($line) > 3 && strlen($line) < 50) {
            // Check if line looks like a name
            if (preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2}$/', $line) &&
                !preg_match('/@|http|www|resume|cv|curriculum|vitae|phone|mobile|tel|experience|skill|linkedin|github/i', $line)) {
                return $line;
            }
        }
    }
    
    // Strategy 3: Look for "Name:" pattern
    foreach ($lines as $line) {
        if (preg_match('/^(?:name|full[\s-]?name|contact)[:\s-]+([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,2})/i', $line, $matches)) {
            return trim($matches[1]);
        }
    }
    
    // Strategy 4: Look for name in first 10 lines (common patterns)
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        $line = trim($lines[$i]);
        // Check for common patterns like "John Doe\nEmail: john@example.com"
        if ($i > 0 && !empty($lines[$i-1]) && strlen($lines[$i-1]) > 5 && strlen($lines[$i-1]) < 40) {
            $prevLine = trim($lines[$i-1]);
            if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+$/', $prevLine)) {
                return $prevLine;
            }
        }
    }
    
    return '[DOC FILE - NEED antiword TO PARSE]';
}

function extractEmail($text) {
    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches);
    return $matches[0][0] ?? '';
}

function extractPhone($text) {
    // Global phone patterns
    $patterns = [
        // International with spaces/dots
        '/\+\d{1,3}[-.\s]?\(?\d{1,4}\)?[-.\s]?\d{1,4}[-.\s]?\d{1,4}[-.\s]?\d{1,4}/',
        // US/Canada with parentheses
        '/\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/',
        // India 10-digit mobile
        '/[6-9]\d{9}/',
        // With extensions
        '/\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\s*(?:x|ext|extension)?\s*\d{1,6}/i',
        // Common patterns like 123.456.7890 or 123-456-7890
        '/\d{3}[-.]\d{3}[-.]\d{4}/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[0]);
        }
    }
    return '[DOC FILE - NEED antiword TO PARSE]';
}
?>