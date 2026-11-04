<?php
// This runs in background to parse skills, experience, etc.
require_once 'auth.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit();
}

// Get input data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$candidateIds = $data['candidate_ids'] ?? [];

if (empty($candidateIds)) {
    echo json_encode(['status' => 'error', 'message' => 'No candidate IDs provided']);
    exit();
}

// Set for background execution
ignore_user_abort(true);
set_time_limit(300); // 5 minutes
session_write_close();

// Send immediate response to client
ob_end_clean();
header("Connection: close");
header("Content-Length: 0");
ob_start();
echo json_encode(['status' => 'started', 'message' => 'Background parsing started']);
header("Content-Length: " . ob_get_length());
ob_end_flush();
flush();

// Now process in background
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Process each candidate
global $conn;
foreach ($candidateIds as $candidateId) {
    // Get candidate data
    $stmt = $conn->prepare("SELECT resume_filename, parsed_data FROM candidates WHERE id = ?");
    $stmt->bind_param("i", $candidateId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result) {
        $filePath = 'uploads/' . $result['resume_filename'];
        $parsedData = json_decode($result['parsed_data'], true);
        $rawContent = $parsedData['raw_content'] ?? '';
        
        $contentToParse = '';
        if (file_exists($filePath)) {
            $fileType = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $contentToParse = extractTextForBackground($filePath, $fileType);
        } elseif (!empty($rawContent)) {
            $contentToParse = $rawContent;
        }
        
        if (!empty($contentToParse)) {
            // DEEP PARSING: Extract skills, experience, education
            $deepData = deepParseResume($contentToParse);
            
            // Update candidate with full data
            $updateStmt = $conn->prepare("UPDATE candidates SET 
                skills = ?, 
                total_experience = ?,
                education_summary = ?,
                current_location = ?,
                parsed_data = ?,
                last_parsed_at = NOW()
                WHERE id = ?");
            
            // Merge existing parsed data with new deep data
            $mergedData = array_merge($parsedData, [
                'deep_parse' => $deepData,
                'parsed_at' => date('Y-m-d H:i:s')
            ]);
            
            $updateStmt->bind_param("sssssi",
                $deepData['skills'],
                $deepData['experience'],
                $deepData['education'],
                $deepData['location'],
                json_encode($mergedData, JSON_UNESCAPED_UNICODE),
                $candidateId
            );
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
    
    // Small delay to prevent server overload
    usleep(50000); // 0.05 second
}

// Log completion
error_log("Background parsing completed for " . count($candidateIds) . " candidates");

exit();

function extractTextForBackground($filePath, $fileType) {
    // Reuse extraction functions from bulk_upload_parse.php
    if (file_exists('bulk_upload_parse.php')) {
        include_once 'bulk_upload_parse.php';
        return extractText($filePath, $fileType);
    }
    return '';
}

function deepParseResume($content) {
    $skills = extractSkills($content);
    $experience = extractExperience($content);
    $education = extractEducation($content);
    $location = extractLocation($content);
    
    return [
        'skills' => implode(', ', $skills),
        'experience' => $experience,
        'education' => implode('; ', $education),
        'location' => $location,
        'parsed_at' => date('Y-m-d H:i:s')
    ];
}

function extractSkills($text) {
    $commonSkills = [
        // Programming Languages
        'PHP', 'JavaScript', 'Python', 'Java', 'C#', 'C++', 'Ruby', 'Go', 'Swift', 'Kotlin',
        'TypeScript', 'R', 'Scala', 'Perl', 'Rust', 'Dart',
        
        // Web Technologies
        'HTML', 'CSS', 'HTML5', 'CSS3', 'SASS', 'SCSS', 'LESS',
        'React', 'Angular', 'Vue.js', 'jQuery', 'Bootstrap', 'Tailwind CSS',
        'Node.js', 'Express.js', 'Next.js', 'Nuxt.js', 'Laravel', 'Symfony',
        'Django', 'Flask', 'Spring', 'ASP.NET', 'Ruby on Rails',
        
        // Databases
        'MySQL', 'PostgreSQL', 'MongoDB', 'SQLite', 'Oracle', 'SQL Server',
        'Redis', 'Cassandra', 'Elasticsearch', 'Firebase', 'DynamoDB',
        
        // Cloud & DevOps
        'AWS', 'Azure', 'Google Cloud', 'Docker', 'Kubernetes', 'Jenkins',
        'CI/CD', 'Terraform', 'Ansible', 'Git', 'GitLab', 'GitHub',
        'Linux', 'Unix', 'Shell Scripting',
        
        // Mobile
        'React Native', 'Flutter', 'Android', 'iOS', 'Xamarin',
        
        // Other
        'REST API', 'GraphQL', 'Microservices', 'Agile', 'Scrum',
        'Machine Learning', 'AI', 'Data Science', 'Big Data', 'Tableau',
        'JIRA', 'Confluence', 'Figma', 'Adobe Creative Suite'
    ];
    
    $foundSkills = [];
    $text = strtoupper($text);
    
    foreach ($commonSkills as $skill) {
        if (strpos($text, strtoupper($skill)) !== false) {
            $foundSkills[] = $skill;
        }
    }
    
    return array_unique($foundSkills);
}

function extractExperience($text) {
    // Look for years of experience
    if (preg_match('/(\d+)\+?\s*(?:years?|yrs?)\s*(?:of\s*)?experience/i', $text, $matches)) {
        return $matches[1] . ' years';
    }
    
    // Look for date ranges
    if (preg_match('/(\d{4})\s*[-–]\s*(?:Present|Current|\d{4})/', $text, $matches)) {
        $startYear = (int)$matches[1];
        $currentYear = (int)date('Y');
        $years = $currentYear - $startYear;
        return $years . ' years';
    }
    
    // Look for "X+ years" pattern
    if (preg_match('/(\d+)\+?\s*years?/i', $text, $matches)) {
        return $matches[1] . '+ years';
    }
    
    return '';
}

function extractEducation($text) {
    $degrees = ['B\.?S\.?', 'B\.?A\.?', 'B\.?Tech', 'B\.?E\.?', 'B\.?Com', 'B\.?Sc',
                'M\.?S\.?', 'M\.?A\.?', 'M\.?Tech', 'M\.?E\.?', 'MBA', 'PhD', 'Doctorate',
                'Bachelor', 'Master', 'Diploma', 'Certificate'];
    
    $education = [];
    
    // Look for degree patterns
    foreach ($degrees as $degree) {
        if (preg_match("/$degree(?:\s*(?:in|of)\s*[\w\s]+)?/i", $text, $matches)) {
            $education[] = $matches[0];
        }
    }
    
    // Look for university/college names
    if (preg_match('/(?:University|College|Institute|School)[\s\w,.-]+/i', $text, $matches)) {
        $education[] = trim($matches[0]);
    }
    
    return array_slice(array_unique($education), 0, 3);
}

function extractLocation($text) {
    // Look for city, state patterns
    if (preg_match('/[A-Z][a-z]+(?:[ -][A-Z][a-z]+)*,\s*[A-Z]{2}/', $text, $matches)) {
        return $matches[0];
    }
    
    // Look for city names
    $majorCities = ['New York', 'San Francisco', 'London', 'Bangalore', 'Mumbai', 'Delhi',
                   'Chicago', 'Los Angeles', 'Boston', 'Austin', 'Seattle', 'Toronto'];
    
    foreach ($majorCities as $city) {
        if (stripos($text, $city) !== false) {
            return $city;
        }
    }
    
    return '';
}
?>