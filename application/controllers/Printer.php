<?php

require_once __DIR__ . '/../../vendor/autoload.php';
date_default_timezone_set('Asia/Jakarta');

use chriskacerguis\RestServer\RestController;

class Printer extends RestController
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('M_printer', 'printer');
        $this->load->library('Snmp_service', '', 'snmp');
        $this->load->library('Auth_lib', '', 'auth');
    }

    public function printers_get()
    {
        // Require authentication
        $user = $this->auth->require_auth();

        $printers = $this->printer->select_all('printers')->result();

        if ($printers > 0) {
            $this->response([
                "status" => 200,
                "message" => "Berhasil mendapatkan data",
                "data" => $printers
            ], $this::HTTP_OK);
        } else {
            $this->response([
                "status" => 404,
                "message" => "Printer data not found"
            ], $this::HTTP_NOT_FOUND);
        }
    }

    public function addPrinters_post()
    {
        // Require authentication
        $user = $this->auth->require_auth();

        $raw_input = $this->input->raw_input_stream;
        $param = json_decode($raw_input, true) ?: $this->post();

        $ipAddress = $param['ip_address'] ?? $param['ipAddress'] ?? $param['ip'] ?? null;
        $community = $param['community'] ?? 'public';
        $systemDescription = $param['system_description'] ?? null;
        $printerName = $param['printer_name'] ?? null;
        $name = $param['name'] ?? null;

        if (empty($ipAddress)) {
            $this->response([
                'status' => 400,
                'message' => 'IP address is required'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        // Test SNMP connection first
        $snmp_return = $this->snmp->test_connection($ipAddress, $community);
        if (!$snmp_return['success']) {
            $this->response([
                'status' => 400,
                'message' => $snmp_return['message']
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        // Check if printer already exists
        $existing = $this->db->where('ipAddress', $ipAddress)->get('printers')->row();
        if ($existing) {
            $this->response([
                'status' => 409,
                'message' => 'Printer with this IP already exists'
            ], 409);
            return;
        }

        $data = [
            'ipAddress' => $ipAddress,
            'name' => $snmp_return['data']['printer_name'] ?? $printerName ?? 'Unknown Printer',
            'model' => $snmp_return['data']['model'] ?? $systemDescription,
            'printerType' => 'unknown',
            'location' => null,
            'status' => 'online',
            'snmpProfile' => 'default',
            'snmpCommunity' => $community,
            'isActive' => '1',
            'lastPolled' => null,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s')
        ];

        $success = $this->printer->create($data);
        if ($success) {
            $this->response([
                'status' => 200,
                'message' => 'Printer added successfully',
                'data' => $data
            ], 200);
        } else {
            $this->response([
                'status' => 400,
                'message' => 'Failed to add printer'
            ], 400);
        }
    }

    public function updatePrinters_put($id = null)
    {
        // Require authentication
        $user = $this->auth->require_auth();

        if (!$id) {
            $this->response([
                'status' => 400,
                'message' => 'ID printer tidak ditemukan'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        $input = $this->put();
        $success = $this->printer->update($id, $input);

        if ($success) {
            $this->response([
                'status' => 200,
                'message' => 'Berhasil update printer'
            ], $this::HTTP_OK);
        } else {
            $this->response([
                'status' => 400,
                'message' => 'Gagal update printer'
            ], $this::HTTP_BAD_REQUEST);
        }
    }

    public function deletePrinters_delete($id)
    {
        // Require authentication
        $user = $this->auth->require_auth();

        if (!$id) {
            $this->response([
                'status' => 400,
                'message' => 'ID printer tidak ditemukan'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        $success = $this->printer->delete($id);

        if ($success) {
            $this->response([
                'status' => 200,
                'message' => 'Berhasil hapus printer'
            ], $this::HTTP_OK);
        } else {
            $this->response([
                'status' => 400,
                'message' => 'Gagal hapus printer'
            ], $this::HTTP_BAD_REQUEST);
        }
    }
}