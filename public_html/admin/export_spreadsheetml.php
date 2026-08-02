<?php
// helper: build SpreadsheetML (Excel 2003 XML) for fallback when ZipArchive not available
function buildSpreadsheetMl(array $sheetsRows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">\n';
    foreach ($sheetsRows as $sheetName => $rows) {
        $xml .= '<Worksheet ss:Name="' . htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '">\n<Table>\n';
        foreach ($rows as $r) {
            $xml .= '<Row>\n';
            foreach ($r as $cell) {
                $type = is_numeric($cell) ? 'Number' : 'String';
                $value = $cell === null ? '' : $cell;
                $xml .= '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</Data></Cell>\n';
            }
            $xml .= '</Row>\n';
        }
        $xml .= '</Table>\n</Worksheet>\n';
    }
    $xml .= '</Workbook>';
    return $xml;
}
