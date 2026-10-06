<x-back-layout :title="'Avis sur les points de fraîcheur'">
<div class="flex flex-wrap items-center gap-2">
<a href="{{ route('back.points.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Points de fraîcheur</a>
</div>

{{-- Répartition des sentiments (IA) --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
@foreach (\App\Enums\Sentiment::cases() as $s)
<a href="{{ route('back.avis.index', array_merge($filtres, ['sentiment' => $s->value])) }}" class="rounded-xl border border-outline-variant/20 p-4 flex items-center gap-3 hover:shadow {{ ($filtres['sentiment'] ?? null) === $s->value ? 'ring-2 ring-primary-container' : '' }} {{ $s->badgeClasses() }}">
<span class="material-symbols-outlined text-[28px]">{{ $s->icone() }}</span>
<div><p class="font-headline-sm text-headline-sm font-bold">{{ $repartition[$s->value] ?? 0 }}</p><p class="font-body-sm text-body-sm">avis {{ mb_strtolower($s->label()) }}s</p></div>
</a>
@endforeach
</div>

<form method="GET" action="{{ route('back.avis.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
<div class="flex flex-col gap-1 sm:col-span-2">
<label for="f-point" class="font-label-md text-label-md text-on-surface-variant">Point</label>
<select id="f-point" name="point" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface">
<option value="">Tous les points</option>
@foreach ($points as $p)
<option value="{{ $p->id }}" @selected((int) ($filtres['point'] ?? 0) === $p->id)>{{ $p->nom }}</option>
@endforeach
</select>
</div>
<div class="flex flex-col gap-1">
<label for="f-sentiment" class="font-label-md text-label-md text-on-surface-variant">Sentiment IA</label>
<select id="f-sentiment" name="sentiment" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface">
<option value="">Tous</option>
@foreach (\App\Enums\Sentiment::cases() as $s)
<option value="{{ $s->value }}" @selected(($filtres['sentiment'] ?? '') === $s->value)>{{ $s->label() }}</option>
@endforeach
</select>
</div>
<div class="flex gap-2">
<select name="note" aria-label="Note" class="flex-1 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface">
<option value="">Toutes notes</option>
@for ($n = 5; $n >= 1; $n--)<option value="{{ $n }}" @selected((int) ($filtres['note'] ?? 0) === $n)>{{ $n }} ★</option>@endfor
</select>
<button type="submit" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md" aria-label="Filtrer"><span class="material-symbols-outlined text-[18px] align-middle">filter_list</span></button>
</div>
</form>

<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 overflow-hidden">
<div class="overflow-x-auto">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20 bg-surface-container-lowest/60">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Point</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Auteur</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Note</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Commentaire</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Sentiment</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
@forelse ($avis as $un)
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60 last:border-0 align-top">
<td class="px-6 py-4"><a href="{{ route('back.points.show', $un->pointFraicheur) }}" class="font-title-sm text-title-sm text-primary hover:underline">{{ $un->pointFraicheur?->nom }}</a></td>
<td class="px-6 py-4 text-on-surface-variant whitespace-nowrap">{{ $un->user?->name ?? '—' }}<br /><span class="font-body-sm text-body-sm">{{ $un->created_at?->format('d/m/Y') }}</span></td>
<td class="px-6 py-4 whitespace-nowrap"><x-etoiles :note="$un->note" :taille="14" /></td>
<td class="px-6 py-4 font-body-sm text-body-sm text-on-surface max-w-md">{{ $un->commentaire ? \Illuminate\Support\Str::limit($un->commentaire, 160) : '—' }}</td>
<td class="px-6 py-4"><x-badge-sentiment :sentiment="$un->sentiment" :score="$un->score_sentiment" /></td>
<td class="px-6 py-4 text-right">
<form method="POST" action="{{ route('back.avis.destroy', $un) }}" onsubmit="return confirm('Supprimer cet avis (modération) ?');">@csrf @method('DELETE')<button type="submit" title="Supprimer" aria-label="Supprimer" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-error hover:bg-error-container/40"><span class="material-symbols-outlined text-[18px]">delete</span></button></form>
</td>
</tr>
@empty
<tr><td colspan="6" class="px-6 py-10 text-center text-on-surface-variant">Aucun avis ne correspond à ces filtres.</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
{{ $avis->links() }}
</x-back-layout>
