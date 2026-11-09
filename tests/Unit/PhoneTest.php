<?php

use App\Support\Phone;

it('normalizes egyptian phone formats', function (string $input, string $expected) {
    expect(Phone::normalize($input))->toBe($expected)
        ->and(Phone::isValid($input))->toBeTrue();
})->with([
    'local' => ['01012345678', '01012345678'],
    'plus 20' => ['+201112345678', '01112345678'],
    'double zero 20' => ['00201212345678', '01212345678'],
    'country code no plus' => ['201512345678', '01512345678'],
    'missing leading zero' => ['1012345678', '01012345678'],
    'spaces and dashes' => ['010 1234-5678', '01012345678'],
    'plus 20 with spaces' => ['+20 10 1234 5678', '01012345678'],
    'arabic-indic digits' => ['٠١٠١٢٣٤٥٦٧٨', '01012345678'],
    'arabic digits with +20' => ['+٢٠١٠١٢٣٤٥٦٧٨', '01012345678'],
    'mixed digits' => ['010١٢٣٤٥٦٧٨', '01012345678'],
]);

it('rejects invalid phones', function (?string $input) {
    expect(Phone::isValid($input))->toBeFalse()
        ->and(Phone::normalizeOrNull($input))->toBeNull();
})->with([
    'landline' => ['0223456789'],
    'wrong operator' => ['01312345678'],
    'too short' => ['0101234567'],
    'too long' => ['010123456789'],
    'letters' => ['abc'],
    'empty' => [''],
    'null' => [null],
]);
