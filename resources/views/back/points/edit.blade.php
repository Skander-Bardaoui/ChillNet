<x-back-layout :title="'Modifier « '.$point->nom.' »'">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-sm items-start">
<div class="lg:col-span-2 rounded-2xl bg-surface-container-low/80 shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<form method="POST" action="{{ route('back.points.update', $point) }}" enctype="multipart/form-data" novalidate>
@method('PUT')
@include('points-fraicheur._form', ['retour' => route('back.points.show', $point)])
</form>
</div>
@include('back.points._aide')
</div>
</x-back-layout>
