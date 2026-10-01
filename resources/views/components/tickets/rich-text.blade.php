@props([
    'html' => null,
    'inverted' => false,
])

{{-- $html comes from the SanitizedHtml cast, so it is safe to print unescaped. --}}
<div {{ $attributes->class([
    'prose prose-sm max-w-none wrap-break-word [&>:first-child]:mt-0 [&>:last-child]:mb-0',
    'prose-a:break-all prose-pre:whitespace-pre-wrap',
    'prose-invert' => $inverted,
    'prose-zinc dark:prose-invert' => ! $inverted,
]) }}>{!! $html !!}</div>
