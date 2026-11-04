<?php
// Read the current bulk_upload_parse.php
$content = file_get_contents('bulk_upload_parse.php');

// Replace the extractTextFromDOC function with enhanced version
$newFunction = '
function extractTextFromDOC($filePath) {
    // Method 1: Use antiword (if installed - fastest)
    if (function_exists(\'shell_exec\')) {
        $text = @shell_exec("antiword \\"$filePath\\" 2>/dev/null");
        if ($text) return $text;
    }
    
    // Method 2: Use PHPWord library (if installed)
    if (class_exists(\'\\PhpOffice\\PhpWord\\IOFactory\')) {
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
    if (function_exists(\'shell_exec\')) {
        // Try using catdoc as alternative
        $text = @shell_exec("catdoc \\"$filePath\\" 2>/dev/null");
        if ($text) return $text;
        
        // Try using wvText (from wv package)
        $text = @shell_exec("wvText \\"$filePath\\" 2>/dev/null");
        if ($text) return $text;
    }
    
    // Method 4: Last resort - return empty
    return \'\';
}
';

// Find and replace the old function
$pattern = '/function extractTextFromDOC\(.*?\)\s*\{.*?\}/s';
if (preg_match($pattern, $content)) {
    $content = preg_replace($pattern, $newFunction, $content);
    file_put_contents('bulk_upload_parse.php', $content);
    echo "✓ Updated extractTextFromDOC function\n";
} else {
    echo "⚠ Function not found, adding manually...\n";
    
    // Find where to insert (after extractTextFromDOCX function)
    $insertPos = strpos($content, 'function extractTextFromDOCX');
    if ($insertPos !== false) {
        $endPos = strpos($content, '}', $insertPos) + 1;
        $content = substr($content, 0, $endPos) . "\n\n" . $newFunction . "\n" . substr($content, $endPos);
        file_put_contents('bulk_upload_parse.php', $content);
        echo "✓ Added extractTextFromDOC function\n";
    } else {
        echo "✗ Could not find insertion point\n";
    }
}

// Also update the extractText function to handle .doc properly
$content = file_get_contents('bulk_upload_parse.php');
$content = str_replace(
    "case 'doc':\n                return extractTextFromDOC($filePath);",
    "case 'doc':\n                return extractTextFromDOC($filePath);",
    $content
);
file_put_contents('bulk_upload_parse.php', $content);

echo "✓ .DOC parsing updated\n";
?>
