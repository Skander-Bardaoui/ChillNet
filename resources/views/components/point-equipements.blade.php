@props(['point'])
{{-- Badges type + équipements d'un point de fraîcheur. Module 3. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap gap-1.5']) }}>
<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-label-sm text-label-sm font-semibold {{ $point->type->badgeClasses() }}"><span class="material-symbols-outlined text-[14px]">{{ $point->type->icone() }}</span>{{ $point->type->label() }}</span>
@foreach ($point->equipements() as [$libelle, $icone])
<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container-high text-on-surface font-label-sm text-label-sm"><span class="material-symbols-outlined text-[14px] text-primary">{{ $icone }}</span>{{ $libelle }}</span>
@endforeach
</div>
