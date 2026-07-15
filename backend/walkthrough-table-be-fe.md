# Walkthrough: contoh menambah tabel produk dari backend Laravel ke frontend Next.js

Saya pilih contoh sederhana: menambah tabel produk. Contoh ini paling cocok untuk belajar karena alurnya jelas dan tidak terlalu rumit.

Tujuan:
- membuat tabel baru di database
- mengeluarkan data lewat API Laravel
- menampilkan data di halaman Next.js + TypeScript

---

## 1. Buat tabel di database

Buat migration baru:

```bash
php artisan make:migration create_produk_table
```

Isi file migration dengan kode berikut:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk', function (Blueprint $table) {
            $table->id('id_produk');
            $table->string('nama');
            $table->integer('harga');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
```

Jalankan migrasi:

```bash
php artisan migrate
```

Jika berhasil, maka tabel `produk` sudah ada di database.

---

## 2. Buat model Laravel

Buat model:

```bash
php artisan make:model Produk
```

Isi file app/Models/Produk.php:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    protected $table = 'produk';
    protected $primaryKey = 'id_produk';
    protected $fillable = ['nama', 'harga', 'keterangan'];
}
```

---

## 3. Buat controller API

Buat controller:

```bash
php artisan make:controller Api/ProdukController
```

Isi file app/Http/Controllers/Api/ProdukController.php:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProdukController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(): JsonResponse
    {
        $data = Produk::latest()->get();
        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'harga' => ['required', 'integer'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $produk = Produk::create($validated);

        return response()->json([
            'message' => 'Produk berhasil ditambah',
            'data' => $produk,
        ], 201);
    }
}
```

---

## 4. Daftarkan route di Laravel

Buka file routes/api.php lalu tambahkan:

```php
use App\Http\Controllers\Api\ProdukController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/produk', [ProdukController::class, 'index']);
    Route::post('/produk', [ProdukController::class, 'store']);
});
```

Jika project Anda sudah punya group auth, Anda bisa menaruh route di dalam group yang sama.

---

## 5. Uji endpoint backend

Jalankan server Laravel:

```bash
php artisan serve
```

Coba ambil data dari API:

```bash
curl -X GET http://127.0.0.1:8000/api/produk \
  -H "Authorization: Bearer <token>"
```

Jika Anda belum punya token, login dulu lewat endpoint login yang ada di project Anda. Karena project ini memakai Sanctum, token Bearer sangat penting.

Jika berhasil, Anda akan menerima JSON array data produk.

---

## 6. Hubungkan ke frontend Next.js + TypeScript

### Langkah 6.1: buat helper API

Buat file lib/api.ts:

```ts
const BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

export async function fetchProduk() {
  const token = typeof window !== 'undefined' ? localStorage.getItem('token') : null;

  const res = await fetch(`${BASE_URL}/produk`, {
    headers: {
      Authorization: `Bearer ${token || ''}`,
    },
  });

  if (!res.ok) {
    throw new Error('Gagal mengambil data produk');
  }

  return res.json();
}
```

Buat file .env.local di frontend Next.js:

```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
```

### Langkah 6.2: buat halaman tampilan

Buat file app/produk/page.tsx:

```tsx
'use client';

import { useEffect, useState } from 'react';
import { fetchProduk } from '@/lib/api';

type Produk = {
  id_produk: number;
  nama: string;
  harga: number;
  keterangan?: string | null;
};

export default function ProdukPage() {
  const [data, setData] = useState<Produk[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const result = await fetchProduk();
        setData(result);
      } catch (error) {
        console.error(error);
      } finally {
        setLoading(false);
      }
    }

    load();
  }, []);

  if (loading) return <p>Sedang memuat...</p>;

  return (
    <div style={{ padding: 24 }}>
      <h1>Daftar Produk</h1>
      <table style={{ borderCollapse: 'collapse', width: '100%' }}>
        <thead>
          <tr>
            <th style={{ border: '1px solid #ccc', padding: 8 }}>Nama</th>
            <th style={{ border: '1px solid #ccc', padding: 8 }}>Harga</th>
            <th style={{ border: '1px solid #ccc', padding: 8 }}>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          {data.map((item) => (
            <tr key={item.id_produk}>
              <td style={{ border: '1px solid #ccc', padding: 8 }}>{item.nama}</td>
              <td style={{ border: '1px solid #ccc', padding: 8 }}>{item.harga}</td>
              <td style={{ border: '1px solid #ccc', padding: 8 }}>{item.keterangan ?? '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

---

## 7. Apa yang sedang terjadi?

Secara sederhana, alur kerja adalah:

1. Migration membuat tabel di database
2. Model menghubungkan Laravel ke tabel tersebut
3. Controller mengambil data dari model
4. Route membuat endpoint API
5. Frontend Next.js memanggil endpoint tersebut
6. Data ditampilkan ke halaman UI

---

## 8. Kesalahan yang sering muncul

- lupa menjalankan `php artisan migrate`
- route belum ditambahkan ke `routes/api.php`
- token Sanctum tidak dikirim ke frontend
- URL API salah, misalnya `http://127.0.0.1:8000/api/produk` vs `/produk`
- CORS atau beda port antara backend dan frontend

---

## 9. Ringkasan pola yang bisa Anda tiru

Backend:
- migration → model → controller → route

Frontend:
- helper API → halaman Next.js → tampilkan data ke tabel

Kalau Anda mau, saya bisa lanjutkan ke versi yang lebih dekat dengan project Anda, misalnya membuat walkthrough untuk tabel master yang mirip struktur `data_*` di project Laravel Anda.
