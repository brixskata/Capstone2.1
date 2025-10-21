<?php
echo "PHP Error Log Location: " . ini_get('error_log') . "\n";
echo "Log Errors: " . ini_get('log_errors') . "\n";
echo "Display Errors: " . ini_get('display_errors') . "\n";

// Show all error-related settings
echo "\nAll Error Settings:\n";
$error_settings = [
    'error_log',
    'log_errors', 
    'display_errors',
    'error_reporting',
    'log_errors_max_len'
];

foreach ($error_settings as $setting) {
    echo "$setting: " . ini_get($setting) . "\n";
}
?>
