<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Menampilkan data profil pengguna yang sedang login.
     */
    public function show(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'User profile fetched successfully.',
            'user' => $request->user(),
        ]);
    }

    /**
     * Memperbarui profil pengguna yang sedang login.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'avatar' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // ukuran maksimal 2MB
            ],
        ]);

        // Update data teks
        if ($request->has('name')) {
            $user->name = $validatedData['name'];
        }

        if ($request->has('phone')) {
            $user->phone = $validatedData['phone'];
        }

        if ($request->has('address')) {
            $user->address = $validatedData['address'];
        }

        // Update avatar jika ada file
        if ($request->hasFile('avatar')) {
            // Hapus avatar lama jika ada
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Simpan avatar baru dan ambil path relatifnya
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path; // simpan seperti 'avatars/nama_file.png'
        }

        // Simpan perubahan ke database
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui!',
            'user' => $user,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        // Validasi input
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Cek apakah password saat ini sesuai
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini tidak sesuai.'],
            ]);
        }

        // Update password baru
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui!',
        ]);
    }
}
