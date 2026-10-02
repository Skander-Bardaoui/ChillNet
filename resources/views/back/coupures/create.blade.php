<x-back-layout :title="'Nouvelle coupure'">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-sm items-start">
<div class="lg:col-span-2 rounded-2xl bg-surface-container-low/80 shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<form method="POST" action="{{ route('back.coupures.store') }}" class="flex flex-col gap-space-sm">
@include('back.coupures._form')
</form>
</div>
<aside class="flex flex-col gap-3 lg:sticky lg:top-6">
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">lightbulb</span>Publier une prévue</h2>
<ul class="mt-2 flex flex-col gap-2 font-body-sm text-body-sm text-on-surface-variant">
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span><span class="flex-1 min-w-0">Statut « Prévue » avec le type « Maintenance » ou « Délestage » pour une intervention planifiée.</span></li>
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span><span class="flex-1 min-w-0">Début et fin renseignés : les habitants s'organisent.</span></li>
<li class="flex gap-2"><span class="material-symbols-outlined text-green-700 text-[18px] shrink-0">check</span><span class="flex-1 min-w-0">Le message saisi s'affiche tel quel sur la carte publique.</span></li>
</ul>
</div>
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">verified</span>Anti-doublon</h2>
<p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Le formulaire refuse deux coupures non résolues qui se chevauchent sur la même zone et le même créneau. Les <strong>résolues</strong> restent en historique.</p>
</div>
</aside>
</div>
</x-back-layout>
