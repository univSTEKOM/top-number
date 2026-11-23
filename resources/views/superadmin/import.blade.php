<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                Import Kontak ke Tim
            </h2>
            <p class="mt-1 text-sm text-slate-600">Upload data nomor dan arahkan ke tim tertentu dengan validasi duplikat dan format yang sama seperti import asisten marketing.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="page-wrap space-y-6">
            @if (session('success'))
                <div class="status-success fade-in-up">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="status-error fade-in-up">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="panel fade-in-up">
                <h3 class="section-title">Upload File Import</h3>
                <p class="section-subtitle">
                    Pilih tim tujuan dan upload file CSV/TXT/XLSX/XLS. Pastikan header memiliki kolom <strong>phone</strong> atau alias <strong>nomor</strong>, serta nama opsional.
                </p>

                <form class="mt-4 grid gap-4" method="POST" action="{{ route('superadmin.import.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div>
                        <x-input-label for="team_id" value="Tim Tujuan" />
                        <select id="team_id" name="team_id" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option value="">-- Pilih Tim --</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" {{ old('team_id') == (string) $team->id ? 'selected' : '' }}>
                                    {{ $team->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label for="file" value="Pilih File" />
                        <input id="file" name="file" type="file" required accept=".csv,.txt,.xlsx,.xls"
                            class="mt-1 block w-full file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                        <p class="mt-2 text-xs text-gray-500">Format didukung: .csv, .txt, .xlsx, .xls (maks 20MB).</p>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="btn-main">Import File</button>
                    </div>
                </form>
            </div>

            @if (session('import_summary'))
                @php $summary = session('import_summary'); @endphp
                <div class="panel fade-in-up">
                    <h3 class="section-title">Ringkasan Import</h3>
                    <div class="mt-4 grid gap-3 text-sm text-slate-700 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Berhasil</p>
                            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ $summary['created'] ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Duplikat</p>
                            <p class="mt-2 text-2xl font-bold text-amber-600">{{ $summary['skipped_duplicate'] ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Tidak Valid</p>
                            <p class="mt-2 text-2xl font-bold text-rose-600">{{ $summary['skipped_invalid'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
