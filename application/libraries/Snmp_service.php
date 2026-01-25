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
        try {
            $result = @snmp2_get($ip_address, $community, '1.3.6.1.2.1.1.1.0', 2000000, 2);

            if ($result === false) {
                return [
                    'success' => false,
                    'message' => 'SNMP connection failed'
                ];
            }

            return [
                'success' => true,
                'message' => 'SNMP connection successful',
                'data' => [
                    'system_description' => trim($result, '"'),
                    'ip_address' => $ip_address
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SNMP connection test failed',
                'error' => $e->getMessage()
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

    private function snmp_get($ip, $community, $oid)
    {
        if (!function_exists('snmp2_get')) {
            return null;
        }

        $result = @snmp2_get($ip, $community, $oid, 2000000, 2);
        if ($result !== false) {
            return trim(trim($result, '"'), ' ');
        }
        return null;
    }

    private function get_status($ip, $community)
    {
        $status = $this->snmp_get($ip, $community, '1.3.6.1.2.1.25.3.5.1.1.1');
        $status_map = [1 => 'other', 2 => 'unknown', 3 => 'idle', 4 => 'printing', 5 => 'warmup'];
        return $status_map[$status] ?? 'unknown';
    }


    public function get_printer_details($ip_address, $community = 'public')
    {
        $data = [];

        try {
            // 1. Printer Information - Using standard Printer MIB OIDs
            $data['printer_info'] = [
                'name' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.1.5.0'), // sysName
                'model' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.25.3.2.1.3.1'), // hrDeviceDescr
                'serial_number' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.17.1'), // prtGeneralSerialNumber
                'printer_name' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.16.1'), // prtGeneralPrinterName (new in v2)
                'engine_cycles' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.10.2.1.4.1.1'), // prtMarkerLifeCount
                'firmware' => $this->snmp_get($ip_address, $community, '1.3.6.1.4.1.11.2.3.9.4.2.1.1.3.3.0'), // HP firmware
                'console_display' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.16.5.1.2.1.1'), // prtConsoleDisplayBufferText
            ];

            // 2. Memory Information
            $data['memory'] = $this->get_memory_info($ip_address, $community);

            // 3. Event Log - Using correct Printer MIB OIDs
            $data['event_log'] = [
                'max_entries' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.18.1.1.2.1'), // prtAlertTableMaximumSize
                'current_entries' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.18.1.1.1.1'), // prtAlertIndex (count)
                'critical_events' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.18.1'), // prtAlertCriticalEvents (new in v2)
                'all_events' => $this->snmp_get($ip_address, $community, '1.3.6.1.2.1.43.5.1.1.19.1'), // prtAlertAllEvents (new in v2)
            ];

            // 4. Paper Trays
            $data['paper_trays'] = $this->get_enhanced_paper_trays($ip_address, $community);

            // 5. Basic supplies info (toner levels)
            $data['supplies'] = $this->get_basic_supplies($ip_address, $community);

            return [
                'success' => true,
                'data' => $data
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch details',
                'error' => $e->getMessage()
            ];
        }
    }


    private function get_memory_info($ip, $community)
    {
        // Try multiple memory OIDs for better compatibility
        $memory_oids = [
            'hp_onboard' => '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.4.1.0', // HP onboard memory
            'prt_memory' => '1.3.6.1.2.1.43.5.1.1.3.1', // prtGeneralCurrentLocalization memory
            'hr_memory' => '1.3.6.1.2.1.25.2.3.1.5.1', // hrStorageSize RAM
        ];

        $onboard = null;
        foreach ($memory_oids as $type => $oid) {
            $result = $this->snmp_get($ip, $community, $oid);
            if ($result !== null) {
                $onboard = $result;
                break;
            }
        }

        // Try to get storage info with SNMP walk
        $sizes = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.25.2.3.1.5'); // hrStorageSize
        $descrs = $this->_snmp_walk($ip, $community, '1.3.6.1.2.1.25.2.3.1.3'); // hrStorageDescr

        $total = 0;
        if ($sizes && is_array($sizes)) {
            foreach ($sizes as $oid => $size) {
                if (is_numeric($size)) {
                    $total += (int) $size;
                }
            }
        }

        return [
            'on_board' => $onboard ? $onboard . ' MB' : 'Not available',
            'total_usable' => $total > 0 ? $total . ' Allocation Units' : 'Not available'
        ];
    }

    private function get_enhanced_paper_trays($ip, $community)
    {
        $trays = [];

        // Standard printer MIB OIDs for input trays
        $tray_oids = [
            'name' => '1.3.6.1.2.1.43.8.2.1.18',      // prtInputName
            'type' => '1.3.6.1.2.1.43.8.2.1.2',       // prtInputType
            'capacity' => '1.3.6.1.2.1.43.8.2.1.9',   // prtInputMaxCapacity
            'current' => '1.3.6.1.2.1.43.8.2.1.10',   // prtInputCurrentLevel
            'status' => '1.3.6.1.2.1.43.8.2.1.11',    // prtInputStatus
            'media' => '1.3.6.1.2.1.43.8.2.1.12',     // prtInputMediaName
        ];

        $names = $this->_snmp_walk($ip, $community, $tray_oids['name']);
        $capacities = $this->_snmp_walk($ip, $community, $tray_oids['capacity']);
        $currents = $this->_snmp_walk($ip, $community, $tray_oids['current']);
        $medias = $this->_snmp_walk($ip, $community, $tray_oids['media']);

        if ($names && is_array($names)) {
            foreach ($names as $oid => $val) {
                $idx = substr($oid, strrpos($oid, '.') + 1);
                $cleanName = preg_replace('/^(STRING|Counter32|INTEGER):\s*"?/', '', $val);
                $cleanName = trim($cleanName, '"');

                // Try different OID formats for capacity and current level
                $capacity_key = "iso.3.6.1.2.1.43.8.2.1.9.$idx";
                $current_key = "iso.3.6.1.2.1.43.8.2.1.10.$idx";
                $media_key = "iso.3.6.1.2.1.43.8.2.1.12.$idx";

                // Alternative key formats
                if (!isset($capacities[$capacity_key])) {
                    $capacity_key = "1.3.6.1.2.1.43.8.2.1.9.$idx";
                }
                if (!isset($currents[$current_key])) {
                    $current_key = "1.3.6.1.2.1.43.8.2.1.10.$idx";
                }
                if (!isset($medias[$media_key])) {
                    $media_key = "1.3.6.1.2.1.43.8.2.1.12.$idx";
                }

                $trays[] = [
                    'name' => $cleanName,
                    'capacity' => isset($capacities[$capacity_key]) ? (int) $capacities[$capacity_key] : 'Unknown',
                    'current_level' => isset($currents[$current_key]) ? (int) $currents[$current_key] : 'Unknown',
                    'media_type' => isset($medias[$media_key]) ? trim($medias[$media_key], '"') : 'Plain'
                ];
            }
        }
        return $trays;
    }

    private function get_basic_supplies($ip, $community)
    {
        $supplies = [];

        // Try multiple supply level OIDs for better compatibility
        $supply_oids = [
            'black' => ['1.3.6.1.2.1.43.11.1.1.9.1.1', '1.3.6.1.2.1.43.11.1.1.9.1'],
            'cyan' => ['1.3.6.1.2.1.43.11.1.1.9.1.2', '1.3.6.1.2.1.43.11.1.1.9.2'],
            'magenta' => ['1.3.6.1.2.1.43.11.1.1.9.1.3', '1.3.6.1.2.1.43.11.1.1.9.3'],
            'yellow' => ['1.3.6.1.2.1.43.11.1.1.9.1.4', '1.3.6.1.2.1.43.11.1.1.9.4']
        ];

        foreach ($supply_oids as $color => $oids) {
            $level = null;
            // Try each OID until we get a valid response
            foreach ($oids as $oid) {
                $result = $this->snmp_get($ip, $community, $oid);
                if ($result !== null && is_numeric($result)) {
                    $level = (int) $result;
                    break;
                }
            }

            // If still no result, try HP-specific OIDs
            if ($level === null) {
                $hp_oid = '1.3.6.1.4.1.11.2.3.9.4.2.1.1.1.3.' . (array_search($color, array_keys($supply_oids)) + 1) . '.0';
                $result = $this->snmp_get($ip, $community, $hp_oid);
                if ($result !== null && is_numeric($result)) {
                    $level = (int) $result;
                }
            }

            $supplies[$color] = $level !== null ? $level : 'Unknown';
        }

        return $supplies;
    }

    private function _snmp_walk($ip, $community, $oid, $timeout = 2000000, $retries = 2)
    {
        if (!function_exists('snmp2_real_walk')) {
            return false;
        }
        
        return @snmp2_real_walk($ip, $community, $oid, $timeout, $retries);
    }
}