<?php

namespace App\Models;

class Todo
{
    private $collection;

    public function __construct($db)
    {
        $this->collection = $db->selectCollection('todos');
        $this->initialize();
    }

    private function initialize()
    {
        // Buat indeks untuk pencarian yang lebih cepat
        try {
            $this->collection->createIndex('title');
            $this->collection->createIndex('completed');
            $this->collection->createIndex('created_at');
        } catch (\Exception $e) {
            // Skip jika indeks sudah ada
        }
    }

    public function getAll($filter = [])
    {
        return $this->collection->find($filter)->sort(['created_at' => -1])->toArray();
    }

    public function getById($id)
    {
        return $this->collection->findOne(['_id' => $id]);
    }

    public function create(array $data)
    {
        $data['completed'] = false;
        $data['created_at'] = new \MongoDB\BSON\UTCDateTime();
        $data['updated_at'] = new \MongoDB\BSON\UTCDateTime();
        
        $id = $this->collection->insert($data);
        return $this->getById($id);
    }

    public function update($id, array $data)
    {
        $data['updated_at'] = new \MongoDB\BSON\UTCDateTime();
        
        $this->collection->update(
            ['_id' => $id],
            ['$set' => $data]
        );
        
        return $this->getById($id);
    }

    public function delete($id)
    {
        return $this->collection->remove(['_id' => $id]);
    }

    public function markAsCompleted($id, $completed = true)
    {
        return $this->update($id, [
            'completed' => $completed,
            'completed_at' => $completed ? new \MongoDB\BSON\UTCDateTime() : null
        ]);
    }
}
