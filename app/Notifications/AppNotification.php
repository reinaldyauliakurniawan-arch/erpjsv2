<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app generik (disimpan ke tabel `notifications`, tampil di
 * lonceng topbar). Satu kelas untuk semua jenis supaya gampang ditambah;
 * `type` dipakai untuk ikon / filter.
 */
class AppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $body = '',
        public ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => self::iconFor($this->type),
        ];
    }

    public static function iconFor(string $type): string
    {
        return [
            'tutor_assigned' => 'person_add',
            'tutor_confirmed' => 'verified',
            'new_student' => 'group_add',
            'pending_rate' => 'payments',
            'payroll_paid' => 'account_balance_wallet',
            'installment_overdue' => 'schedule',
            'enrollment_expiring' => 'hourglass_bottom',
            'attendance' => 'fact_check',
        ][$type] ?? 'notifications';
    }
}
