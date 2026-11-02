<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use App\Support\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kelola kupon promo (khusus admin): kupon berlaku untuk seluruh toko.
 */
class PromoCodeController extends Controller
{
    public function index(): View
    {
        return view('owner.promos');
    }

    public function list(): JsonResponse
    {
        $promos = PromoCode::orderByDesc('is_active')->orderByDesc('id')->get()
            ->map(fn (PromoCode $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'type' => $p->type,
                'value' => (float) $p->value,
                'label' => $p->label(),
                'min_order' => (float) $p->min_order,
                'max_discount' => $p->max_discount !== null ? (float) $p->max_discount : null,
                'usage_limit' => $p->usage_limit,
                'used_count' => $p->used_count,
                'starts_at' => $p->starts_at?->format('Y-m-d'),
                'ends_at' => $p->ends_at?->format('Y-m-d'),
                'is_active' => $p->is_active,
                // Alasan tidak berlaku saat ini (selain minimal belanja), untuk badge status.
                'status_note' => $p->unavailableReason(PHP_FLOAT_MAX),
            ]);

        return ApiResponse::success(['promos' => $promos]);
    }

    public function save(Request $request): JsonResponse
    {
        $request->merge(['code' => PromoCode::normalizeCode($request->input('code'))]);
        $id = $request->integer('id') ?: null;

        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:promo_codes,id'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('promo_codes', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:9999999999'],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'max_discount' => ['nullable', 'numeric', 'min:1', 'max:9999999999'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date_format:Y-m-d'],
            'ends_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Kode kupon wajib diisi',
            'code.regex' => 'Kode kupon hanya boleh huruf, angka, - dan _',
            'code.unique' => 'Kode kupon sudah dipakai',
            'name.required' => 'Nama promo wajib diisi',
            'value.required' => 'Nilai potongan wajib diisi',
            'value.max' => 'Persentase maksimal 100%',
            'ends_at.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai',
        ]);

        $attributes = [
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order' => $data['min_order'] ?? 0,
            'max_discount' => $data['type'] === 'percent' ? ($data['max_discount'] ?? null) : null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        $promo = $id ? tap(PromoCode::findOrFail($id))->update($attributes) : PromoCode::create($attributes);

        return ApiResponse::success(['id' => $promo->id], "Kupon {$promo->code} disimpan");
    }

    public function toggle(PromoCode $promo): JsonResponse
    {
        $promo->update(['is_active' => ! $promo->is_active]);

        return ApiResponse::success(
            ['is_active' => $promo->is_active],
            "Kupon {$promo->code} ".($promo->is_active ? 'diaktifkan' : 'dinonaktifkan')
        );
    }

    public function destroy(PromoCode $promo): JsonResponse
    {
        // Transaksi lama menyimpan kode kupon sebagai teks, jadi riwayatnya tetap utuh.
        $promo->delete();

        return ApiResponse::success(null, "Kupon {$promo->code} dihapus");
    }
}
