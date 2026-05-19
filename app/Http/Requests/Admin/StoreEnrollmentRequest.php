public function rules(): array
{
    $isExisting = $this->filled('existing_student_id');

    return [
        'existing_student_id'      => 'nullable|exists:students,id',
        'new_student.name'         => $isExisting ? 'nullable' : 'required|string|max:255',
        'new_student.email'        => $isExisting ? 'nullable' : 'required|email|unique:users,email',
        'new_student.phone'        => 'nullable|string|max:20',
        'program_id'               => 'required|exists:programs,id',
        'class_session_id'         => 'nullable|exists:class_sessions,id',
        'enrollment_date'          => 'required|date',
        'expiry_date'              => 'required|date|after:enrollment_date',
        'payment_method'           => 'required|string|in:full upfront,installment,ala carte',
        'schedules'                => 'required|array|min:1',
        'schedules.*.classroom_id' => 'required|exists:classrooms,id',
        'schedules.*.day'          => 'required|string',
        'schedules.*.time_block'   => 'required|string',
        'installments'             => 'required_if:payment_method,installment|array',
        'installments.*.amount'    => 'required_with:installments|numeric',
        'installments.*.due_date'  => 'required_with:installments|date',
        'tutor_ids'                => 'nullable|array',
        'tutor_ids.*'              => 'exists:tutors,id',
        'remaining_meetings'       => 'nullable|integer|min:0',
        'total_amount'             => 'nullable|numeric|min:0',
    ];
}
