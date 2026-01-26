<?php
// Test SNMP Extension
echo "=== SNMP Extension Test ===\n";

// Check if SNMP extension is loaded
if (extension_loaded('snmp')) {
    echo "✓ SNMP extension is loaded\n";
} else {
    echo "✗ SNMP extension is NOT loaded\n";
    exit(1);
}

// Check SNMP functions
$functions = ['snmp2_get', 'snmp2_walk', 'snmp2_real_walk'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "✓ Function $func is available\n";
    } else {
        echo "✗ Function $func is NOT available\n";
    }
}

// Test basic SNMP connection (replace with your printer IP)
$test_ip = '192.168.1.100'; // Change this to your printer IP
$community = 'public';

echo "\n=== Testing SNMP Connection ===\n";
echo "Testing IP: $test_ip\n";
echo "Community: $community\n";

// Set SNMP options
snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
snmp_set_quick_print(1);

// Test connection
$result = @snmp2_get($test_ip, $community, '1.3.6.1.2.1.1.1.0', 1000000, 1);

if ($result === false) {
    $error = error_get_last();
    echo "✗ SNMP connection failed\n";
    if ($error) {
        echo "Error: " . $error['message'] . "\n";
    }
} else {
    echo "✓ SNMP connection successful\n";
    echo "System Description: " . trim($result, '"') . "\n";
}

echo "\n=== PHP Info ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "OS: " . php_uname() . "\n";

// Check loaded extensions
$extensions = get_loaded_extensions();
if (in_array('snmp', $extensions)) {
    echo "SNMP extension version: " . phpversion('snmp') . "\n";
}
?>