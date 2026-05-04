@props([
    'variant'    => 'waving',
    'size'       => 'md',
    'decorative' => true,
    'class'      => '',
])

@php
$variants = [
    'hero'        => 'r-bunny-hero-flying.webp',
    'waving'      => 'r-bunny-waving.webp',
    'celebration' => 'r-bunny-celebration.webp',
    'analyst'     => 'r-bunny-analyst.webp',
    'helper'      => 'r-bunny-helper-question.webp',
    'thinking'    => 'r-bunny-thinking.webp',
    'tech'        => 'r-bunny-tech-hologram.webp',
    'warning'     => 'r-bunny-warning-error.webp',
    'sleeping'    => 'r-bunny-sleeping.webp',
    'thumbsup'    => 'r-bunny-thumbs-up.webp',
    'rocket'      => 'r-bunny-rocket.webp',
    'portal'      => 'r-bunny-portal.webp',
];

$altTexts = [
    'hero'        => 'R Bunny flying confidently',
    'waving'      => 'R Bunny waving hello',
    'celebration' => 'R Bunny celebrating success',
    'analyst'     => 'R Bunny analyzing data',
    'helper'      => 'R Bunny ready to help',
    'thinking'    => 'R Bunny thinking carefully',
    'tech'        => 'R Bunny with hologram display',
    'warning'     => 'R Bunny showing a warning',
    'sleeping'    => 'R Bunny sleeping',
    'thumbsup'    => 'R Bunny giving a thumbs up',
    'rocket'      => 'R Rocket flying with fist raised',
    'portal'      => 'R Portal — R Bunny with holographic displays',
];

$sizes = [
    'xs' => 'w-8 h-8',
    'sm' => 'w-16 h-16',
    'md' => 'w-[120px] h-[120px]',
    'lg' => 'w-[220px] h-[220px]',
    'xl' => 'w-[320px] h-[320px]',
];

$file   = $variants[$variant] ?? $variants['waving'];
$alt    = $decorative ? '' : ($altTexts[$variant] ?? '');
$sizing = $sizes[$size] ?? $sizes['md'];
@endphp

<img
    src="/images/mascots/{{ $file }}"
    alt="{{ $alt }}"
    @if($decorative) aria-hidden="true" @endif
    loading="lazy"
    draggable="false"
    class="{{ $sizing }} object-contain shrink-0 select-none {{ $class }}"
/>
