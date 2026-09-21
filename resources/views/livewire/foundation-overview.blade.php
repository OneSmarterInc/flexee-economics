<section>
    <header>
        <p class="text-sm text-muted-foreground">{{ auth()->user()->tenant->name }}</p>
        <h1 class="text-2xl font-semibold">Foundation Overview</h1>
    </header>

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">Courses</p>
            <p class="text-3xl font-semibold">{{ $courses->count() }}</p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">Authorized sections</p>
            <p class="text-3xl font-semibold">{{ $sections->count() }}</p>
        </div>
        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">Tenant teams</p>
            <p class="text-3xl font-semibold">{{ $teamCount }}</p>
        </div>
    </div>

    <div class="mt-8 space-y-4">
        @foreach ($sections as $section)
            <article class="rounded-lg border p-4">
                <h2 class="font-medium">{{ $section->course->code }} - {{ $section->name }}</h2>
                <p class="text-sm text-muted-foreground">{{ $section->teams->count() }} teams</p>
            </article>
        @endforeach
    </div>
</section>
