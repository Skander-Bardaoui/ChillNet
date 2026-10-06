<x-back-layout :title="'Paramètres du profil'">

@php($user = auth()->user())

<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
    <div class="flex items-start gap-3 flex-1">
        <div class="p-3 rounded-xl bg-primary/10 text-primary shrink-0">
            <span class="material-symbols-outlined text-[28px]">manage_accounts</span>
        </div>
        <div>
            <p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Mon compte</p>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Gérez vos informations personnelles, votre mot de passe et votre compte gestionnaire.</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-space-md">
    <div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
        @include('profile.partials.update-profile-information-form', ['user' => $user])
    </div>

    <div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
        @include('profile.partials.update-password-form')
    </div>

    <div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
        @include('profile.partials.delete-user-form')
    </div>
</div>

</x-back-layout>
