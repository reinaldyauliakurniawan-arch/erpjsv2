<?php

namespace App\Http\Requests\Admin;

use App\Models\ClassSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * Validasi edit enrollment oleh admin (koreksi data kapan pun).
 * Aturan lintas-field: total cicilan harus == total_amount, class session
 * harus milik program yang dipilih, expiry setelah enrollment_date.
 * Dependency akuntansi (revenue recognition) di-guard di controller, bukan di sini.
 */
class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'program_id' => 'required|exists:programs,id',
            'class_session_id' => 'nullable|exists:class_sessions,id',
            'enrollment_date' => 'required|date',
            'expiry_date' => 'required|date|after:enrollment_date',
            'payment_method' => 'required|in:full upfront,installment',
            'payment_channel' => 'required|in:cash,bank',
            'total_amount' => 'required|numeric|min:0',
            // payment_status diturunkan otomatis di controller (dari kas yg masuk),
            // bukan dari input admin.
            'payment_status' => 'nullable|in:pending,partial,full',
            'status' => 'required|in:active,waitlist,graduate,expired,cancelled,refunded',
            'remaining_meetings' => 'required|integer|min:0',

            'installments' => 'nullable|array',
            'installments.*.id' => 'nullable|integer',
            'installments.*.amount' => 'required_with:installments|numeric|min:0',
            'installments.*.due_date' => 'required_with:installments|date',
            'installments.*.payment_channel' => 'nullable|in:cash,bank',
            'installments.*.paid' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $method = $this->input('payment_method');
            $rows = $this->input('installments', []);
            $total = (float) $this->input('total_amount');

            if ($method === 'installment') {
                if (empty($rows)) {
                    $validator->errors()->add('installments', 'Metode installment butuh minimal satu baris cicilan.');
                } else {
                    $sum = collect($rows)->sum(fn ($r) => (float) ($r['amount'] ?? 0));
                    if (abs($sum - $total) > 1) {
                        $validator->errors()->add(
                            'installments',
                            'Total cicilan (Rp '.number_format($sum).') harus sama dengan total biaya (Rp '.number_format($total).').'
                        );
                    }
                }
            }

            if ($this->filled('class_session_id')) {
                $cs = ClassSession::find($this->input('class_session_id'));
                if ($cs && (int) $cs->program_id !== (int) $this->input('program_id')) {
                    $validator->errors()->add('class_session_id', 'Class session yang dipilih bukan milik program ini.');
                }
            }
        });
    }

    public function failedValidation(Validator $validator)
    {
        Log::error('Enrollment update validation failed', $validator->errors()->toArray());
        parent::failedValidation($validator);
    }
}
