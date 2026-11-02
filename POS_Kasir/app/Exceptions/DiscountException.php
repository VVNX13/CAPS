<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kupon/diskon tidak bisa dipakai. Pesannya aman ditampilkan ke pengguna.
 */
class DiscountException extends RuntimeException
{
}
