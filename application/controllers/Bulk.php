<?php

require_once __DIR__ . '/../../vendor/autoload.php';
date_default_timezone_set('Asia/Jakarta');

use chriskacerguis\RestServer\RestController;

class Bulk extends RestController
{
    function __construct()
    {
        parent::__construct();
        $this->load->library('Snmp_service', '', 'snmp_lib');
        $this->load->model('M_printer', 'printer');
    }

    public function testConnections_options()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        $this->response(null, 200);
    }

    public function testConnections_post()
    {
        header('Access-Control-Allow-Origin: *');
        
        $raw_input = $this->input->raw_input_stream;
        $param = json_decode($raw_input, true) ?: $this->post();
        
        $ipAddress = $param['ip'] ?? $param['ipAddress'] ?? null;
        $community = $param['community'] ?? 'public';

        if (empty($ipAddress)) {
            $this->response([
                'status' => 400,
                'message' => 'IP address is required'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        $result = $this->snmp_lib->test_connection($ipAddress, $community);

        if ($result['success']) {
            $this->response([
                'status' => 200,
                'message' => $result['message'],
                'data' => $result['data']
            ], $this::HTTP_OK);
        } else {
            $this->response([
                'status' => 400,
                'message' => $result['message']
            ], $this::HTTP_BAD_REQUEST);
        }
    }

    public function details_options($id = null)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        $this->response(null, 200);
    }

    public function details_get($id)
    {
        header('Access-Control-Allow-Origin: *');

        set_time_limit(60);

        if (!$id) {
            $this->response([
                'status' => 400,
                'message' => 'Printer ID is required'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        try {
            $printer = $this->printer->get_by_id($id);

            if (!$printer) {
                $this->response([
                    'status' => 404,
                    'message' => 'Printer not found'
                ], $this::HTTP_NOT_FOUND);
                return;
            }

            // Log printer info for debugging
            log_message('debug', 'Getting details for printer: ' . $printer->name . ' (' . $printer->ipAddress . ')');

            $connectionTest = $this->snmp_lib->test_connection($printer->ipAddress, $printer->snmpCommunity ?? 'public');

            if (!$connectionTest['success']) {
                $this->response([
                    'status' => 502,
                    'message' => 'Printer is offline or unreachable',
                    'error' => $connectionTest['message'],
                    'debug_info' => [
                        'printer_ip' => $printer->ipAddress,
                        'community' => $printer->snmpCommunity ?? 'public'
                    ]
                ], 502);
                return;
            }

            $details = $this->snmp_lib->get_printer_details($printer->ipAddress, $printer->snmpCommunity ?? 'public');

            if ($details['success']) {
                // Log successful response for debugging
                log_message('debug', 'Successfully retrieved printer details for: ' . $printer->name);
                
                $this->response([
                    'status' => 200,
                    'message' => 'Success',
                    'data' => $details['data']
                ], $this::HTTP_OK);
            } else {
                // Log error for debugging
                log_message('error', 'Failed to get printer details for: ' . $printer->name . ' - ' . ($details['message'] ?? 'Unknown error'));
                
                $this->response([
                    'status' => 502,
                    'message' => 'Failed to get printer details',
                    'error' => $details['error'] ?? $details['message'] ?? 'Unknown error',
                    'debug_info' => [
                        'printer_ip' => $printer->ipAddress,
                        'community' => $printer->snmpCommunity ?? 'public'
                    ]
                ], 502);
            }

        } catch (Exception $e) {
            log_message('error', 'Exception in details_get: ' . $e->getMessage());
            
            $this->response([
                'status' => 500,
                'message' => 'Error fetching printer details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function debug_options($id = null)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        $this->response(null, 200);
    }

    public function debug_get($id)
    {
        header('Access-Control-Allow-Origin: *');

        if (!$id) {
            $this->response([
                'status' => 400,
                'message' => 'Printer ID is required'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        try {
            $printer = $this->printer->get_by_id($id);

            if (!$printer) {
                $this->response([
                    'status' => 404,
                    'message' => 'Printer not found'
                ], $this::HTTP_NOT_FOUND);
                return;
            }

            // Quick test - only essential OIDs
            $quick_test = [
                'sysDescr' => $this->snmp_lib->snmp_get($printer->ipAddress, $printer->snmpCommunity ?? 'public', '1.3.6.1.2.1.1.1.0'),
                'black_ink' => $this->snmp_lib->snmp_get($printer->ipAddress, $printer->snmpCommunity ?? 'public', '1.3.6.1.2.1.43.11.1.1.9.1.1'),
                'cyan_ink' => $this->snmp_lib->snmp_get($printer->ipAddress, $printer->snmpCommunity ?? 'public', '1.3.6.1.2.1.43.11.1.1.9.1.2'),
                'magenta_ink' => $this->snmp_lib->snmp_get($printer->ipAddress, $printer->snmpCommunity ?? 'public', '1.3.6.1.2.1.43.11.1.1.9.1.3'),
                'yellow_ink' => $this->snmp_lib->snmp_get($printer->ipAddress, $printer->snmpCommunity ?? 'public', '1.3.6.1.2.1.43.11.1.1.9.1.4')
            ];

            $this->response([
                'status' => 200,
                'message' => 'Quick debug completed',
                'data' => [
                    'printer' => [
                        'name' => $printer->name,
                        'ip' => $printer->ipAddress
                    ],
                    'quick_test' => $quick_test,
                    'working_oids' => count(array_filter($quick_test, function($v) { return $v !== null && $v !== ''; }))
                ]
            ], $this::HTTP_OK);

        } catch (Exception $e) {
            $this->response([
                'status' => 500,
                'message' => 'Debug error: ' . $e->getMessage()
            ], 500);
        }
    }
}