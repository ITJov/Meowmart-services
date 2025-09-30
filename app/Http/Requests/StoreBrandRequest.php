<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    /**
     * Tentukan apakah user diizinkan membuat request ini.
     */
    public function authorize(): bool
    {
        return true; // Izinkan semua user yang sudah login
    }

    /**
     * Dapatkan aturan validasi yang berlaku untuk request ini.
     */
    public function rules(): array
    {
        // Ambil ID brand dari rute jika ada (untuk proses update)
        $brandId = $this->route('brand') ? $this->route('brand')->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands')->ignore($brandId),
            ],
            'image' => ['nullable', 'image', 'max:2048'], // Boleh kosong, harus gambar, maks 2MB
        ];
    }
}
