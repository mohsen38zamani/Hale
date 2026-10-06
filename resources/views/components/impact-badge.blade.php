@props(['control'])

@if($level = config('creative.impacts.'.$control))
    <span class="impact-badge impact-{{ $level }}" data-impact-control="{{ $control }}" title="{{ config('creative.impact_levels.'.$level.'.hint') }}">{{ config('creative.impact_levels.'.$level.'.short') }}</span>
@endif
