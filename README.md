# Joy Gadai Cemerlang (JGC)
## Sistem Penaksiran Barang Jaminan Berbasis Machine Learning

Sistem Penaksiran Barang Jaminan Joy Gadai Cemerlang (JGC) merupakan aplikasi berbasis web yang digunakan untuk membantu proses penaksiran nilai barang jaminan secara lebih cepat, terstruktur, dan terdokumentasi.

Sistem ini mengintegrasikan aplikasi administrasi berbasis **PHP Native**, database **MySQL**, serta layanan **Machine Learning API menggunakan Python Flask** untuk melakukan prediksi harga pasar barang berdasarkan karakteristik barang yang dimasukkan oleh pengguna.

---
## 📌 Tentang JGC

**Joy Gadai Cemerlang (JGC)** merupakan layanan jasa gadai yang menyediakan solusi pembiayaan dengan menggunakan barang bernilai sebagai jaminan.

Sistem ini dikembangkan untuk membantu proses:

- Pendataan barang jaminan
- Penaksiran harga pasar
- Perhitungan nilai taksiran
- Estimasi jumlah pinjaman
- Penyimpanan riwayat penaksiran
- Analisis hasil prediksi Machine Learning
- Monitoring data penaksiran

---

# 🎯 Tujuan Sistem

Sistem ini bertujuan untuk:

1. Membantu petugas melakukan penaksiran barang secara lebih cepat.
2. Memberikan estimasi harga pasar berdasarkan data barang.
3. Mengurangi ketergantungan terhadap proses penaksiran manual.
4. Menyimpan riwayat hasil penaksiran.
5. Membantu menentukan estimasi nilai pinjaman.
6. Menyediakan informasi yang dapat digunakan sebagai pendukung keputusan petugas JGC.

> **Catatan:** Hasil Machine Learning merupakan estimasi/pendukung keputusan dan bukan pengganti pemeriksaan fisik serta kebijakan penaksiran resmi JGC.

---
# 🚀 Fitur Utama

## 1. Dashboard Penaksiran

Dashboard menampilkan informasi ringkas mengenai:

- Total penaksiran
- Total nilai taksiran
- Total estimasi pinjaman
- Aktivitas penaksiran terbaru

---

## 2. Form Penaksiran Barang

Petugas dapat memasukkan informasi barang seperti:

- Kategori
- Merek
- Model
- Harga baru
- Umur barang
- RAM
- Kapasitas penyimpanan
- Kondisi fisik
- Kondisi fungsi
- Kelengkapan barang

Contoh kategori:

- Laptop
- HP
- Elektronik
- Motor
- Mobil
- Emas
- Lainnya

---

## 3. Prediksi Harga Pasar

Data dari form dikirim dari PHP ke Machine Learning API menggunakan HTTP request.

Alur sistem:

```text
Pengguna
   ↓
Form Penaksiran JGC
   ↓
PHP Native
   ↓
Flask Machine Learning API
   ↓
Model Machine Learning
   ↓
Prediksi Harga Pasar
   ↓
PHP
   ↓
Nilai Taksiran
   ↓
Estimasi Pinjaman
