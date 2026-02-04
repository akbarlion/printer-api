<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Snmp_service
{
    public function __construct()
    {
        if (!extension_loaded('snmp')) {
            throw new Exception('SNMP extension is not installed or enabled in PHP');
        }
    }

    public function test_connection($ip_address, $community = 'public')
    {
        // Validate input
        if (empty($ip_address)) {
            return [
                'success' => false,
                'message' => 'IP address is required'
            ];
        }

        // Check if SNMP extension is loaded
        if (!extension_loaded('snmp')) {
            return [
                'success' => false,
                'message' => 'SNMP extension is not loaded in PHP'
            ];
        }

        // Check if snmp2_get function exists
        if (!function_exists('snmp2_get')) {
            return [
                'success' => false,
                'message' => 'snmp2_get function is not available'
            ];
        }

        try {
            // Clear any previous SNMP errors
            error_clear_last();

            // Set SNMP options for better error handling
            snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
            snmp_set_quick_print(1);

            // Try connection with shorter timeout first
            $result = @snmp2_get($ip_address, $community, '1.3.6.1.2.1.1.1.0', 1000000, 1);

            if ($result === false) {
                $last_error = error_get_last();
                $error_msg = 'SNMP connection failed';

                if ($last_error && strpos($last_error['message'], 'snmp') !== false) {
                    $error_msg .= ': ' . $last_error['message'];
                }

                return [
                    'success' => false,
                    'message' => $error_msg,
                    'debug' => [
                        'ip' => $ip_address,
                        'community' => $community,
                        'php_error' => $last_error
                    ]
                ];
            }

            // Get additional printer info
            $printer_name = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.1.5.0'); // sysName
            $model = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1'); // hrDeviceDescr

            return [
                'success' => true,
                'message' => 'SNMP connection successful',
                'data' => [
                    'system_description' => trim($result, '"'),
                    'printer_name' => $printer_name,
                    'model' => $model,
                    'ip_address' => $ip_address,
                    'community' => $community
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SNMP connection test failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'debug' => [
                    'ip' => $ip_address,
                    'community' => $community
                ]
            ];
        }
    }

    public function get_printer_info($ip_address, $community = 'public')
    {
        try {
            // Detect printer type first
            $printer_type = $this->detect_printer_type($ip_address, $community);
            
            $info = $this->get_printer_info_universal($ip_address, $community, $printer_type);
            $info['printer_type'] = $printer_type;
            $info['status'] = $this->get_status($ip_address, $community);

            return [
                'success' => true,
                'data' => $info
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function get_printer_details($ip_address, $community = 'public')
    {
        try {
            // Test basic connection first
            $basic_test = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.1.5.0');

            if ($basic_test === null) {
                return [
                    'success' => false,
                    'message' => 'Cannot connect to printer via SNMP'
                ];
            }

            // Detect printer type first
            $printer_type = $this->detect_printer_type($ip_address, $community);
            
            // Build data step by step
            $data = [];

            // 1. Printer Information - Universal OIDs first, then brand-specific
            $data['printer_info'] = $this->get_printer_info_universal($ip_address, $community, $printer_type);

            // 2. Supplies - Based on printer type
            $data['supplies'] = $this->get_supplies_by_type($ip_address, $community, $printer_type);

            // 3. Paper Trays
            $data['paper_trays'] = $this->get_paper_trays($ip_address, $community);

            // 4. Cartridge Information
            $data['cartridge_info'] = $this->get_cartridge_info($ip_address, $community, $printer_type);

            // 5. Memory
            $data['memory'] = $this->get_memory_info($ip_address, $community);

            // 6. Alerts
            $data['alerts'] = $this->get_printer_alerts($ip_address, $community);

            // 7. Add printer type to response
            $data['printer_type'] = $printer_type;

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        } catch (Error $e) {
            return [
                'success' => false,
                'message' => 'Fatal Error: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    public function snmp_get($ip, $community, $oid)
    {
        if (!function_exists('snmp2_get')) {
            return null;
        }

        $result = @snmp2_get($ip, $community, $oid, 1000000, 1); // Faster timeout
        if ($result !== false) {
            // Clean SNMP response format
            $cleaned = trim(trim($result, '"'), ' ');
            $cleaned = preg_replace('/^(STRING|Counter32|INTEGER|Gauge32|TimeTicks):\s*"?/', '', $cleaned);
            $cleaned = trim($cleaned, '"');
            $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);
            return $cleaned;
        }
        return null;
    }

    private function snmp_get_private($ip, $community, $oid)
    {
        if (!function_exists('snmp2_get')) {
            return null;
        }

        $result = @snmp2_get($ip, $community, $oid, 2000000, 2);
        if ($result !== false) {
            // Clean SNMP response format (remove STRING:, Counter32:, etc.)
            $cleaned = trim(trim($result, '"'), ' ');
            // Remove SNMP type prefixes
            $cleaned = preg_replace('/^(STRING|Counter32|INTEGER|Gauge32|TimeTicks):\s*"?/', '', $cleaned);
            $cleaned = trim($cleaned, '"');
            // Remove control characters and null bytes
            $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);
            return $cleaned;
        }
        return null;
    }

    private function snmp_get_with_fallback($ip, $community, $oids)
    {
        if (!is_array($oids)) {
            $oids = [$oids];
        }
        
        foreach ($oids as $oid) {
            $result = $this->snmp_get_private($ip, $community, $oid);
            if ($result !== null && $result !== '' && $result !== '0') {
                return $result;
            }
        }
        
        return null;
    }

    private function get_status($ip, $community)
    {
        // Try multiple status OIDs
        $status_oids = [
            '1.3.6.1.2.1.25.3.5.1.1.1',  // hrDeviceStatus
            '1.3.6.1.4.1.11.2.3.9.4.2.1.2.1.0',  // HP Common
            '1.3.6.1.2.1.43.16.5.1.2.1.1'  // prtAlertSeverityLevel
        ];

        foreach ($status_oids as $oid) {
            $status = $this->snmp_get($ip, $community, $oid);
            if ($status !== null) {
                $status_map = [1 => 'other', 2 => 'unknown', 3 => 'idle', 4 => 'printing', 5 => 'warmup'];
                return $status_map[$status] ?? 'ready';
            }
        }

        return 'ready';
    }

    private function convert_supply_level($level)
    {
        if ($level === null || $level === '') {
            return 'Unknown';
        }

        $level = (int) $level;

        if ($level <= 5) {
            return 'Very Low';
        } elseif ($level <= 15) {
            return 'Low';
        } elseif ($level <= 30) {
            return 'Medium';
        } elseif ($level <= 70) {
            return 'Good';
        } else {
            return 'Full';
        }
    }

    private function format_date($date_string)
    {
        if (empty($date_string) || $date_string === '0' || !is_numeric($date_string)) {
            return 'Not available';
        }

        // Skip single digit numbers like "5"
        if (strlen($date_string) < 6) {
            return 'Not available';
        }

        // If it's in YYYYMMDD format like 20250213
        if (strlen($date_string) === 8) {
            $year = substr($date_string, 0, 4);
            $month = substr($date_string, 4, 2);
            $day = substr($date_string, 6, 2);
            return $day . '/' . $month . '/' . $year;
        }

        // Return as-is if format is unknown but looks like a date
        return $date_string;
    }

    private function get_printer_alerts($ip, $community)
    {
        $alerts = [];

        // Try to get up to 5 alerts
        for ($i = 1; $i <= 5; $i++) {
            $alert = $this->snmp_get($ip, $community, "1.3.6.1.2.1.43.18.1.1.8.1.{$i}");
            if ($alert && $alert !== '') {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    private function get_memory_info($ip, $community)
    {
        // Try basic memory OIDs
        $memory_oids = [
            'hp_onboard' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.4.1.0',
            'prt_memory' => '1.3.6.1.2.1.43.5.1.1.3.1',
            'hr_memory' => '1.3.6.1.2.1.25.2.3.1.5.1'
        ];

        $onboard = null;
        foreach ($memory_oids as $type => $oid) {
            $result = $this->snmp_get($ip, $community, $oid);
            if ($result !== null) {
                $onboard = $result;
                break;
            }
        }

        return [
            'on_board' => $onboard ? $onboard . ' MB' : 'Not available'
        ];
    }

    private function detect_printer_type($ip, $community)
    {
        // Get system description to detect printer type
        $sys_desc = $this->snmp_get($ip, $community, '1.3.6.1.2.1.1.1.0');
        $model = $this->snmp_get($ip, $community, '1.3.6.1.2.1.25.3.2.1.3.1');
        
        if ($sys_desc || $model) {
            $desc_lower = strtolower($sys_desc . ' ' . $model);
            
            // Check for inkjet keywords
            if (preg_match('/(inkjet|deskjet|officejet|photosmart|envy|ink|cartridge)/i', $desc_lower)) {
                return 'inkjet';
            }
            
            // Check for laser keywords
            if (preg_match('/(laser|laserjet|toner|mono|color laser)/i', $desc_lower)) {
                return 'laser';
            }
        }
        
        // Try to detect by checking supply types
        $supply_desc = $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.6.1.1');
        if ($supply_desc) {
            if (preg_match('/(ink|cartridge)/i', $supply_desc)) {
                return 'inkjet';
            }
            if (preg_match('/(toner)/i', $supply_desc)) {
                return 'laser';
            }
        }
        
        return 'unknown';
    }

    private function get_printer_info_universal($ip, $community, $printer_type)
    {
        $info = [];
        
        // Berdasarkan hasil SNMP walk HP printer
        $info['name'] = $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.5.1.1.16.1') ?: 'Unknown'; // ANIS-PRINTING
        $info['serial_number'] = $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.5.1.1.17.1') ?: 'Unknown'; // CN22E370TR
        $info['model'] = $this->snmp_get($ip, $community, '1.3.6.1.2.1.25.3.2.1.3.1') ?: 'Unknown';
        $info['status'] = $this->get_status($ip, $community);
        
        // Page counter dari marker life count
        $info['engine_cycles'] = $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1') ?: '0';
        
        return $info;
    }

    private function get_supplies_by_type($ip, $community, $printer_type)
    {
        $supplies = [];
        
        if ($printer_type === 'inkjet') {
            // HP Inkjet supplies - berdasarkan hasil SNMP walk
            $supplies = [
                'cyan' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.1'),
                'magenta' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.2'),
                'yellow' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.3'),
                'black' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.4')
            ];
            
            // Convert to numeric values
            foreach ($supplies as $color => $level) {
                $supplies[$color] = ($level !== null && is_numeric($level)) ? (int) $level : 'Unknown';
            }
        } else {
            // Laser printer supplies
            $level = $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.1');
            $supplies['black'] = ($level !== null && is_numeric($level)) ? (int) $level : 'Unknown';
        }
        
        return $supplies;
    }

    private function get_paper_trays($ip, $community)
    {
        // Try to get paper tray information
        $trays = [];
        
        // Standard paper tray OIDs
        $tray_oids = [
            'tray_1_size' => '1.3.6.1.2.1.43.8.2.1.13.1.1',
            'tray_1_type' => '1.3.6.1.2.1.43.8.2.1.12.1.1',
            'tray_2_size' => '1.3.6.1.2.1.43.8.2.1.13.1.2',
            'tray_2_type' => '1.3.6.1.2.1.43.8.2.1.12.1.2'
        ];
        
        foreach ($tray_oids as $key => $oid) {
            $result = $this->snmp_get($ip, $community, $oid);
            $trays[$key] = $result ?: 'Unknown';
        }
        
        // If no data found, provide defaults
        if (empty(array_filter($trays, function($v) { return $v !== 'Unknown'; }))) {
            $trays = [
                'tray_1_size' => 'Any Size',
                'tray_1_type' => 'Any Type',
                'tray_2_size' => 'Not available',
                'tray_2_type' => 'Not available'
            ];
        }
        
        return $trays;
    }

    private function get_cartridge_info($ip, $community, $printer_type)
    {
        // Berdasarkan hasil SNMP walk HP printer inkjet
        $cartridge_info = [
            'cyan_level' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.1') ?: 'Unknown',
            'magenta_level' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.2') ?: 'Unknown',
            'yellow_level' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.3') ?: 'Unknown',
            'black_level' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.9.1.4') ?: 'Unknown',
            'pages_printed' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1') ?: '0',
            'cartridge_descriptions' => [
                'cyan' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.6.1.1') ?: 'cyan ink unknown',
                'magenta' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.6.1.2') ?: 'magenta ink unknown',
                'yellow' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.6.1.3') ?: 'yellow ink unknown',
                'black' => $this->snmp_get($ip, $community, '1.3.6.1.2.1.43.11.1.1.6.1.4') ?: 'black ink unknown'
            ]
        ];
        
        return $cartridge_info;
    }

    // Debug method to explore available OIDs
    public function debug_snmp_walk($ip, $community, $base_oid = '1.3.6.1.2.1.43')
    {
        if (!function_exists('snmp2_real_walk')) {
            return ['error' => 'snmp2_real_walk function not available'];
        }
        
        $result = @snmp2_real_walk($ip, $community, $base_oid, 3000000, 1);
        
        if ($result === false) {
            return ['error' => 'SNMP walk failed for OID: ' . $base_oid];
        }
        
        if (empty($result)) {
            return ['message' => 'No data found for OID: ' . $base_oid];
        }
        
        // Clean up the results
        $cleaned = [];
        $count = 0;
        foreach ($result as $oid => $value) {
            if ($count >= 20) break; // Limit results
            $cleaned_value = trim(trim($value, '"'), ' ');
            $cleaned_value = preg_replace('/^(STRING|Counter32|INTEGER|Gauge32|TimeTicks):\s*"?/', '', $cleaned_value);
            $cleaned_value = trim($cleaned_value, '"');
            $cleaned[$oid] = $cleaned_value;
            $count++;
        }
        
        return $cleaned;
    }

    // Method to test multiple OIDs at once
    public function test_multiple_oids($ip, $community, $oids)
    {
        $results = [];
        
        foreach ($oids as $name => $oid) {
            $result = $this->snmp_get($ip, $community, $oid);
            $results[$name] = [
                'oid' => $oid,
                'value' => $result,
                'success' => $result !== null && $result !== ''
            ];
        }
        
        return $results;
    }
}