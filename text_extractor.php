<?php

function extractTextFromFile(string $filePath): ?string
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return null;
    }

    $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    switch ($fileExtension) {
        case 'txt':
            return file_get_contents($filePath);

        case 'docx':
            return getTextFromDocx($filePath);
            
        case 'pdf':
            // PDF extraction is complex and requires a dedicated library like smalot/pdfparser.
            // This pure PHP approach cannot handle it. Returning empty string.
            return 'PDF content could not be extracted automatically.';

        case 'doc':
            // Old .doc format is a proprietary binary and cannot be reliably read without external tools.
            return 'DOC content could not be extracted automatically.';

        default:
            return 'Unsupported file type for text extraction.';
    }
}

/**
 * Extracts text from a .docx file.
 * A .docx file is a zip archive containing XML files.
 * The main content is in 'word/document.xml'.
 */
function getTextFromDocx(string $filePath): string
{
    $zip = new ZipArchive();
    $content = '';

    if ($zip->open($filePath) === TRUE) {
        // The main content of a DOCX file is stored here
        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xmlContent) {
            // Remove all XML tags to get the plain text
            $content = strip_tags($xmlContent);
        }
    }
    
    return $content;
}