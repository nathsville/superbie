<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super Admin — Bulk market price entry validation (PHASE S / SIPHP module).
 *
 * One submission covers ALL items of one market for one date (D-S6: one price
 * per market × item × date). An empty price input means "leave unchanged" and
 * is skipped by the controller — it is NOT stored as zero. Prices are whole
 * rupiah (integer, min 0, max fits DECIMAL(14,2)).
 */
class SaveMarketPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->role === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'rows'           => ['required', 'array'],
            'rows.*.item_id' => ['required', 'integer', 'exists:market_items,id'],
            'rows.*.price'   => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_date.required'    => 'Tanggal berlaku wajib diisi.',
            'effective_date.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'rows.required'              => 'Tidak ada data barang yang dikirim.',
            'rows.*.item_id.required'    => 'Baris barang tidak lengkap.',
            'rows.*.item_id.exists'      => 'Ada barang yang tidak ditemukan.',
            'rows.*.price.integer'       => 'Harga harus berupa angka rupiah tanpa desimal.',
            'rows.*.price.min'           => 'Harga tidak boleh negatif.',
            'rows.*.price.max'           => 'Harga melebihi batas yang dapat disimpan.',
        ];
    }
}
