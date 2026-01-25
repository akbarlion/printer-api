<?php

require_once __DIR__ . '/../../vendor/autoload.php';
date_default_timezone_set('Asia/Jakarta');

use chriskacerguis\RestServer\RestController;

class Monitor extends RestController
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('M_printer_alerts', 'alerts');
        $this->load->library('Monitoring_service', '', 'monitor');
        $this->load->library('Auth_lib', '', 'auth');
    }

    public function check_options()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        $this->response(null, 200);
    }

    public function check_post()
    {
        header('Access-Control-Allow-Origin: *');
        
        $this->monitor->check_all_printers();
        
        $this->response([
            'status' => 200,
            'message' => 'Printer monitoring completed'
        ], $this::HTTP_OK);
    }

    public function alerts_get()
    {
        header('Access-Control-Allow-Origin: *');
        
        // Require authentication
        $user = $this->auth->require_auth();
        
        $alerts = $this->alerts->get_unacknowledged();
        
        $this->response([
            'status' => 200,
            'message' => 'Success',
            'data' => $alerts
        ], $this::HTTP_OK);
    }

    public function acknowledge_put($id)
    {
        header('Access-Control-Allow-Origin: *');
        
        $data = $this->put();
        $acknowledgedBy = $data['acknowledgedBy'] ?? 'system';
        
        $success = $this->alerts->acknowledge($id, $acknowledgedBy);
        
        if ($success) {
            $this->response([
                'status' => 200,
                'message' => 'Alert acknowledged'
            ], $this::HTTP_OK);
        } else {
            $this->response([
                'status' => 400,
                'message' => 'Failed to acknowledge alert'
            ], $this::HTTP_BAD_REQUEST);
        }
    }

    public function acknowledge_all_options()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: PUT, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        $this->response(null, 200);
    }

    public function acknowledge_all_put()
    {
        header('Access-Control-Allow-Origin: *');
        
        $data = $this->put();
        $acknowledgedBy = $data['acknowledgedBy'] ?? 'system';
        
        $success = $this->alerts->acknowledge_all($acknowledgedBy);
        
        if ($success) {
            $this->response([
                'status' => 200,
                'message' => 'All alerts acknowledged'
            ], $this::HTTP_OK);
        } else {
            $this->response([
                'status' => 400,
                'message' => 'Failed to acknowledge all alerts'
            ], $this::HTTP_BAD_REQUEST);
        }
    }
}