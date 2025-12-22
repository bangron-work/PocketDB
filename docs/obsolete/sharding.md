````markdown
# Panduan Sharding

Panduan ini menjelaskan cara melakukan sharding horizontal pada PocketDB di tingkat aplikasi. PocketDB tidak menyediakan sharding otomatis — lapisan routing harus diimplementasikan oleh aplikasi Anda.

Pilihan desain

- Kunci shard: pilih kunci stabil (mis. `customer_id`, `tenant_id`, `user_id`) untuk memetakan dokumen ke shard.
- Penamaan shard: gunakan nama file deterministik, mis. `ecommerce_shard_0001.sqlite`.
- Fungsi pemetaan: bisa menggunakan modulo sederhana (`crc32(key) % shardCount`) atau consistent hashing untuk skala elastis.

Contoh routing sederhana

```php
function selectShardForKey(\PocketDB\Client $client, string $baseName, string $key, int $shardCount = 4) {
    $hash = crc32((string)$key);
    $shard = $hash % $shardCount;
    $name = sprintf('%s_shard_%04d', $baseName, $shard);
    return $client->selectDB($name);
}
```

Scatter-gather

- Untuk query global (tanpa shard key), lakukan query ke semua shard dan gabungkan hasil di aplikasi. Jalankan paralel bila memungkinkan.

Transaksi & konsistensi

- Transaksi hanya berlaku pada satu shard/koneksi. Transaksi lintas-shard tidak atomik secara bawaan.
- Jika memerlukan konsistensi lintas-shard kuat, terapkan protokol dua-fase (two-phase commit) di level aplikasi (kompleks).

Resharding & migrasi

- Rebalancing memerlukan penyalinan subset dokumen antar-shard dan pembaruan peta routing.
- Pola: tulis ganda sementara (dual-write) ke shard lama + baru, lalu backfill dan alihkan trafik baca/ tulis.

Indexing

- Buat index JSON pada setiap shard untuk field yang sering dicari menggunakan `Database::createJsonIndex()`.

Tips operasional

- Pantau ukuran file shard dan I/O; otomatis buat shard baru saat threshold tercapai.
- Backup dilakukan per-file shard; snapshot tiap file lebih mudah di-manage.
````
