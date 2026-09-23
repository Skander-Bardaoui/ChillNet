@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-on-surface-variant/60 focus:border-primary-container focus:outline-none']) }}>
