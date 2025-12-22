````markdown
# Resharding & Migrasi

Dokumen ini menjelaskan strategi dan desain alat minimal untuk memindahkan data antar-shard atau merename database dengan aman.

Tujuan

- Memindahkan subset dokumen (berdasarkan rentang atau kunci) dari shard A ke shard B dengan waktu henti minimal.
- Menjaga konsistensi baca/tulis selama migrasi.

Strategi (dual-write + backfill)

1. Ubah aplikasi agar menulis ke shard sumber dan target secara bersamaan untuk ruang kunci yang dimigrasi (dual-write).
2. Backfill: scan shard sumber dan salin dokumen yang cocok ke shard target.
3. Alihkan baca: perbarui peta routing sehingga pembacaan untuk kunci terkait diarahkan ke shard target.
4. Hentikan dual-write setelah validasi, lalu hapus dokumen lama bila sudah siap.

Contoh alat minimal (pseudo)

```php
// migrate_range.php --source source_db --target target_db --filter "json_extract(document,'$.tenant') = 'acme'"

$source = $client->selectDB('source_db');
$target = $client->selectDB('target_db');

$rows = $source->selectCollection('coll')->find($criteria)->toArray();
foreach ($rows as $r) {
    $target->selectCollection('coll')->insert($r);
}
```

Catatan

- Pastikan `PRAGMA synchronous` dan mode `WAL` sesuai saat melakukan penyalinan besar.
- Gunakan checksum dan perhitungan jumlah dokumen untuk memverifikasi integritas salinan.
````
