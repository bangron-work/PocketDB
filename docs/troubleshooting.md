# Pemecahan Masalah (Troubleshooting)

Masalah umum dan cara mengatasinya.

1. Windows: tidak bisa menghapus file `.sqlite` (file sedang digunakan)

- Pastikan `Database::close()` dan `Client::close()` dipanggil untuk menutup koneksi PDO.
- Panggil `gc_collect_cycles()` sebelum menghapus file bila perlu.
- Pastikan tidak ada proses lain (IDE, antivirus, tool) yang membuka file database.

2. Memori habis saat menjalankan tes

- Periksa tes yang membuat dataset besar di memori. Gunakan operasi batch atau fixtures yang lebih kecil.
- Hindari `Cursor::toArray()` pada result set sangat besar; gunakan iterasi streaming (`each()` atau `foreach`).

3. Query lambat saat menggunakan kriteria PHP

- Kriteria yang diregistrasikan di PHP dieksekusi per-baris oleh callback `document_criteria` dan mahal untuk scan besar. Jika memungkinkan gunakan kriteria kesetaraan sederhana sehingga query dapat diterjemahkan ke `json_extract(...)` dan memanfaatkan index.

4. Error ATTACH / masalah path

- Gunakan path absolut saat memanggil `ATTACH DATABASE '/abs/path/file.sqlite' AS alias` dan pastikan proses memiliki izin filesystem.

5. Concurrency / WAL

- Gunakan mode WAL (`PRAGMA journal_mode=WAL`) untuk concurrency. Perhatikan kebutuhan checkpoint dan file `-wal` / `-shm`.
