<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-semibold hover:opacity-95 transition']) }}>
    {{ $slot }}
</button>
