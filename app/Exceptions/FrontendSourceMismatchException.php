<?php

namespace App\Exceptions;

use RuntimeException;

class FrontendSourceMismatchException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Locally modified frontend files detected.');
    }
}
