<?php

namespace App\Controllers;

use App\Models\Todo;

class TodoController
{
    private $todoModel;

    public function __construct()
    {
        $this->todoModel = new Todo(Flight::get('db'));
    }

    public function index()
    {
        $filter = [];
        
        // Filter by completion status if provided
        if ($status = Flight::request()->query->completed) {
            $filter['completed'] = $status === 'true';
        }
        
        $todos = $this->todoModel->getAll($filter);
        Flight::json($todos);
    }

    public function show($id)
    {
        $todo = $this->todoModel->getById($id);
        
        if (!$todo) {
            return Flight::halt(404, json_encode([
                'error' => true,
                'message' => 'Todo tidak ditemukan',
                'code' => 404
            ]));
        }
        
        Flight::json($todo);
    }

    public function store()
    {
        $data = Flight::request()->data->getData();
        
        // Validasi input
        if (empty($data['title'])) {
            return Flight::halt(400, json_encode([
                'error' => true,
                'message' => 'Judul tidak boleh kosong',
                'code' => 400
            ]));
        }
        
        $todo = $this->todoModel->create([
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null
        ]);
        
        Flight::json($todo, 201);
    }

    public function update($id)
    {
        $data = Flight::request()->data->getData();
        
        // Validasi input
        if (empty($data['title'])) {
            return Flight::halt(400, json_encode([
                'error' => true,
                'message' => 'Judul tidak boleh kosong',
                'code' => 400
            ]));
        }
        
        $todo = $this->todoModel->update($id, [
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null
        ]);
        
        if (!$todo) {
            return Flight::halt(404, json_encode([
                'error' => true,
                'message' => 'Todo tidak ditemukan',
                'code' => 404
            ]));
        }
        
        Flight::json($todo);
    }

    public function toggleComplete($id)
    {
        $data = Flight::request()->data->getData();
        $completed = $data['completed'] ?? true;
        
        $todo = $this->todoModel->markAsCompleted($id, $completed);
        
        if (!$todo) {
            return Flight::halt(404, json_encode([
                'error' => true,
                'message' => 'Todo tidak ditemukan',
                'code' => 404
            ]));
        }
        
        Flight::json($todo);
    }

    public function destroy($id)
    {
        $result = $this->todoModel->delete($id);
        
        if ($result === 0) {
            return Flight::halt(404, json_encode([
                'error' => true,
                'message' => 'Todo tidak ditemukan',
                'code' => 404
            ]));
        }
        
        Flight::json([
            'success' => true,
            'message' => 'Todo berhasil dihapus'
        ]);
    }
}
