# Migrasi Searchable Fields dan Drop `si_` Columns

Panduan ini menjelaskan bagaimana menghapus kolom `si_{field}` yang dibuat oleh fitur "searchable fields" dan bagaimana memigrasi tabel SQLite dengan aman.

⚠️ Peringatan penting

- SQLite tidak mendukung `ALTER TABLE DROP COLUMN`. Untuk menghapus sebuah kolom, kita perlu membuat tabel sementara, menyalin kolom yang ingin dipertahankan, lalu mengganti tabel lama.
- Operasi ini bisa berisiko: indeks, trigger, dan constraint kompleks mungkin tidak dipertahankan secara otomatis. Selalu backup file `.sqlite` sebelum menjalankan migrasi.

Tool migrasi

Repositori menyertakan utilitas migrasi kecil di `tools/migrate_drop_si.php`.

Cara menggunakan:

```bash
php tools/migrate_drop_si.php /path/to/db.sqlite collection_name field_name
```

Contoh:

```bash
php tools/migrate_drop_si.php data/myapp.sqlite users email
```

Langkah yang dilakukan tool:

1. Memeriksa bahwa tabel ada.
2. Mengumpulkan daftar kolom melalui `PRAGMA table_info`.
3. Mencari indeks dan trigger terkait.
4. Membuat tabel sementara yang hanya memuat kolom yang dipertahankan.
5. Menyalin data dari tabel lama ke tabel sementara.
6. Menghapus tabel lama dan mengganti dengan tabel sementara.
7. Mencoba merekonstruksi indeks dan trigger yang tidak bergantung pada kolom yang dihapus.

Catatan tambahan

- Jika indeks atau trigger bergantung pada `si_{field}` yang dihapus, tool akan melewatkannya dan menampilkan pesan. Anda mungkin perlu membuat ulang indeks/triggers tersebut secara manual dengan SQL yang sesuai.
- Jika Anda membutuhkan migrasi lebih canggih (mencakup foreign keys, constraints, restore of complex triggers), pertimbangkan menggunakan dump + manual edit + restore, atau gunakan skrip yang lebih canggih yang merekam seluruh `sqlite_master` dan meng-replaynya setelah rekonstruksi tabel.

Jika Anda ingin, saya bisa menambahkan mode "dry-run" untuk utilitas migrasi yang hanya menampilkan rencana tanpa melakukan perubahan. Ini direkomendasikan untuk memverifikasi langkah sebelum eksekusi nyata.
