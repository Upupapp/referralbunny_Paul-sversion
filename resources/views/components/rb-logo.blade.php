@props([
    'variant'    => 'horizontal',
    'size'       => 'md',
    'decorative' => true,
    'priority'   => false,
    'class'      => '',
    'alt'        => null,
])

@php
$variants = [
    'primary'      => 'referralbunny-primary-logo.webp',
    'horizontal'   => 'referralbunny-horizontal-logo.webp',
    'rectangle'    => 'referralbunny-rectangle-logo.webp',
    'stacked'      => 'referralbunny-stacked-logo.webp',
    'square'       => 'referralbunny-square-logo.webp',
    'icon'         => 'referralbunny-icon-only.webp',
    'wordmark'     => 'referralbunny-wordmark-only.webp',
    'black'        => 'referralbunny-black-logo.webp',
    'white'        => 'referralbunny-white-logo.webp',
    'grayscale'    => 'referralbunny-grayscale-logo.webp',
    'favicon'      => 'referralbunny-favicon.webp',
    'appIcon'      => 'referralbunny-app-icon.webp',
    'socialAvatar' => 'referralbunny-social-avatar.webp',
];

// Height-based sizes — width auto-adjusts to preserve aspect ratio
$sizes = [
    'xs'   => 'h-5',
    'sm'   => 'h-7',
    'md'   => 'h-10',
    'lg'   => 'h-16',
    'xl'   => 'h-24',
    'full' => 'w-full h-auto',
];

$file      = $variants[$variant] ?? $variants['horizontal'];
$sizing    = $sizes[$size]      ?? $sizes['md'];
$resolvedAlt = $decorative ? '' : ($alt ?? 'ReferralBunny.ai');
$loading   = $priority ? 'eager' : 'lazy';
@endphp

<img
    src="/images/logos/{{ $file }}"
    alt="{{ $resolvedAlt }}"
    @if($decorative) aria-hidden="true" @endif
    loading="{{ $loading }}"
    draggable="false"
    class="{{ $sizing }} object-contain shrink-0 select-none {{ $class }}"
/>
