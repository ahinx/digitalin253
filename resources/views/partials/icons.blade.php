@props(['name','class'=>'h-6 w-6'])
@if($name === 'search')
<svg {{ $attributes->merge(['class'=>$class]) }} xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
    stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
        d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z" />
</svg>
@endif