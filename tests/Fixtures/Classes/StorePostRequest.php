<?php

namespace LaravelOptimized\Tests\Fixtures\Classes;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'min:3']];
    }
}
