<x-back-layout :title="'Nouveau point de fraîcheur'">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-sm items-start">
<div class="lg:col-span-2 rounded-2xl bg-surface-container-low/80 shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<form method="POST" action="{{ route('back.points.store') }}" enctype="multipart/form-data" novalidate>
@include('points-fraicheur._form', ['retour' => route('back.points.index')])
</form>
</div>
@include('back.points._aide')
</div>
</x-back-layout>
