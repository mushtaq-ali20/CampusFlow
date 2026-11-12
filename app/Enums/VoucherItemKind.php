<?php

namespace App\Enums;

enum VoucherItemKind: string
{
    case Fee = 'fee';
    case Discount = 'discount';
    case Fine = 'fine';
}
