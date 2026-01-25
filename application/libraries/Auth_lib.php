<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth_lib
{
    private $CI;
    private $jwt_key = 'printer_api_secret_key_2026';

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    public function verify_token()
    {
        try {
            $headers = $this->CI->input->request_headers();
            $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            
            // Debug: log headers
            error_log('Headers received: ' . print_r($headers, true));
            error_log('Auth header: ' . $auth_header);

            if (!$auth_header || strpos($auth_header, 'Bearer ') !== 0) {
                error_log('No valid Bearer token found');
                return false;
            }

            $token = substr($auth_header, 7);
            error_log('Token extracted: ' . $token);
            
            $decoded = JWT::decode($token, new Key($this->jwt_key, 'HS256'));
            error_log('Token decoded successfully');

            return $decoded;

        } catch (Exception $e) {
            error_log('JWT decode error: ' . $e->getMessage());
            return false;
        }
    }

    public function require_auth()
    {
        $user = $this->verify_token();

        if (!$user) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status' => 401,
                'message' => 'Unauthorized - Invalid or missing token'
            ]);
            exit;
        }

        return $user;
    }
}