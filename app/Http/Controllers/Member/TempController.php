<?php

namespace App\Http\Controllers\Member;

use App\Models\Buku;
use App\Models\Temp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TempController extends Controller
{
    public function tambahKeranjang(Request $request)
    {
        // Cek apakah buku sudah ada di keranjang user ini
        $cekKeranjang = Temp::where('id_buku', $request->id)
            ->where('id_user', Auth::user()->id)
            ->first();

        if ($cekKeranjang) {
            return redirect()->back()->with('info', 'Buku sudah ada di keranjang.');
        }

        // Cek total buku di keranjang (max 3)
        $totalKeranjang = Temp::where('id_user', Auth::user()->id)->count();

        if ($totalKeranjang >= 3) {
            return redirect()->back()->with('error', 'Keranjang penuh! Maksimal 3 buku.');
        }

        // Cek stok buku
        $buku = Buku::find($request->id);

        if (!$buku || $buku->stok <= 0) {
            return redirect()->back()->with('error', 'Stok buku tidak tersedia.');
        }

        // Simpan ke tabel temp (keranjang)
        Temp::create([
            'id_buku' => $request->id,
            'id_user' => Auth::user()->id,
        ]);

        return redirect()->back()->with('success', 'Buku berhasil ditambahkan ke keranjang.');
    }

    public function dataKeranjang(User $user)
    {
        $data_keranjang = Temp::with('buku', 'buku.kategori')
            ->where('id_user', $user->id)->get();
        if (count($data_keranjang) == 0) {
            return redirect()->route('member.index')->with('info', 'Tidak ada buku di keranjang.');
        }
        return view('member.data_keranjang', compact('data_keranjang'));
    }

    public function keranjang()
    {
        $keranjang = Temp::with('buku.kategori')
            ->where('id_user', Auth::user()->id)
            ->get();

        return view('member.keranjang', compact('keranjang'));
    }

    public function hapusKeranjang($buku, $user)
    {
        $temp = Temp::where('id_buku', $buku)
            ->where('id_user', $user)
            ->delete();
        return redirect()->back()->with('success', 'Buku berhasil dihapus dari keranjang.');
    }

    public function simpanBooking(Request $request)
    {
        $cek_stok = Temp::with('buku')
            ->where('id_user', $request->id)->get();
            //loop melalui setiap buku untuk memeriksa stok
        foreach ($cek_stok as $temp) {
            if ($temp->buku->stok <= 0) {
                // Redirect back dengan pesan kesalahan jika stok buku kosong
                return redirect()->back()->with('error', 'Ada buku yang stoknya kosong, silakan hapus terlebih dahulu.');

            }
        }
        // Jika semua buku memiliki stok, lanjutkan dengan menyimpan booking
        // Generate id_booking
        $today = Carbon::today()->format('Ymd');

        //Mendapatkan id_booking terbaru dari tabel booking
        $latestBooking = DB::table('booking')
            ->whereDate('tgl_booking', Carbon::today())
            ->orderBy('id', 'desc')
            ->first();

        $latestBookingId = $latestBooking ? intval(substr($latestBooking->id_booking, -3)) : 0;
        
        //Mendapatkan id_booking baru dari tabel pinjam
        $latestPinjam = DB::table('pinjam')
            ->whereDate('tgl_pinjam', Carbon::today())
            ->orderBy('id', 'desc')
            ->first();

        $latestPinjamId = $latestPinjam ? intval(substr($latestPinjam->id_booking, -3)) : 0;

        // Bandingkan id_booking dari kedua tabel
        $latestBooking = max($latestBookingId, $latestPinjamId);

        //Membuat id_booking baru
        $newIdBooking = 'B' . $today . str_pad($latestBooking + 1, 3, '0', STR_PAD_LEFT);

        //Tanggal booking dan Batas ambil
        $tgl_booking = Carbon::now();
        $batas_ambil = $tgl_booking->copy()->addDay();

        //logika untuk menyimpan booking dan booking
        //simpan ke tabel booking
        DB::beginTransaction();
        try {
            DB::table('booking')->insert([
                'id_booking' => $newIdBooking,
                'tgl_booking' => $tgl_booking,
                'batas_ambil' => $batas_ambil,
                'id_user' => $request->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            //simpan ke tabel booking_detail
            foreach ($cek_stok as $item) {
                DB::table('booking_detail')->insert([
                    'id_booking' => $newIdBooking,
                    'id_buku' => $item->id_buku,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                //update stok buku dan kolom dibooking
                DB::table('buku')->where('id', $item->id_buku)->update([
                    'stok' => DB::raw('stok - 1'),
                    'dibooking' => DB::raw('dibooking + 1'),
                ]);
            }

            //Hapus data dari tabel temp
            Temp::where('id_user', $request->id)->delete();
            DB::commit();
            return redirect()->route('member.index')->with('success', 'Booking berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan saat menyimpan booking.');
        }

    }
}