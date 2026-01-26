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

            $connectionTest = $this->snmp_lib->test_connection($printer->ipAddress, $printer->snmpCommunity ?? 'public');

            if (!$connectionTest['success']) {
                $this->response([
                    'status' => 502,
                    'message' => 'Printer is offline or unreachable',
                    'error' => $connectionTest['message']
                ], 502);
                return;
            }

            $details = $this->snmp_lib->get_printer_details($printer->ipAddress, $printer->snmpCommunity ?? 'public');

            if ($details['success']) {
                $this->response([
                    'status' => 200,
                    'message' => 'Success',
                    'data' => $details['data']
                ], $this::HTTP_OK);
            } else {
                $this->response([
                    'status' => 502,
                    'message' => 'Failed to get printer details',
                    'error' => $details['error'] ?? null
                ], 502);
            }

        } catch (Exception $e) {
            $this->response([
                'status' => 500,
                'message' => 'Error fetching printer details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}