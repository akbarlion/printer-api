<?php

class M_printer_alerts extends CI_Model
{
    protected $table = 'printeralerts';

    public function create($data)
    {
        if (!isset($data['id'])) {
            $data['id'] = $this->generate_uuid();
        }
        $data['createdAt'] = date('Y-m-d H:i:s');
        $data['updatedAt'] = date('Y-m-d H:i:s');
        return $this->db->insert($this->table, $data);
    }

    public function get_unacknowledged()
    {
        $this->db->where('isAcknowledged', 0);
        $this->db->order_by('createdAt', 'DESC');
        return $this->db->get($this->table)->result();
    }

    public function acknowledge($id, $acknowledgedBy)
    {
        $data = [
            'isAcknowledged' => 1,
            'acknowledgedAt' => date('Y-m-d H:i:s'),
            'acknowledgedBy' => $acknowledgedBy,
            'updatedAt' => date('Y-m-d H:i:s')
        ];
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function acknowledge_all($acknowledgedBy)
    {
        $data = [
            'isAcknowledged' => 1,
            'acknowledgedAt' => date('Y-m-d H:i:s'),
            'acknowledgedBy' => $acknowledgedBy,
            'updatedAt' => date('Y-m-d H:i:s')
        ];
        $this->db->where('isAcknowledged', 0);
        return $this->db->update($this->table, $data);
    }

    private function generate_uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}