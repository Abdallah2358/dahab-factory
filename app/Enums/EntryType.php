<?php

namespace App\Enums;

enum EntryType: string
{
    case ClientPayment = 'client_payment';
    case RawMaterial   = 'raw_material';
    case Expense       = 'expense';
    case Labor         = 'labor';

    public function direction(): string
    {
        return match($this) {
            self::ClientPayment => 'in',
            default             => 'out',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::ClientPayment => 'تحصيل من عميل',
            self::RawMaterial   => 'مواد خام',
            self::Expense       => 'مصروفات',
            self::Labor         => 'عمالة',
        };
    }
}
