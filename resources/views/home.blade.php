@extends('layouts.app')

@section('title', config('app.name', 'VEX'))

@php
    $heading = "Shaping tomorrow\nwith vision and action.";
@endphp

@section('content')
    <div class="relative h-screen w-full overflow-hidden bg-black text-white">
        {{-- Background video — raw, no overlay --}}
        <video
            class="absolute inset-0 h-full w-full object-cover"
            src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260403_050628_c4e32401-fab4-4a27-b7a8-6e9291cd5959.mp4"
            autoplay
            loop
            muted
            playsinline
        ></video>

        {{-- Foreground --}}
        <div class="relative z-10 flex h-full flex-col">
            {{-- Navbar --}}
            <header class="px-6 pt-6 md:px-12 lg:px-16">
                <nav class="liquid-glass flex items-center justify-between rounded-xl px-4 py-2">
                    <div class="text-2xl font-semibold tracking-tight">
                        VEX
                    </div>

                    <div class="hidden items-center gap-8 text-sm md:flex">
                        <a href="#" class="transition-colors hover:text-gray-300">Story</a>
                        <a href="#" class="transition-colors hover:text-gray-300">Investing</a>
                        <a href="#" class="transition-colors hover:text-gray-300">Building</a>
                        <a href="#" class="transition-colors hover:text-gray-300">Advisory</a>
                    </div>

                    <a href="#" class="rounded-lg bg-white px-6 py-2 text-sm font-medium text-black transition-colors hover:bg-gray-100">
                        Connect
                    </a>
                </nav>
            </header>

            {{-- Hero content pinned to the bottom of the viewport --}}
            <main class="flex flex-1 flex-col justify-end px-6 pb-12 md:px-12 lg:px-16 lg:pb-16">
                <div class="lg:grid lg:grid-cols-2 lg:items-end">
                    {{-- Left column --}}
                    <div>
                        <x-animated-heading
                            :text="$heading"
                            class="mb-4 text-4xl font-normal md:text-5xl lg:text-6xl xl:text-7xl"
                            style="letter-spacing: -0.04em"
                        />

                        <x-fade-in :delay="800" :duration="1000">
                            <p class="mb-5 text-base text-gray-300 md:text-lg">
                                We back visionaries and craft ventures that define what comes next.
                            </p>
                        </x-fade-in>

                        <x-fade-in :delay="1200" :duration="1000">
                            <div class="flex flex-wrap gap-4">
                                <a href="#" class="rounded-lg bg-white px-8 py-3 font-medium text-black transition-colors hover:bg-gray-100">
                                    Connect
                                </a>
                                <a href="#" class="liquid-glass rounded-lg border border-white/20 px-8 py-3 font-medium text-white transition-colors hover:bg-white hover:text-black">
                                    Explore Now
                                </a>
                            </div>
                        </x-fade-in>
                    </div>

                    {{-- Right column — bottom-right tag --}}
                    <x-fade-in :delay="1400" :duration="1000" class="mt-10 flex items-end justify-start lg:mt-0 lg:justify-end">
                        <div class="liquid-glass rounded-xl border border-white/20 px-6 py-3">
                            <p class="text-lg font-light md:text-xl lg:text-2xl">
                                Investing. Building. Advisory.
                            </p>
                        </div>
                    </x-fade-in>
                </div>
            </main>
        </div>
    </div>
@endsection
