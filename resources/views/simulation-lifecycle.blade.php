<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Simulation Lifecycle</title>
        @vite(['resources/css/app.css'])
        @livewireStyles
    </head>
    <body class="bg-background text-foreground">
        <main class="mx-auto max-w-5xl px-6 py-8">
            <livewire:simulation-lifecycle-overview />
        </main>
        @livewireScripts
    </body>
</html>
