<?php
require_once __DIR__ . '/../config/database.php';
$conn = getConnection();
// A connection-local temporary table isolates this test from saved settings.
if (!$conn->query('CREATE TEMPORARY TABLE settings_regression LIKE system_settings')) throw new RuntimeException($conn->error);
if (!$conn->query("INSERT INTO settings_regression (setting_key, setting_value) VALUES ('site_name', 'Preserve this custom name')")) throw new RuntimeException($conn->error);
$source = file_get_contents(__DIR__ . '/../settings.php');
$start = strpos($source, '// Repair partially populated installations');
$end = strpos($source, '// Handle settings update', $start);
$seed = str_replace('system_settings', 'settings_regression', substr($source, $start, $end - $start));
eval($seed);
eval($seed);
$rows = [];
$result = $conn->query('SELECT setting_key, setting_value FROM settings_regression');
while ($row = $result->fetch_assoc()) $rows[$row['setting_key']] = $row['setting_value'];
if (($rows['site_name'] ?? '') !== 'Preserve this custom name') throw new RuntimeException('Saved settings overwritten.');
foreach (['allow_registration' => '1', 'enable_charts' => '1', 'maintenance_mode' => '0', 'email_notifications' => '1'] as $key => $expected) {
    if (($rows[$key] ?? null) !== $expected) throw new RuntimeException('Missing default: ' . $key);
    if (strpos($source, '<input type="hidden" name="settings[' . $key . ']" value="0">') === false) throw new RuntimeException('Unchecked toggle cannot save: ' . $key);
}
$save = $conn->prepare('UPDATE settings_regression SET setting_value = ?, updated_by = ? WHERE setting_key = ?');
if (!$save) throw new RuntimeException('Settings save schema is incompatible: ' . $conn->error);
$off = '0'; $adminId = 0; $key = 'enable_charts';
$save->bind_param('sis', $off, $adminId, $key);
if (!$save->execute()) throw new RuntimeException('Settings save failed.');
$saved = $conn->query("SELECT setting_value FROM settings_regression WHERE setting_key = 'enable_charts'")->fetch_assoc();
if ($saved['setting_value'] !== '0') throw new RuntimeException('Off setting was not saved.');
$conn->close();
echo "PASS: missing settings repaired, saved values preserved, repeat initialization safe, off values submitted and saved.\n";
