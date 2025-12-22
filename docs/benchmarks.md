# Benchmark & Pengoptimalan Performa

Ringkasan alat benchmark dan tips tuning.

Alat yang disertakan

- `tools/profile.php` — profiler ringan untuk uji cepat in-memory.
- `tools/benchmark_disk.php` — benchmark disk vs memory dengan parameter ukuran batch.
- `tools/benchmark_crossdb.php` — bandingkan konfigurasi PRAGMA antar setup disk.

Tips tuning utama

- Bulk ingest: gunakan ukuran batch besar (mis. 10k+) dan bungkus insert dalam transaksi.
- Untuk ingest tercepat ke disk, sementara set `PRAGMA synchronous=OFF` lalu kembalikan ke `NORMAL` setelah selesai.
- Buat index JSON untuk field yang sering dicari atau disortir menggunakan `Database::createJsonIndex()`.
- Gunakan query kesetaraan sederhana yang bisa diterjemahkan ke `json_extract(...) = value` sehingga bisa memakai index.

Profiling

- Untuk profiling CPU gunakan Xdebug/pcov dan hasilkan output cachegrind/callgrind, lalu analisa dengan `kcachegrind` atau alat serupa.
