@if ($errors->any())
<div role="alert" class="rounded-xl bg-error-container text-on-error-container px-space-md py-space-sm flex flex-col gap-2">
<div class="flex items-center gap-2 font-title-md text-title-md font-semibold">
<span class="material-symbols-outlined">report</span>
<span>{{ trans_choice(':count champ est invalide.|:count champs sont invalides.', $errors->count(), ['count' => $errors->count()]) }}</span>
</div>
<ul class="list-disc pl-8 flex flex-col gap-0.5 font-body-sm text-body-sm">
@foreach ($errors->all() as $message)
<li>{{ $message }}</li>
@endforeach
</ul>
</div>
@endif
