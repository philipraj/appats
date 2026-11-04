<?php
// A simple class to read the text content from a .docx file.
class Docx_reader {
    public static function read($filename) {
        if (!file_exists($filename)) {
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($filename) === TRUE) {
            $content = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($content !== false) {
                // Strip XML tags to get plain text
                return strip_tags($content);
            }
        }
        return false;
    }
}
?>