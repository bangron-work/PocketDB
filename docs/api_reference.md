# Referensi API

Dokumen ini menjelaskan API publik PocketDB (kelas, metode, dan perilaku yang diharapkan).

## Ikhtisar

PocketDB menyediakan beberapa kelas utama:

- `PocketDB\Client` — mengelola beberapa instance `Database` (folder atau mode in-memory).
- `PocketDB\Database` — membungkus koneksi PDO SQLite dan menyediakan manajemen collection/table serta fungsi pembantu yang dipakai oleh query.
- `PocketDB\Collection` — abstraksi koleksi dokumen (insert, find, update, remove, populate, hooks).
- `PocketDB\\Cursor` — iterator malas untuk hasil query dengan dukungan proyeksi, penyortiran, paging, dan populate.

Semua dokumen disimpan sebagai string JSON di kolom `document` (TEXT) pada tabel per-koleksi.

## Client

- `__construct(string $path, array $options = [])` — membuat client; `$path` adalah direktori untuk file DB di disk atau `:memory:` untuk database sementara.
- `selectDB(string $name): Database` — mengembalikan instance `Database` untuk nama yang diberikan (membuat file/koneksi secara malas). Nama divalidasi untuk mencegah path traversal.
- `selectCollection(string $database, string $collection): Collection` — helper untuk memilih koleksi pada DB bernama.
- `listDBs(): array` — (mode disk) mengembalikan daftar database yang ditemukan di path client (file `.sqlite`).
- `close(): void` — menutup semua koneksi `Database` yang dimiliki client.
- Magic getter: akses properti `$client->mydb` akan memanggil `selectDB('mydb')`.

Contoh:

```
$client = new \PocketDB\Client('/var/data/pocketdb');
$db = $client->selectDB('app');
$users = $db->selectCollection('users');
```

## Database

- `__construct(string $path = ':memory:', array $options = [])` — membuka koneksi PDO SQLite dan mendaftarkan dua fungsi SQLite helper:
  - `document_key(key, document)` — mengekstrak nilai JSON bersarang untuk `ORDER BY` dan indexing.
  - `document_criteria(id, document)` — mendelegasikan ke closure kriteria yang diregistrasikan di PHP (lihat `registerCriteriaFunction`).
- `selectCollection(string $name): Collection` — mengembalikan instance `Collection`. Membuat tabel SQLite jika belum ada.
- `createCollection(string $name): void` — membuat tabel koleksi jika perlu.
- `dropCollection(string $name): void` — menghapus tabel dan membersihkan cache internal.
- `getCollectionNames(): array` — daftar nama tabel.
- `listCollections(): array` — mengembalikan instance `Collection` untuk semua tabel.
- `createJsonIndex(string $collection, string $field, ?string $indexName = null): void` — membuat index SQLite pada `json_extract(document, '$.field')`.

  **Praktik:** Untuk performa upsert dan pencarian cepat, buat index pada field `_id` atau field yang sering dipakai di kondisi WHERE. Contoh: `createJsonIndex('users', '_id')`.

- `attach(string $path, string $alias): bool` — attach file SQLite eksternal ke koneksi ini dengan alias SQL; mengembalikan `true` saat berhasil.
- `detach(string $alias): bool` — detach alias yang pernah dilampirkan.
- `attachOnce(string $path, string $alias, callable $callback)` — wrapper kenyamanan: attach, jalankan `$callback($this, $alias)`, lalu detach di blok `finally`.
- `registerCriteriaFunction($criteria): ?string` — mendaftarkan callable PHP atau kriteria array; mengembalikan id yang dipakai oleh `document_criteria`.
  - Jika `$criteria` adalah array, PocketDB mengonversinya menjadi closure PHP yang menggunakan `UtilArrayQuery::match()` dan menyimpannya dengan id di registry statis.
- `callCriteriaFunction(string $id, $document): bool` — eksekusi kriteria yang terdaftar.
- `vacuum(): void`, `close(): void`, `drop(): void` — helper pemeliharaan.

Catatan attach/alias: alias harus cocok dengan regex `/^[a-zA-Z0-9_]+$/`. Path attach dikutip ke SQL; attach/detach mengembalikan boolean false pada kegagalan dan tidak melempar.

## Collection

Collection menyediakan operasi dokumen dan API mirip MongoDB. Dokumen adalah array asosiatif; `_id` adalah identifier dokumen.

- `__construct(string $name, Database $database)` — internal.
- `insert(array $doc)` — sisipkan satu dokumen atau beberapa dokumen (array of arrays). Mengembalikan `_id` (string) untuk insert tunggal, atau array id untuk batch.
  - Mode pembuatan ID: `auto` (UUID v4), `manual` (gunakan `_id` dari dokumen), `prefix` (`PREFIX-000001`). Gunakan `setIdModeAuto()`, `setIdModeManual()`, `setIdModePrefix($prefix)`.
- `insertMany(array $docs)` — alias untuk batch insert.
- `save(array $doc, bool $create = false)` — helper upsert. Jika `_id` ada maka update; jika tidak (atau `$create` true) maka buat.

  **Catatan optimasi `save()`**: implementasi terbaru menggunakan pencarian native SQL pada field `_id` via `json_extract(document, '$._id')` untuk menghindari pemanggilan `document_criteria` (callback PHP) per baris. Ini memungkinkan SQLite memanfaatkan index JSON apabila dibuat melalui `createJsonIndex()` dan mempercepat operasi upsert dan pencarian berdasarkan `_id`.

