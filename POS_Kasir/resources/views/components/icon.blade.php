@props(['name', 'size' => null])
{{-- Inline line icon. Usage: <x-icon name="shopping-cart" /> — paths live in App\Support\Icon. --}}
{!! \App\Support\Icon::svg($name, $size ? (int) $size : null, (string) $attributes->get('class', '')) !!}
