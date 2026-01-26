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
            $info = [
                'name' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.1.5.0'),
                'model' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1'),
                'serial_number' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.17.1'),
                'status' => $this->get_status($ip_address, $community)
            ];

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

            // Build data step by step - MINIMAL FIRST
            $data = [];

            // 1. Printer Information - BASIC ONLY
            $data['printer_info'] = [
                'name' => $basic_test,
                'model' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1'),
                'serial_number' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.17.1'),
                'engine_cycles' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1'),
                'status' => $this->get_status($ip_address, $community),
            ];

            // 2. Supplies - SIMPLE
            $supplies = [];
            $supplies['black'] = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.9.1.1');
            $supplies['cyan'] = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.9.1.2');
            $supplies['magenta'] = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.9.1.3');
            $supplies['yellow'] = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.9.1.4');
            $data['supplies'] = $supplies;

            // 3. Paper Trays and Options
            $tray_1_type = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.8.2.1.2.1.1');
            $tray_2_type = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.8.2.1.2.1.2');
            
            $tray_type_map = [
                1 => 'Other', 
                2 => 'Unknown', 
                3 => 'Removable Tray', 
                4 => 'Built-in Tray', 
                5 => 'Manual Feed'
            ];
            
            $data['paper_trays'] = [
                'default_paper_size' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.8.2.1.12.1.1') ?: 'A4',
                'tray_1_type' => $tray_type_map[$tray_1_type] ?? 'Unknown',
                'tray_2_type' => $tray_type_map[$tray_2_type] ?? 'Unknown',
                'tray_1_size' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.8.2.1.12.1.1') ?: 'A4',
                'tray_2_size' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.8.2.1.12.1.2') ?: 'A4',
                'note' => 'HP P3010 may return paper type instead of size for some trays'
            ];

            // 4. Cartridge Information
            $supply_level = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.9.1.1');
            $cartridge_desc = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.6.1.1');
            $install_date = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.15.1.1');
            $last_used = $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.11.1.1.16.1.1');
            
            $data['cartridge_info'] = [
                'supply_level' => $supply_level ? $supply_level . '%' : 'Unknown',
                'pages_printed' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1') ?: '0',
                'cartridge_serial' => $cartridge_desc ? trim(str_replace('\0', '', $cartridge_desc)) : 'Unknown',
                'cartridge_install_date' => $install_date ?: 'Not available',
                'last_used_date' => $last_used ?: 'Not available'
            ];

            // 5. Memory
            $data['memory'] = $this->get_memory_info($ip_address, $community);


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

    private function snmp_get($ip, $community, $oid)
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
            return $cleaned;
        }
        return null;
    }

    private function get_status($ip, $community)
    {
        $status = $this->snmp_get($ip, $community, '1.3.6.1.2.1.25.3.5.1.1.1');
        $status_map = [1 => 'other', 2 => 'unknown', 3 => 'idle', 4 => 'printing', 5 => 'warmup'];
        return $status_map[$status] ?? 'unknown';
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

    // private function get_enhanced_paper_trays($ip, $community)
    // {
    //     // Return empty array for now to avoid timeout
    //     return [];
    // }

    // private function get_basic_supplies($ip, $community)
    // {
    //     $supplies = [];

    //     // Try basic supply OIDs
    //     $supply_oids = [
    //         'black' => '1.3.6.1.2.1.43.11.1.1.9.1.1',
    //         'cyan' => '1.3.6.1.2.1.43.11.1.1.9.1.2', 
    //         'magenta' => '1.3.6.1.2.1.43.11.1.1.9.1.3',
    //         'yellow' => '1.3.6.1.2.1.43.11.1.1.9.1.4'
    //     ];

    //     foreach ($supply_oids as $color => $oid) {
    //         $result = $this->snmp_get($ip, $community, $oid);
    //         $supplies[$color] = $result !== null && is_numeric($result) ? (int) $result : 'Unknown';
    //     }

    //     return $supplies;
    // }

    // private function _snmp_walk($ip, $community, $oid, $timeout = 2000000, $retries = 2)
    // {
    //     if (!function_exists('snmp2_real_walk')) {
    //         return false;
    //     }

    //     return @snmp2_real_walk($ip, $community, $oid, $timeout, $retries);
    // }
}