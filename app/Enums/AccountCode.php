<?php

namespace App\Enums;

enum AccountCode: string
{
    case CASH = '1001';
    case BANK = '1002';
    case DEFERRED_REVENUE = '2002';
    case TUTOR_PAYABLE = '2003';
    case REVENUE_TUITION_FEES = '4101';
    case REVENUE_ADMIN_FEE = '4102';
    case EXPENSE_TUTOR_FEE = '5101';
    case EXPENSE_DISCOUNT_PROMO = '5102';
    case EXPENSE_REFUND = '5103';
}
