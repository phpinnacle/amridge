<?php

use PHPinnacle\Goridge\Exception;

function goridge_encode($data): string
{
    $payload = json_encode($data);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception\JSONException(json_last_error_msg());
    }

    return $payload;
}

function goridge_decode(string $data)
{
    $data = \json_decode($data, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception\JSONException(json_last_error_msg());
    }

    return $data;
}
