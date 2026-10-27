<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard');
    }

    public function tampilProfil()
    {
        return view('admin.profil');
    }

    public function updateProfil(Request $request)
    {
        $messages = [
            'nama.required' => 'Nama harus diisi.',
            'alamat.required' => 'Alamat harus diisi.',
            'image.image' => 'File yang diunggah harus berupa gambar.',
            'image.max' => 'Max ukuran file gambar 1MB.',
        ];

        $validatedData = $request->validate([
            'nama' => 'required|string|max:128',
            'alamat' => 'required',
            'image' => 'image|mimes:jpeg,jpg,png|file|max:1024', // Maksimal ukuran file 1MB
        ], $messages);

        if ($request->file('image')) {
            if ($request->oldImage && $request->oldImage <> 'profil-pic/default.jpg') {
                // Hapus gambar lama jika ada dan bukan gambar default
                Storage::delete($request->oldImage);
            }
            $validatedData['image'] = $request->file('image')->store('profil-pic');
        }
        User::where('id', Auth::user()->id)->update($validatedData);
        return redirect()->route('admin.profil')->with('success', 'Profil berhasil diperbarui.');
    }   

    public function tampilGantiPassword()
    {
        return view('admin.ganti_password');
    }

    public function updateGantiPassword(Request $request)
    {
        $messages = [
            'password_saat_ini.required' => 'Password saat ini harus diisi.',
            'password_saat_ini.min' => 'minimal 8 karakter.',
            'password_baru.required' => 'Password baru harus diisi.',
            'password_baru.min' => 'Password baru minimal 8 karakter.',
            'konfirmasi_password.required' => 'Konfirmasi password baru harus diisi.',
            'konfirmasi_password.min' => 'Konfirmasi password baru minimal 8 karakter.',
            'konfirmasi_password.same' => 'Konfirmasi password baru tidak sesuai.',
        ];

        $validatedData = $request->validate([
            'password_saat_ini' => 'required|string|min:8',
            'password_baru' => 'required|string|min:8',
            'konfirmasi_password' => 'required|string|min:8|same:password_baru',
        ], $messages);

        $cekPassword = Hash::check($request->password_saat_ini, auth()->user()->password);

        if (!$cekPassword) {
            return redirect()->back()->with('error', 'Gagal mengupdate password. Password saat ini salah.');
        }

        User::where('id', Auth::user()->id)->update([
            'password' => Hash::make($request->password_baru),
        ]);
        return redirect()->route('admin.ganti-password')->with('success', 'Password berhasil diupdate.');
    }
}
