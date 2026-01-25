<?php

require_once __DIR__ . '/../../vendor/autoload.php';
date_default_timezone_set('Asia/Jakarta');

use chriskacerguis\RestServer\RestController;

class Alert extends RestController
{
    function __construct()
    {
        parent::__construct();
        $this->load->library('Websocket_service', '', 'websocket');
    }

    public function send_options()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
        $this->response(null, 200);
    }

    public function send_post()
    {
        header('Access-Control-Allow-Origin: *');
        
        $data = $this->post();
        $printer_id = $data['printer_id'] ?? null;
        $message = $data['message'] ?? null;
        $status = $data['status'] ?? null;

        if (!$printer_id || !$message || !$status) {
            $this->response([
                'status' => 400,
                'message' => 'Missing required fields'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        $result = $this->websocket->send_alert($printer_id, $message, $status);

        if ($result) {
            $this->response([
                'status' => 200,
                'message' => 'Alert sent successfully'
            ], $this::HTTP_OK);
        } else {
            $this->response([
                'status' => 500,
                'message' => 'Failed to send alert'
            ], $this::HTTP_INTERNAL_ERROR);
        }
    }
}