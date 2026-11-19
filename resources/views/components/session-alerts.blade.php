@if (session('status'))
    <div role="status" class="mb-4 rounded-lg border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
        {{ session('status') }}
    </div>
@endif

@if (session('warning') || session('duplicate_warning'))
    <div role="alert" class="mb-4 rounded-lg border border-warning/20 bg-warning/5 px-4 py-3 text-sm text-warning">
        {{ session('warning') ?? session('duplicate_warning') }}
    </div>
@endif

@if (session('error'))
    <div role="alert" class="mb-4 rounded-lg border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
        {{ session('error') }}
    </div>
@endif
