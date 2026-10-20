@props(['messages'])

@if ($messages)
    <div role="alert" {{ $attributes->merge(['class' => 'rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex flex-col gap-1']) }}>
        @foreach ((array) $messages as $message)
            <p class="flex items-start gap-1.5">
                <span class="material-symbols-outlined text-[18px] leading-none shrink-0" aria-hidden="true">error</span>
                <span>{{ $message }}</span>
            </p>
        @endforeach
    </div>
@endif
