<?php
function parseDocFile($filePath) {
    // Try multiple methods
    
    // 1. antiword (if hosting installs it)
    if (function_exists('shell_exec')) {
        $text = @shell_exec("antiword \"$filePath\" 2>/dev/null");
        if ($text && strlen($text) > 10) return $text;
    }
    
    // 2. catdoc alternative
    if (function_exists('shell_exec')) {
        $text = @shell_exec("catdoc \"$filePath\" 2>/dev/null");
        if ($text && strlen($text) > 10) return $text;
    }
    
    // 3. If all else fails, extract filename as name
    $filename = basename($filePath);
    $name = preg_replace('/[^a-zA-Z]/', ' ', $filename);
    $name = preg_replace('/\s+/', ' ', $name);
    $name = trim($name);
    
    return "Name: " . ucwords($name) . "\n\nNote: .DOC file could not be parsed.\nPlease ask hosting to install 'antiword' or convert to PDF/DOCX.";
}
?>
