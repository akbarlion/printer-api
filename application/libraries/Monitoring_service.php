<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Monitoring_service
{
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('M_printer', 'printer');
        $this->CI->load->model('M_printer_alerts', 'alerts');
        $this->CI->load->library('Snmp_service', '', 'snmp');
        $this->CI->load->library('Websocket_service', '', 'websocket');
    }

    public function check_all_printers()
    {
        $printers = $this->CI->printer->select_all('printers')->result();
        
        foreach ($printers as $printer) {
            $this->check_printer($printer);
        }
    }

    private function check_printer($printer)
    {
        $result = $this->CI->snmp->test_connection($printer->ipAddress, $printer->community ?? 'public');
        
        if (!$result['success']) {
            // Printer offline - buat alert
            $alertData = [
                'printerId' => $printer->id,
                'printerName' => $printer->name,
                'alertType' => 'connection',
                'severity' => 'high',
                'message' => 'Printer is offline or unreachable',
                'isAcknowledged' => 0
            ];
            
            $this->CI->alerts->create($alertData);
            
            // Update printer status
            $this->CI->printer->update($printer->id, ['status' => 'offline']);
            
            // Kirim WebSocket alert
            $this->CI->websocket->send_alert($printer->id, 'Printer offline', 'offline');
            
        } else {
            // Printer online - update status
            $this->CI->printer->update($printer->id, ['status' => 'online']);
        }
    }
}