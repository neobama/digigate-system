# Experimental AI Feature Setup

Fitur experimental untuk auto-parse invoice/bon menggunakan Google Gemini API.

## Setup

1. **Tambahkan GEMINI_API_KEY di `.env`:**
   ```env
   GEMINI_API_KEY=AIzaSyAlc0OhtgZqb79WEWms6JUqCKzD1IHeuBo
   ```

2. **Clear config cache (jika sudah di-cache):**
   ```bash
   php artisan config:clear
   ```

## Fitur yang Tersedia

### 1. Reimbursement - Auto Fill dari Invoice

- **Lokasi**: Employee Portal → Reimbursement → Request Reimbursement Baru
- **Cara Pakai**:
  1. Klik "Request Reimbursement Baru"
  2. Buka section "Experimental AI - Auto Fill dari Invoice"
  3. Upload invoice/bon
  4. Form akan otomatis terisi dengan:
     - Purpose (keperluan)
     - Expense Date (tanggal pengeluaran)
     - Amount (jumlah)
     - Description (keterangan)
  5. Periksa dan edit jika diperlukan
  6. Upload bukti pembayaran (jika belum)
  7. Submit

### 2. Component - Bulk Add dengan AI

- **Lokasi**: Employee Portal → Component → Bulk Add (Experimental AI)
- **Cara Pakai**:
  1. Klik button "Bulk Add (Experimental AI)"
  2. Upload invoice
  3. AI akan auto-detect semua items dari invoice
  4. Repeater akan terisi otomatis dengan:
     - Name (tipe komponen)
     - Supplier
     - Purchase Date
  5. **Wajib**: Isi Serial Number (SN) untuk setiap item
  6. Edit atau tambah item jika diperlukan
  7. Submit untuk create semua komponen sekaligus

## Catatan

- Fitur ini adalah **experimental** dan mungkin tidak 100% akurat
- Selalu **periksa dan edit** hasil parsing sebelum submit
- Jika parsing gagal, isi form secara manual
- API key disimpan di `.env` dan tidak boleh di-commit ke Git

## Troubleshooting

### Error: "API key tidak valid"
- Pastikan `GEMINI_API_KEY` sudah di-set di `.env`
- Clear config cache: `php artisan config:clear`

### Parsing tidak akurat
- Pastikan invoice/bon jelas dan terbaca
- Coba crop image untuk fokus ke area penting
- Edit manual hasil parsing jika diperlukan

### Timeout error
- Pastikan koneksi internet stabil
- Coba lagi setelah beberapa saat

