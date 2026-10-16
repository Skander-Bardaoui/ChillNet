{{--
    Formulaire d'avis (dépôt / modification). Variables : $avis (Avis|null).
    Règle : commentaire obligatoire si la note est ≤ 2 (StoreAvisRequest) ;
    l'indication « obligatoire » suit la note choisie côté client.
--}}
@php
    $seuil = \App\Http\Requests\StoreAvisRequest::NOTE_COMMENTAIRE_OBLIGATOIRE;
    $noteCourante = (int) old('note', $avis?->note ?? 0);
    $libelles = [1 => 'À éviter', 2 => 'Décevant', 3 => 'Correct', 4 => 'Bien', 5 => 'Excellent'];
@endphp
@csrf
<div x-data="{ note: {{ $noteCourante }}, survol: 0 }" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
<div class="flex flex-col gap-1 md:col-span-2">
<span id="label-note" class="font-label-md text-label-md text-on-surface font-medium">Votre note <span class="text-error">*</span></span>
<div class="flex items-center gap-3 flex-wrap" role="radiogroup" aria-labelledby="label-note">
<div class="flex items-center gap-1" @mouseleave="survol = 0">
@for ($i = 1; $i <= 5; $i++)
<label class="cursor-pointer" @mouseenter="survol = {{ $i }}" title="{{ $i }}/5 — {{ $libelles[$i] }}">
<input type="radio" name="note" value="{{ $i }}" x-model.number="note" class="sr-only" @checked($noteCourante === $i) />
<span class="material-symbols-outlined text-[32px] transition-colors" :class="(survol || note) >= {{ $i }} ? 'text-amber-500' : 'text-outline'" :style="(survol || note) >= {{ $i }} ? `font-variation-settings: 'FILL' 1` : ''">star</span>
<span class="sr-only">{{ $i }} sur 5</span>
</label>
@endfor
</div>
<span class="font-label-md text-label-md text-on-surface-variant" x-text="{{ json_encode($libelles) }}[survol || note] ?? 'Cliquez une étoile'"></span>
</div>
@error('note') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
<label for="affluence" class="font-label-md text-label-md text-on-surface font-medium">Affluence lors de votre passage <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnel)</span></label>
<select id="affluence" name="affluence" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('affluence') border-error @enderror">
<option value="">— Je ne sais pas —</option>
@foreach (\App\Enums\Affluence::cases() as $aff)
<option value="{{ $aff->value }}" @selected(old('affluence', $avis?->affluence?->value) === $aff->value)>{{ $aff->label() }}</option>
@endforeach
</select>
<p class="font-body-sm text-body-sm text-on-surface-variant">Aide l'IA à estimer l'affluence pour les prochains visiteurs.</p>
@error('affluence') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1 md:col-span-2">
<label for="commentaire" class="font-label-md text-label-md text-on-surface font-medium">Commentaire
<span x-show="note > 0 && note <= {{ $seuil }}" class="text-error">* obligatoire pour une note de 1 ou 2</span>
<span x-show="!(note > 0 && note <= {{ $seuil }})" class="font-body-sm text-body-sm text-on-surface-variant font-normal">(optionnel)</span>
</label>
<textarea id="commentaire" name="commentaire" rows="4" maxlength="1000" :required="note > 0 && note <= {{ $seuil }}" placeholder="Fraîcheur, propreté, accueil, attente…" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('commentaire') border-error @enderror">{{ old('commentaire', $avis?->commentaire) }}</textarea>
@error('commentaire') <p class="font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3">
<p class="font-body-sm text-body-sm text-on-surface-variant inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">auto_awesome</span>Le ton de votre avis est analysé automatiquement (IA) pour aider la modération.</p>
<button type="submit" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">send</span>{{ $avis ? 'Enregistrer les modifications' : 'Publier mon avis' }}</button>
</div>
</div>
