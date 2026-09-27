<x-app-layout>
<x-slot name="header">
<h1 class="font-headline-sm text-headline-sm text-on-surface">Signalements communautaires</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant">Signalez un problème dans votre résidence et suivez son traitement.</p>
</x-slot>

@if (session('success'))
<div class="rounded-lg bg-green-100 px-4 py-3 text-green-900">{{ session('success') }}</div>
@endif
@if ($errors->any())
<div class="rounded-lg bg-red-100 px-4 py-3 text-red-900">{{ $errors->first() }}</div>
@endif
<section class="rounded-xl bg-surface-container-low p-space-md shadow-sm">
<button type="button" id="notifications-toggle" aria-expanded="false" aria-controls="notifications-panel" class="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2 font-semibold text-on-primary-container"><span class="material-symbols-outlined">notifications</span>Notifications @if ($unreadNotificationsCount > 0)<span class="rounded-full bg-red-600 px-2 py-0.5 text-xs text-white">{{ $unreadNotificationsCount }}</span>@endif</button>
<div id="notifications-panel" class="mt-4 hidden" aria-hidden="true">
@forelse ($notifications as $notification)
<div class="border-b border-surface-variant py-2 last:border-0"><p class="font-label-md font-semibold">{{ $notification->data['title'] }}</p><p class="font-body-sm text-on-surface-variant">{{ $notification->data['message'] }}</p><time class="text-xs text-on-surface-variant">{{ $notification->created_at->format('d/m/Y H:i') }}</time></div>
@empty
<p class="text-on-surface-variant">Aucune notification.</p>
@endforelse
</div>
</section>
<script>
const notificationsToggle = document.getElementById('notifications-toggle');
const notificationsPanel = document.getElementById('notifications-panel');
notificationsToggle.addEventListener('click', () => {
	const expanded = notificationsToggle.getAttribute('aria-expanded') === 'true';
	notificationsToggle.setAttribute('aria-expanded', String(!expanded));
	notificationsPanel.setAttribute('aria-hidden', String(expanded));
	notificationsPanel.classList.toggle('hidden', expanded);
});
</script>

<section class="rounded-xl bg-surface-container-low p-space-md shadow-md">
<h2 class="font-title-md text-title-md text-on-surface mb-space-sm">Nouveau signalement</h2>
<form action="{{ route('front.signalements.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
@csrf
<div class="flex flex-col gap-1"><label for="categorie" class="font-label-md text-label-md text-on-surface">Catégorie</label><select id="categorie" name="categorie" required class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2"><option value="fuite">Fuite</option><option value="panne_locale">Panne locale</option><option value="personne_vulnerable">Personne vulnérable isolée</option><option value="autre">Autre</option></select></div>
<div id="categorie-autre-wrapper" class="hidden flex-col gap-1"><label for="categorie_autre" class="font-label-md text-label-md text-on-surface">Précisez le problème</label><input id="categorie_autre" name="categorie_autre" type="text" maxlength="100" placeholder="Écrivez la catégorie du problème" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2"></div>
<div class="flex flex-col gap-1"><label for="urgence" class="font-label-md text-label-md text-on-surface">Niveau d’urgence</label><select id="urgence" name="urgence" required class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2"><option value="normale">Normale</option><option value="prioritaire">Prioritaire</option><option value="vitale">Vitale</option></select></div>
<div class="md:col-span-2 flex flex-col gap-1"><label for="description" class="font-label-md text-label-md text-on-surface">Description (20 caractères minimum)</label><textarea id="description" name="description" rows="4" minlength="20" maxlength="5000" required class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2">{{ old('description') }}</textarea></div>
<div class="md:col-span-2 flex flex-col gap-1"><label for="photo" class="font-label-md text-label-md text-on-surface">Photo (facultative, 5 Mo maximum)</label><input id="photo" name="photo" type="file" accept="image/*" class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2"></div>
<button type="submit" class="md:col-span-2 justify-self-start px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md font-semibold">Envoyer le signalement</button>
</form>
</section>

<script>
const categorie = document.getElementById('categorie');
const categorieAutreWrapper = document.getElementById('categorie-autre-wrapper');
const categorieAutre = document.getElementById('categorie_autre');
function toggleCategorieAutre() {
	const isOther = categorie.value === 'autre';
	categorieAutreWrapper.classList.toggle('hidden', !isOther);
	categorieAutreWrapper.classList.toggle('flex', isOther);
	categorieAutre.required = isOther;
}
categorie.addEventListener('change', toggleCategorieAutre);
toggleCategorieAutre();
</script>

<section class="rounded-xl bg-surface-container-low p-space-md shadow-sm overflow-x-auto">
<h2 class="font-title-md text-title-md text-on-surface mb-space-sm">Mes signalements</h2>
<p class="mb-space-sm text-on-surface-variant">Résidence associée : <strong class="text-on-surface">{{ $residence?->nom ?? 'Aucune résidence associée' }}</strong></p>
<table class="w-full text-left min-w-[820px]"><thead><tr class="border-b border-surface-variant font-label-sm uppercase text-on-surface-variant"><th class="py-2 pr-4">Catégorie</th><th class="py-2 pr-4">Urgence</th><th class="py-2 pr-4">Description</th><th class="py-2 pr-4">Statut</th><th class="py-2 pr-4">Date</th><th class="py-2">Actions</th></tr></thead><tbody class="font-body-sm text-body-sm text-on-surface">
@forelse ($signalements as $signalement)
<tr class="border-b border-surface-variant"><td class="py-3 pr-4">{{ $signalement->categorie === 'autre' ? $signalement->categorie_autre : str_replace('_', ' ', ucfirst($signalement->categorie)) }}</td><td class="py-3 pr-4"><span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $signalement->urgence === 'vitale' ? 'bg-red-100 text-red-800' : ($signalement->urgence === 'prioritaire' ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800') }}">{{ ucfirst($signalement->urgence) }}</span></td><td class="py-3 pr-4">{{ \Illuminate\Support\Str::limit($signalement->description, 80) }}</td><td class="py-3 pr-4"><span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $signalement->statut === 'nouveau' ? 'bg-blue-100 text-blue-800' : ($signalement->statut === 'en_traitement' ? 'bg-orange-100 text-orange-800' : 'bg-green-100 text-green-800') }}">{{ str_replace('_', ' ', ucfirst($signalement->statut)) }}</span></td><td class="py-3 pr-4">{{ $signalement->date_signalement->format('d/m/Y') }}</td><td class="py-3"><div class="flex gap-2"><a href="{{ route('front.signalements.pdf', $signalement) }}" class="text-primary font-semibold hover:underline">PDF</a><a href="{{ route('front.signalements.edit', $signalement) }}" class="text-primary font-semibold hover:underline">Modifier</a><form method="POST" action="{{ route('front.signalements.destroy', $signalement) }}" onsubmit="return confirm('Supprimer ce signalement ?');">@csrf @method('DELETE')<button type="submit" class="text-red-700 font-semibold hover:underline">Supprimer</button></form></div></td></tr>
@empty
<tr><td colspan="6" class="py-4 text-on-surface-variant">Aucun signalement envoyé.</td></tr>
@endforelse
</tbody></table>
</section>
</x-app-layout>
