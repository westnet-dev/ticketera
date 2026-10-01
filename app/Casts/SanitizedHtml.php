<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Rich text written with <x-tickets.rich-editor>, safe to render with {!! !!}.
 *
 * Values are sanitized on write and again on read: rows saved before the rich editor
 * existed hold unsanitized plain text, which is escaped into paragraphs on the way out.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class SanitizedHtml implements CastsAttributes
{
    private static ?HtmlSanitizer $sanitizer = null;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::clean((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $html = self::clean((string) $value);

        return self::toPlainText($html) === '' ? null : $html;
    }

    /**
     * The visible text of a rich text value, used to validate its length.
     */
    public static function toPlainText(?string $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private static function clean(string $value): string
    {
        return self::sanitizer()->sanitize(self::looksLikeHtml($value) ? $value : self::fromPlainText($value));
    }

    /**
     * Tiptap output always starts with a block element; anything else is legacy plain text.
     */
    private static function looksLikeHtml(string $value): bool
    {
        return preg_match('/^\s*<(p|ul|ol|pre|blockquote|h[1-6]|hr)[\s>\/]/i', $value) === 1;
    }

    private static function fromPlainText(string $value): string
    {
        $paragraphs = preg_split('/\R{2,}/u', trim($value)) ?: [];

        return collect($paragraphs)
            ->map(fn (string $paragraph): string => '<p>'.preg_replace('/\R/u', '<br>', e($paragraph)).'</p>')
            ->implode('');
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                // Keep in sync with the Tiptap extensions enabled in rich-editor.js
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('em')
                ->allowElement('s')
                ->allowElement('u')
                ->allowElement('code')
                ->allowElement('pre')
                ->allowElement('blockquote')
                ->allowElement('ul')
                ->allowElement('ol', ['start'])
                ->allowElement('li')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('hr')
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['https', 'http', 'mailto'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->forceAttribute('a', 'target', '_blank')
                ->withMaxInputLength(50_000)
        );
    }
}
