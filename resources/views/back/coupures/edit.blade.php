<x-back-layout :title="'Modifier la coupure'">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-sm items-start">
<div class="lg:col-span-2 rounded-2xl bg-surface-container-low/80 shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<form method="POST" action="{{ route('back.coupures.update', $coupure->id) }}" class="flex flex-col gap-space-sm">
@method('PUT')
@include('back.coupures._form')
</form>
</div>
<aside class="flex flex-col gap-3 lg:sticky lg:top-6">
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">info</span>État actuel</h2>
<div class="mt-2 flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
<p><strong class="text-on-surface">Zone :</strong>
@if ($coupure->quartier)
{{ $coupure->quartier->nom }} ({{ $coupure->quartier->ville }})
@elseif ($coupure->hasCoordinates())
Point sur la carte
@else
—
@endif
@if ($coupure->lieu) · {{ $coupure->lieu }} @endif
</p>
<p><strong class="text-on-surface">Statut :</strong> {{ $coupure->statut?->label() }}</p>
<p class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">groups</span><strong class="text-on-surface">{{ $coupure->confirmations }}</strong>&nbsp;confirmation(s) d'habitants</p>
</div>
</div>
<div class="rounded-2xl bg-green-50 border border-green-200 p-space-md">
<h2 class="font-title-md text-title-md text-green-900 font-semibold flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span>Clôturer ?</h2>
<p class="mt-1 font-body-sm text-body-sm text-green-800">Passez le statut à <strong>Résolue</strong> : la coupure sort de la carte publique et rejoint l'historique.</p>
</div>
</aside>
</div>
</x-back-layout>
