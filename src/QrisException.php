<?php

declare(strict_types=1);

namespace QrisDinamis;

/**
 * Error untuk semua kegagalan validasi/konversi QRIS.
 *
 * Properti $errorCode stabil (aman dipakai di if/switch atau unit test),
 * sedangkan getMessage() berisi penjelasan dalam bahasa Indonesia.
 */
final class QrisException extends \InvalidArgumentException
{
    public const EMPTY_INPUT = 'EMPTY_INPUT';
    public const NOT_QRIS = 'NOT_QRIS';
    public const BAD_TLV = 'BAD_TLV';
    public const DUPLICATE_TAG = 'DUPLICATE_TAG';
    public const MISSING_TAG = 'MISSING_TAG';
    public const BAD_TAG_VALUE = 'BAD_TAG_VALUE';
    public const BAD_CRC = 'BAD_CRC';
    public const ALREADY_DYNAMIC = 'ALREADY_DYNAMIC';
    public const INVALID_AMOUNT = 'INVALID_AMOUNT';
    public const INVALID_OPTION = 'INVALID_OPTION';
    public const QR_TOO_LONG = 'QR_TOO_LONG';
    public const GD_MISSING = 'GD_MISSING';

    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
