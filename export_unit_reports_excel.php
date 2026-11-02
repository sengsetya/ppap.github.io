<?php
/**
 * Controller to Export Unit Performance Report to Microsoft Excel (.xlsx / .xls)
 * Matches the official Royal Government of Cambodia & MPWT formatting
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/excel_exporter.php';
require_once __DIR__ . '/includes/unit_reports_view.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$pdo = get_db_connection();
$level = trim($_GET['level'] ?? 'national');
if (!in_array($level, ['national', 'sub_national', 'all'])) $level = 'national';

$yearParam = $_GET['year'] ?? 'all';
$year = ($yearParam === 'all' || $yearParam === '') ? 'all' : (int)$yearParam;

$quarter = trim($_GET['quarter'] ?? 'all');
if (!in_array($quarter, ['all', 'Q1', 'Q2', 'Q3', 'Q4'])) $quarter = 'all';

$data = get_unit_performance_data($pdo, $level, $year, $quarter);
$units = $data['units'];
$totals = $data['totals'];

$levelLabels = [
    'national'     => 'អង្គភាពថ្នាក់ជាតិ',
    'sub_national' => 'អង្គភាពថ្នាក់ក្រោមជាតិ',
    'all'          => 'អង្គភាពទាំងអស់'
];
$levelKm = $levelLabels[$level] ?? 'អង្គភាពថ្នាក់ជាតិ';
$kmDigits = ['0'=>'០', '1'=>'១', '2'=>'២', '3'=>'៣', '4'=>'៤', '5'=>'៥', '6'=>'៦', '7'=>'៧', '8'=>'៨', '9'=>'៩'];
$kmYear = ($year === 'all') ? 'ទាំងអស់' : strtr((string)$year, $kmDigits);

$filename = 'Report_Performance_' . ucfirst($level) . '_' . ($year === 'all' ? 'All' : $year) . '_' . date('Ymd_His') . '.xls';

$esc = function($v) {
    return htmlspecialchars((string)$v, ENT_XML1, 'UTF-8');
};

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
$xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
$xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
$xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
$xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

$xml .= ' <Styles>' . "\n";
$xml .= '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Kantumruy Pro" ss:Size="10"/></Style>' . "\n";
$xml .= '  <Style ss:ID="sTitle"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Kantumruy Pro" ss:Size="13" ss:Bold="1"/></Style>' . "\n";
$xml .= '  <Style ss:ID="sHeader"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#015bb5"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#015bb5"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#015bb5"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#015bb5"/></Borders><Font ss:FontName="Kantumruy Pro" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#0275D8" ss:Pattern="Solid"/></Style>' . "\n";
$xml .= '  <Style ss:ID="sCell"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Font ss:FontName="Kantumruy Pro" ss:Size="10"/></Style>' . "\n";
$xml .= '  <Style ss:ID="sCenter"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders><Font ss:FontName="Kantumruy Pro" ss:Size="10"/></Style>' . "\n";
$xml .= '  <Style ss:ID="sTotal"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D97706"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D97706"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D97706"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D97706"/></Borders><Font ss:FontName="Kantumruy Pro" ss:Size="10" ss:Bold="1" ss:Color="#000000"/><Interior ss:Color="#F59E0B" ss:Pattern="Solid"/></Style>' . "\n";
$xml .= ' </Styles>' . "\n";

$xml .= ' <Worksheet ss:Name="' . $esc($levelKm) . '">' . "\n";
$xml .= '  <Table ss:DefaultRowHeight="22">' . "\n";
$xml .= '   <Column ss:Width="45"/>' . "\n";  // A: No
$xml .= '   <Column ss:Width="280"/>' . "\n"; // B: Name
$xml .= '   <Column ss:Width="80"/>' . "\n";  // C: Abbr
$xml .= '   <Column ss:Width="100"/>' . "\n"; // D: Planned
$xml .= '   <Column ss:Width="95"/>' . "\n";  // E: Pending
$xml .= '   <Column ss:Width="95"/>' . "\n";  // F: In Progress
$xml .= '   <Column ss:Width="95"/>' . "\n";  // G: Completed
$xml .= '   <Column ss:Width="90"/>' . "\n";  // H: Score

// Title
$xml .= '   <Row ss:Height="26">' . "\n";
$xml .= '    <Cell ss:MergeAcross="7" ss:StyleID="sTitle"><Data ss:Type="String">តារាងតាមដានវឌ្ឍនភាពនៃការអនុវត្តផែនការសកម្មភាពអាទិភាព ' . $esc($levelKm) . ' ឆ្នាំ ' . $kmYear . '</Data></Cell>' . "\n";
$xml .= '   </Row>' . "\n";
$xml .= '   <Row ss:Height="10"/>' . "\n";

// Header
$xml .= '   <Row ss:Height="32">' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">ល.រ</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">អង្គភាពអនុវត្ត</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">អក្សរកាត់</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">ចំនួនសកម្មភាព</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">មិនទាន់អនុវត្ត</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">កំពុងអនុវត្ត</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">សម្រេចជាស្ថាពរ</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">ពិន្ទុមធ្យម</Data></Cell>' . "\n";
$xml .= '   </Row>' . "\n";

// Data rows
$counter = 1;
foreach ($units as $u) {
    $scText = ($u['score'] !== null) ? ($u['score'] . '%') : '-';
    $pendText = ((int)$u['pending'] > 0) ? to_km_digits($u['pending']) : '-';
    $progText = ((int)$u['in_progress'] > 0) ? to_km_digits($u['in_progress']) : '-';
    $compText = ((int)$u['completed'] > 0) ? to_km_digits($u['completed']) : '-';

    $xml .= '   <Row ss:Height="24">' . "\n";
    $xml .= '    <Cell ss:StyleID="sCenter"><Data ss:Type="String">' . to_km_digits($counter) . '</Data></Cell>' . "\n";
    $xml .= '    <Cell ss:StyleID="sCell"><Data ss:Type="String">' . $esc($u['name']) . '</Data></Cell>' . "\n";
    $xml .= '    <Cell ss:StyleID="sCenter"><Data ss:Type="String">' . $esc($u['abbr']) . '</Data></Cell>' . "\n";
    $xml .= '    <Cell ss:StyleID="sCenter"><Data ss:Type="String">' . to_km_digits($u['planned']) . '</Data></Cell>' . "\n";
    $xml .= '    <Cell ss:StyleID="sCenter"><Data ss:Type="String">' . $pendText . '</Data></Cell>' . "\n";
    $xml .= '    <Cell ss:StyleID="sCenter"><Data ss:Type="String">' . $progText . '</Data></Cell>' . "\n";
    $xml .= '    <Cell ss:StyleID="sCenter"><Data ss:Type="String">' . $compText . '</Data></Cell>' . "\n";
    $xml .= '    <Cell ss:StyleID="sCenter"><Data ss:Type="String">' . to_km_digits($scText) . '</Data></Cell>' . "\n";
    $xml .= '   </Row>' . "\n";
    $counter++;
}

// Total footer row
$xml .= '   <Row ss:Height="26">' . "\n";
$xml .= '    <Cell ss:MergeAcross="1" ss:StyleID="sTotal"><Data ss:Type="String">សរុបរួម' . $esc($levelKm) . '</Data></Cell>' . "\n";
$xml .= '    <Cell ss:Index="3" ss:StyleID="sTotal"><Data ss:Type="String"></Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sTotal"><Data ss:Type="String">' . to_km_digits($totals['planned']) . '</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sTotal"><Data ss:Type="String">' . to_km_digits($totals['pending']) . '</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sTotal"><Data ss:Type="String">' . to_km_digits($totals['in_progress']) . '</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sTotal"><Data ss:Type="String">' . to_km_digits($totals['completed']) . '</Data></Cell>' . "\n";
$xml .= '    <Cell ss:StyleID="sTotal"><Data ss:Type="String">' . to_km_digits($totals['avg_score'] . '%') . '</Data></Cell>' . "\n";
$xml .= '   </Row>' . "\n";

$xml .= '  </Table>' . "\n";
$xml .= ' </Worksheet>' . "\n";
$xml .= '</Workbook>' . "\n";

while (ob_get_level()) {
    ob_end_clean();
}

if (!headers_sent()) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . strlen($xml));
}

echo $xml;
exit;

