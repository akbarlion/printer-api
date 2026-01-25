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
        
        $printer = $this->post();
        $ip = $printer['ip'] ?? null;
        $community = $printer['community'] ?? null;

        $snmp_return = $this->snmp->test_connection($ip, $community);

        if (!$snmp_return['success']) {
            $this->response([
                "status" => 400,
                "message" => $snmp_return['message']
            ], $this::HTTP_BAD_REQUEST);
        } else {
            $input = $this->input->post();
            $success = $this->printer->create($input);
            if ($success) {
                $this->response([
                    'status' => 200,
                    'message' => "Berhasil input printer",
                    'data' => $success
                ], $this::HTTP_OK);
            } else {
                $this->response([
                    'status' => 400,
                    'message' => "Gagal input printer"
                ], $this::HTTP_BAD_REQUEST);
            }
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