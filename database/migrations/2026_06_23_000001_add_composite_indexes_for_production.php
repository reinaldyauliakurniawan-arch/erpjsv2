<?php

/**
 * Production-grade composite indexes for the most frequent query patterns.
 *
 * Why composite indexes?
 *   Single-column indexes help WHERE a = ? but not WHERE a = ? AND b = ?.
 *   Composite indexes (a, b, c) cover all of:
 *     - WHERE a = ?
 *     - WHERE a = ? AND b = ?
 *     - WHERE a = ? AND b = ? AND c = ?
 *   And can also serve ORDER BY a, b, c.
 *
 * This migration is SAFE for production (online DDL on MySQL 8+):
 *   - Adding indexes is non-blocking for InnoDB with ALGORITHM=COPY off
 *   - The migration can be reverted (dropIndex) without data loss
 *
 * Zero-downtime migration strategy (Expand & Contract):
 *   1. Deploy the new code that reads using the new indexes
 *   2. Run this migration (adds indexes — non-blocking)
 *   3. Verify query performance via EXPLAIN
 *   4. (Optional) later migration can drop unused single-column indexes
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // SCHEDULES — queried by (classroom_id, day, time_block) for
        // room occupancy checks during enrollment
        // ============================================================
        Schema::table('schedules', function (Blueprint $table) {
            // Single composite index for the most common lookup
            $table->index(['classroom_id', 'day', 'time_block'], 'schedules_room_day_block_idx');
            // For tutor availability / class session lookups
            $table->index(['class_session_id', 'day', 'time_block'], 'schedules_session_day_block_idx');
        });

        // ============================================================
        // ATTENDANCE — queried by (date, time_block, class_session_id)
        // for duplicate detection and daily attendance reports
        // ============================================================
        Schema::table('attendance', function (Blueprint $table) {
            $table->index(['date', 'time_block', 'class_session_id'], 'attendance_date_block_session_idx');
            $table->index(['class_session_id', 'date'], 'attendance_session_date_idx');
        });

        // ============================================================
        // ATTENDANCE_TUTOR — queried by (tutor_id, paid_at) for payroll
        // and by (attendance_id, tutor_id) for record lookup
        // ============================================================
        Schema::table('attendance_tutor', function (Blueprint $table) {
            // PayrollService: WHERE tutor_id = ? AND paid_at IS NULL AND pending_rate = false
            // Composite index allows MySQL to use index-only scan
            $table->index(['tutor_id', 'paid_at', 'pending_rate'], 'att_tutor_payroll_idx');
            $table->index(['attendance_id', 'tutor_id'], 'att_tutor_session_idx');
        });

        // ============================================================
        // ENROLLMENTS — queried by (student_id, status) and
        // (class_session_id, status) for active/waitlist counting
        // ============================================================
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'enrollments_student_status_idx');
            $table->index(['class_session_id', 'status'], 'enrollments_session_status_idx');
            $table->index(['status', 'payment_status'], 'enrollments_status_payment_idx');
        });

        // ============================================================
        // INSTALLMENTS — queried by (enrollment_id, paid_at) for
        // unpaid count and (due_date, paid_at) for overdue reminders
        // ============================================================
        Schema::table('installments', function (Blueprint $table) {
            $table->index(['enrollment_id', 'paid_at'], 'installments_enroll_paid_idx');
            // due_date + paid_at already have single indexes;
            // composite is better for "WHERE due_date < ? AND paid_at IS NULL"
            $table->index(['paid_at', 'due_date'], 'installments_paid_due_idx');
        });

        // ============================================================
        // JOURNALS — queried by (type, date) for reports and
        // (reference) for idempotency (already unique-indexed)
        // ============================================================
        Schema::table('journals', function (Blueprint $table) {
            // Reports often filter by date range AND type
            $table->index(['date', 'type'], 'journals_date_type_idx');
        });

        // ============================================================
        // JOURNAL_ITEMS — queried by (account_id, journal_id) for
        // general ledger reports and trial balance
        // ============================================================
        Schema::table('journal_items', function (Blueprint $table) {
            // General ledger: WHERE account_id = ? JOIN journals ON date BETWEEN ? AND ?
            $table->index(['account_id', 'journal_id'], 'ji_account_journal_idx');
        });

        // ============================================================
        // TUTOR_AVAILABILITY — queried by (tutor_id, day, time_block)
        // (already unique-indexed) but also by (status) for stats
        // ============================================================
        Schema::table('tutor_availability', function (Blueprint $table) {
            $table->index(['status', 'tutor_id'], 'ta_status_tutor_idx');
        });

        // ============================================================
        // ROOM_BOOKINGS — queried by (classroom_id, date) for conflict
        // detection and by (date) for daily schedule view
        // ============================================================
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->index(['classroom_id', 'date', 'time_block'], 'rb_room_date_block_idx');
        });

        // ============================================================
        // USERS — soft-delete + role filter for user listings
        // ============================================================
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'email'], 'users_role_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_room_day_block_idx');
            $table->dropIndex('schedules_session_day_block_idx');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_date_block_session_idx');
            $table->dropIndex('attendance_session_date_idx');
        });

        Schema::table('attendance_tutor', function (Blueprint $table) {
            $table->dropIndex('att_tutor_payroll_idx');
            $table->dropIndex('att_tutor_session_idx');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_student_status_idx');
            $table->dropIndex('enrollments_session_status_idx');
            $table->dropIndex('enrollments_status_payment_idx');
        });

        Schema::table('installments', function (Blueprint $table) {
            $table->dropIndex('installments_enroll_paid_idx');
            $table->dropIndex('installments_paid_due_idx');
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex('journals_date_type_idx');
        });

        Schema::table('journal_items', function (Blueprint $table) {
            $table->dropIndex('ji_account_journal_idx');
        });

        Schema::table('tutor_availability', function (Blueprint $table) {
            $table->dropIndex('ta_status_tutor_idx');
        });

        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropIndex('rb_room_date_block_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_email_idx');
        });
    }
};
