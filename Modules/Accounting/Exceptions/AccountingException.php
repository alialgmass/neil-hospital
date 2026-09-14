<?php

namespace Modules\Accounting\Exceptions;

use RuntimeException;

/**
 * Thrown whenever a caller attempts to post an invalid journal entry:
 * an unknown account code, a non-postable/summary account, an inactive
 * account, a zero/negative amount, or debit == credit.
 */
class AccountingException extends RuntimeException {}
