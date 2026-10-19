@if (session('success'))
    <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
        <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
    </p>
@endif
@if ($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="mb-1 font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i> Không thể thực hiện:</p>
        <ul class="list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