- `find($criteria = null, $projection = null): Cursor` — mengembalikan `Cursor` untuk hasil yang dapat diiterasi.
  - Jika `$criteria` adalah array kesetaraan sederhana (tanpa operator `$` dan tanpa array bersarang), library mengonversinya menjadi klausa SQL `WHERE` native menggunakan `json_extract` untuk performa dan penggunaan index. Kriteria kompleks dievaluasi lewat callback PHP yang terdaftar.
  - `$projection` menerima map seperti `['name' => 1, 'age' => 1]` (mode include) atau `['password' => 0]` (mode exclude). `_id` selalu disertakan di mode include.
- `findOne($criteria = null, $projection = null): ?array` — wrapper kenyamanan mengembalikan dokumen pertama atau `null`.
- `update($criteria, array $data, bool $merge = true): int` — update dokumen yang cocok. Jika `$merge` true, gabungkan kunci; jika false, ganti keseluruhan JSON.
- `remove($criteria): int` — hapus dokumen yang cocok.
- `count($criteria = null): int` — hitung dokumen (menggunakan `Cursor::count()`); jika tabel tidak ada, kembalikan `0`.
- `on(string $event, callable $fn)` / `off(string $event, callable $fn = null)` — register/unregister hooks: `beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeRemove`, `afterRemove`.
- `drop()` — hapus tabel koleksi (delegasi ke `Database::dropCollection`).

Populate / referensi

- `Cursor` mendukung `populate()`/`populateMany()`/`with()` yang menerima path seperti `user_id` atau `items.product_id`, sebuah `Collection` target, dan opsi `['as' => 'namaField']` untuk menempatkan hasil populate.

## Cursor

`Cursor` adalah objek iterabel hasil dari `Collection::find()`.

- `limit(int $n): Cursor` — batasi jumlah hasil.
- `skip(int $n): Cursor` — lewati sejumlah hasil.
- `sort(array $spec): Cursor` — penyortiran berdasarkan map field=>direction (1 ASC, -1 DESC) menggunakan helper `document_key()`.
- `populate(string $path, Collection $collection, array $options = []): Cursor` — daftarkan aturan populate untuk mengambil dokumen terkait dan menyuntikkannya ke output. Mendukung path bersarang.
- `populateMany(array $defs)` / `with(string|array $path, ?Collection $collection = null, array $options = [])` — varian kenyamanan.
- `toArray(): array` — materialisasi cursor menjadi array dokumen. Perilaku penting:
  - Library menerapkan aturan `populate` terlebih dahulu, lalu menerapkan proyeksi (jika ada). Ini memastikan populate melihat field asli yang diperlukan.
- `each(callable $fn): Cursor` — iterasi dengan memanggil `$fn($doc)` untuk tiap dokumen.
- `count(): int` — hitung dokumen yang cocok. Mengembalikan `0` bila tabel tidak ada.
- `getIterator()` — mengimplementasikan `IteratorAggregate` agar iterasi PHP standar bekerja.

Detil proyeksi

- Proyeksi berupa map include/exclude sederhana. Kombinasi include dan exclude tidak didukung.
- Mode include: field dengan nilai truthy disertakan; `_id` selalu dipertahankan.
- Mode exclude: field dengan nilai `0` dihapus dari dokumen.

## Utilitas

- `\PocketDB\UtilArrayQuery` — evaluator internal yang mengimplementasikan operator Mongo-like saat kriteria diberikan sebagai array. Mendukung operator: `$and`, `$or`, `$in`, `$nin`, `$gt`, `$gte`, `$lt`, `$lte`, `$exists`, `$regex` (dan alias), `$size`, `$mod`, `$func` (callable), `$fuzzy` / `$text`, dan lainnya.

- Fungsi pembantu global: `createMongoDbLikeId()` — membuat id mirip UUID yang dipakai mode `auto`; `fuzzy_search()` dan `levenshtein_utf8()` digunakan untuk pencocokan teks.

## Praktik terbaik

- Gunakan kriteria kesetaraan sederhana bila memungkinkan agar query diterjemahkan ke SQL native (`json_extract`) dan bisa memakai index JSON lewat `createJsonIndex()`.
- Hindari mendaftarkan callback `$where` yang sangat mahal pada tabel besar karena setiap baris akan memanggil PHP melalui `document_criteria`.
- Gunakan `attach()` / `attachOnce()` untuk join cross-db singkat, dan `populate()` untuk fetch referensi di level aplikasi.

## Catatan penanganan error

- Banyak operasi mengembalikan `false`/`0` pada kegagalan atau hasil kosong daripada melempar exception. `attach`/`detach` mengembalikan boolean; `attachOnce` melempar jika `attach` gagal atau me-rethrow exception callback setelah memastikan `detach`.

---

Jika Anda ingin, saya dapat menghasilkan JSON/Markdown bergaya OpenAPI dari API ini, atau menambahkan contoh runnable di `docs/usage_examples.md` yang diubah menjadi test PHPUnit.
