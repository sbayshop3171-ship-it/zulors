@props([
    'link' => ''
])

<a href="{{ $link }}" style="color: blue; font-weight: bold; text-decoration: underline;" target="_blank">{{ $link }}</a>