<?php

class M_user extends CI_Model
{
    protected $table = 'users';

    public function authenticate($username, $password)
    {
        $this->db->where('username', $username);
        $user = $this->db->get($this->table)->row();
        
        if ($user && password_verify($password, $user->password)) {
            return $user;
        }
        
        return false;
    }

    public function create_user($data)
    {
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['created_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert($this->table, $data);
    }

    public function get_by_id($id)
    {
        $this->db->where('id', $id);
        return $this->db->get($this->table)->row();
    }
}