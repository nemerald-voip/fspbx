<?php

namespace App\Services\Interfaces;

interface MessageProviderInterface {
    public function send($data);
}