<?php

require_once __DIR__ . '/../../vendor/autoload.php';
date_default_timezone_set('Asia/Jakarta');

use chriskacerguis\RestServer\RestController;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth extends RestController
{
    private $jwt_key = 'printer_api_secret_key_2026';
    
    function __construct()
    {
        parent::__construct();
        $this->load->model('M_user', 'user');
    }

    public function login_options()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        $this->response(null, 200);
    }

    public function login_post()
    {
        header('Access-Control-Allow-Origin: *');
        
        $data = $this->post();
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            $this->response([
                'status' => 400,
                'message' => 'Username and password required'
            ], $this::HTTP_BAD_REQUEST);
            return;
        }

        // Check user credentials
        $user = $this->user->authenticate($username, $password);
        
        if ($user) {
            // Generate JWT token
            $payload = [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60) // 24 hours
            ];

            $token = JWT::encode($payload, $this->jwt_key, 'HS256');

            $this->response([
                'status' => 200,
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'role' => $user->role
                    ]
                ]
            ], $this::HTTP_OK);
        } else {
            $this->response([
                'status' => 401,
                'message' => 'Invalid credentials'
            ], $this::HTTP_UNAUTHORIZED);
        }
    }

    public function verify_token($token = null)
    {
        try {
            if (!$token) {
                $headers = $this->input->request_headers();
                $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
                
                if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
                    $token = substr($auth_header, 7);
                }
            }

            if (!$token) {
                return false;
            }

            $decoded = JWT::decode($token, new Key($this->jwt_key, 'HS256'));
            return $decoded;
            
        } catch (Exception $e) {
            return false;
        }
    }
}