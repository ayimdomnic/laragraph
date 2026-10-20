<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A regular Laravel FormRequest — the same class a REST endpoint could use
 * for the same input. UpdateProfileMutation reuses it through the
 * formRequest() hook instead of duplicating the rules, so a validation change
 * lives in exactly one place.
 *
 * Only rules()/messages()/attributes() are read by Laragraph; authorize() is
 * never called (authorization stays on the field — see UpdateProfileMutation).
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
        ];
    }
}
