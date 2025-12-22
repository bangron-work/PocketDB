# Praktik Terbaik & Operasi

Panduan desain dan operasi saat menggunakan PocketDB di lingkungan produksi.

Layout data

- Gunakan DB terpisah per-domain untuk isolasi (mis. `base.sqlite` untuk autentikasi, `ecommerce.sqlite` untuk pesanan).
- Pertimbangkan DB per-tenant atau per-region jika pola akses terpartisi secara alami.

Index

- Gunakan `Database::createJsonIndex()` untuk field yang sering dicari atau disortir.

Backup

- Backup file `.sqlite`. Tutup koneksi (`Database::close()`) atau hentikan aplikasi sementara saat membuat salinan untuk memastikan konsistensi.

Operasi bulk

- Gunakan batch insert yang besar dan mode `WAL`. Untuk impor besar, pertimbangkan menurunkan `PRAGMA synchronous` sementara.

Monitoring

- Pantau ukuran file, I/O, latensi operasi, dan query lambat. Lacak jumlah dokumen per-koleksi dan per-shard.

Keamanan

- Batasi akses filesystem ke file DB. Terapkan otorisasi pada level aplikasi untuk field sensitif.

Pengujian

- Gunakan PHPUnit yang disertakan sebagai baseline. Tambahkan tes integrasi untuk operasi cross-db dan skenario sharding.
