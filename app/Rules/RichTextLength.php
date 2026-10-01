<?php

namespace App\Rules;

use App\Casts\SanitizedHtml;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Length limits for rich text, counted on the visible text rather than the HTML markup.
 */
class RichTextLength implements ValidationRule
{
    public function __construct(
        public int $min = 0,
        public ?int $max = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $length = mb_strlen(SanitizedHtml::toPlainText(is_string($value) ? $value : ''));

        if ($length < $this->min) {
            $fail('validation.min.string')->translate(['min' => (string) $this->min]);

            return;
        }

        if ($this->max !== null && $length > $this->max) {
            $fail('validation.max.string')->translate(['max' => (string) $this->max]);
        }
    }
}
