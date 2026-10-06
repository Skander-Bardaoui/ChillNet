<x-app-layout :title="'Paramètres du profil'">
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="p-2.5 rounded-xl bg-primary/10 text-primary shrink-0">
                <span class="material-symbols-outlined text-[24px]">manage_accounts</span>
            </span>
            <div>
                <h1 class="font-headline-sm text-headline-sm text-on-surface font-semibold">Paramètres du profil</h1>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Gérez vos informations personnelles, votre mot de passe et votre compte.</p>
            </div>
        </div>
    </x-slot>

    <div class="flex flex-col gap-space-md">
        <div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
            @include('profile.partials.update-password-form')
        </div>

        <div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
