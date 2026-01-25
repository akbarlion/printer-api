<?php

class M_printer extends CI_Model
{
    protected $table = 'printers';

    function select_all($table)
    {
        return $this->db->get($table);
    }

    public function create($data)
    {
        if (!isset($data['id'])) {
            $data['id'] = $this->generate_uuid();
        }
        return $this->db->insert($this->table, $data);
    }

    public function get_insert_id()
    {
        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        if (empty($id) || empty($data)) {
            return false;
        }
        $this->db->where('id', $id);
        return $this->db->update($this->table, $data);
    }

    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete($this->table);
    }

    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        return $this->db->get($this->table)->row();
    }

    public function exists($id)
    {
        $this->db->where('id', $id);
        return $this->db->count_all_results($this->table) > 0;
    }

    private function generate_uuid()
    {
        // Simple UUID v4 generator
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