<?php

namespace App\Http\Controllers\Member;

use App\Models\Buku;
use App\Models\Temp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;

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
        $dataKeranjang = Temp::with('buku', 'buku.kategori')
            ->where('id_user', $user->id)->get();
        if (count($dataKeranjang) == 0) {
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

    public function hapusKeranjang($id)
    {
        $temp = Temp::findOrFail($id);

        if ($temp->id_user != Auth::user()->id) {
            return redirect()->back()->with('error', 'Akses tidak diizinkan.');
        }

        $temp->delete();

        return redirect()->back()->with('success', 'Buku berhasil dihapus dari keranjang.');
    }
}