<?php

namespace App\Http\Requests\Admin;

use App\Models\Program;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExisting = $this->filled('existing_student_id');

        return [
            'existing_student_id' => 'nullable|exists:students,id',
            'new_student.name' => $isExisting ? 'nullable' : 'required|string|max:255',
            'new_student.email' => $isExisting ? 'nullable' : 'required|email|unique:users,email',
            'new_student.phone' => 'nullable|string|max:20',
            'new_student.education_level' => 'nullable|in:SD,SMP,SMA,Kuliah,Umum',
            'program_id' => 'required|exists:programs,id',
            'class_session_id' => 'nullable|exists:class_sessions,id',
            'enrollment_date' => 'required|date',
            'expiry_date' => 'required|date|after:enrollment_date',
            'payment_method' => 'required|in:full upfront,installment',
            'payment_channel' => 'required|in:cash,bank',
            'total_amount' => 'nullable|numeric|min:0',
            'remaining_meetings' => 'nullable|integer|min:0',
            // Schedules sekarang nullable — kalau kosong = waitlist
            'schedules' => 'nullable|array',
            'schedules.*.classroom_id' => 'required_with:schedules|exists:classrooms,id',
            'schedules.*.day' => 'required_with:schedules|string',
            'schedules.*.time_block' => 'required_with:schedules|string',
            'tutor_ids' => 'nullable|array',
            'tutor_ids.*' => 'exists:tutors,id',
            'installments' => 'required_if:payment_method,installment|array',
            'installments.*.amount' => 'required_with:installments|numeric|min:0',
            'installments.*.due_date' => 'required_with:installments|date',
            'installments.*.payment_channel' => 'nullable|in:cash,bank',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        \Log::error('Enrollment validation failed', $validator->errors()->toArray());
        parent::failedValidation($validator);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('payment_method') === 'full upfront' && empty($this->input('total_amount'))) {
                $validator->errors()->add('total_amount', 'Total amount wajib diisi untuk pembayaran full upfront.');
            }

            if ($this->input('payment_method') === 'installment' && ! empty($this->input('installments'))) {
                // "Biaya Aktual" di form adalah override OPSIONAL — kalau dikosongkan
                // (jalur paling umum), EnrollmentService::enroll() jatuh ke harga
                // program apa adanya. Cek kecocokan cicilan HARUS memakai total yang
                // SAMA PERSIS dengan yang nanti benar-benar dipakai sebagai
                // enrollment.total_amount (basis revenue recognition) — kalau cuma
                // dicek saat total_amount diisi manual, enrollment dengan harga
                // default (paling sering terjadi) lolos tanpa verifikasi sama sekali,
                // dan cicilan yang salah jumlah baru ketahuan setelah data tersimpan.
                $effectiveTotal = $this->input('total_amount');
                if (empty($effectiveTotal) && $this->filled('program_id')) {
                    $effectiveTotal = Program::find($this->input('program_id'))?->price;
                }

                if (! empty($effectiveTotal)) {
                    $installmentSum = collect($this->input('installments'))->sum('amount');
                    if (abs($installmentSum - (float) $effectiveTotal) > 1) {
                        $validator->errors()->add('installments', 'Total cicilan (Rp '.number_format($installmentSum).') harus sama dengan total harga (Rp '.number_format($effectiveTotal).').');
                    }
                }
            }
        });
    }
}
