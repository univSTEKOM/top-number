<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                        {{ ($isTopmatch ?? false) ? 'Input Kontak Asisten Marketing (Topmatch)' : 'Input Nomor Asisten Marketing' }}
                    </h2>
                    @if ($isTopmatch ?? false)
                        <span class="inline-flex items-center rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                            Topmatch
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                            Toploker
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($isTopmatch ?? false)
                        Input nomor telepon dan nama perusahaan untuk channel Topmatch. Kedua field wajib diisi.
                    @else
                        Input nama perusahaan, nomor telepon, dan kota. Ketiga field wajib diisi, dengan validasi duplikat otomatis.
                    @endif
                </p>
            </div>
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

            @if ($isTopmatch ?? false)
                {{-- FORM KHUSUS TOPMATCH: NAMA PERUSAHAAN + NOMOR HP WAJIB --}}
                <div class="panel fade-in-up" x-data="{ mode: 'single' }">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="section-title">Tambah Kontak Topmatch</h3>
                            <p class="section-subtitle">Nama Perusahaan dan Nomor Telepon wajib diisi.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="mode = 'single'"
                                    :class="mode === 'single' ? 'btn-main' : 'btn-subtle'" class="text-xs">
                                Form Satuan
                            </button>
                            <button type="button" @click="mode = 'batch'"
                                    :class="mode === 'batch' ? 'btn-main' : 'btn-subtle'" class="text-xs">
                                Form Massal (Teks)
                            </button>
                        </div>
                    </div>

                    {{-- Form Satuan --}}
                    <form x-show="mode === 'single'" class="mt-4 grid gap-4 md:grid-cols-2" method="POST" action="{{ route('subleader.contacts.store') }}">
                        @csrf
                        <div>
                            <x-input-label for="company_name" value="Nama Perusahaan *" />
                            <x-text-input id="company_name" name="company_name" class="mt-1 block w-full"
                                placeholder="Contoh: PT Sumber Makmur" :value="old('company_name')" required />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Nomor Telepon / WhatsApp *" />
                            <x-text-input id="phone" name="phone" class="mt-1 block w-full"
                                placeholder="Contoh: 081234567890 atau 6281234567890" :value="old('phone')" required />
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <x-primary-button>Simpan Kontak</x-primary-button>
                        </div>
                    </form>

                    {{-- Form Massal (Teks baris) --}}
                    <form x-show="mode === 'batch'" x-cloak class="mt-4 space-y-3" method="POST" action="{{ route('subleader.contacts.store') }}">
                        @csrf
                        <div>
                            <x-input-label for="entries" value="Data Perusahaan & Nomor (Satu baris per perusahaan)" />
                            <p class="text-xs text-slate-500 mb-2">
                                Format: <code>Nama Perusahaan, Nomor HP</code> (pisahkan dengan koma atau tanda titik koma <code>;</code>)
                            </p>
                            <textarea id="entries" name="entries" rows="6" required
                                class="mt-1 block w-full text-sm font-mono"
                                placeholder="PT Maju Bersama, 081234567890&#10;CV Abadi Sentosa, 085678901234&#10;PT Digital Kreatif, 628987654321">{{ old('entries') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Simpan Semua Kontak</x-primary-button>
                        </div>
                    </form>
                </div>

                {{-- Import Massal Topmatch --}}
                <div class="panel fade-in-up">
                    <h3 class="section-title">Import Massal Excel / CSV (Topmatch)</h3>
                    <p class="section-subtitle">
                        Upload file dengan kolom wajib: <strong>nama_perusahaan</strong> (atau <code>nama</code>/<code>name</code>) dan <strong>phone</strong> (atau <code>nomor</code>/<code>no_hp</code>).
                    </p>
                    <p class="mt-1 text-xs text-amber-700">
                        * Baris yang tidak memiliki Nama Perusahaan akan otomatis ditolak/dilewati.
                    </p>

                    <form class="mt-4 flex flex-wrap items-end gap-3" method="POST"
                        action="{{ route('subleader.contacts.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="w-full max-w-sm">
                            <x-input-label for="file_tm" value="Pilih File (.xlsx, .xls, .csv)" />
                            <input id="file_tm" name="file" type="file" required accept=".csv,.txt,.xlsx,.xls"
                                class="mt-1 block w-full file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                        </div>
                        <button type="submit" class="btn-main">Import File Topmatch</button>
                    </form>
                </div>

            @else
                {{-- FORM TOPLOKER: NAMA PERUSAHAAN + NOMOR HP + KOTA WAJIB --}}
                <div class="panel fade-in-up" x-data="{ mode: '{{ old('entries') ? 'batch' : 'single' }}' }">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="section-title">Tambah Kontak</h3>
                            <p class="section-subtitle">Nama Perusahaan, Nomor Telepon, dan Kota wajib diisi.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="mode = 'single'"
                                    :class="mode === 'single' ? 'btn-main' : 'btn-subtle'" class="text-xs">
                                Form Satuan
                            </button>
                            <button type="button" @click="mode = 'batch'"
                                    :class="mode === 'batch' ? 'btn-main' : 'btn-subtle'" class="text-xs">
                                Form Massal (Teks)
                            </button>
                        </div>
                    </div>

                    {{-- Form Satuan --}}
                    <form x-show="mode === 'single'" class="mt-4 grid gap-4 md:grid-cols-3" method="POST" action="{{ route('subleader.contacts.store') }}">
                        @csrf
                        <div>
                            <x-input-label for="company_name" value="Nama Perusahaan *" />
                            <x-text-input id="company_name" name="company_name" class="mt-1 block w-full"
                                placeholder="Contoh: PT Sumber Makmur" :value="old('company_name')" required />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Nomor Telepon / WhatsApp *" />
                            <x-text-input id="phone" name="phone" class="mt-1 block w-full"
                                placeholder="Contoh: 081234567890" :value="old('phone')" required />
                        </div>
                        <div>
                            <x-input-label for="city" value="Kota/Wilayah *" />
                            <x-text-input id="city" name="city" class="mt-1 block w-full"
                                placeholder="Contoh: Bekasi, Solo, Semarang" :value="old('city')" required />
                            <p class="mt-1 text-xs text-slate-500">Isi nama kota, bukan provinsi (bukan Jawa Tengah / Jawa Barat).</p>
                        </div>
                        <div class="md:col-span-3 flex justify-end">
                            <x-primary-button>Simpan Kontak</x-primary-button>
                        </div>
                    </form>

                    {{-- Form Massal (Teks baris) --}}
                    <form x-show="mode === 'batch'" x-cloak class="mt-4 space-y-3" method="POST" action="{{ route('subleader.contacts.store') }}">
                        @csrf
                        <div>
                            <x-input-label for="entries" value="Data Perusahaan, Nomor & Kota (Satu baris per perusahaan)" />
                            <p class="text-xs text-slate-500 mb-2">
                                Format: <code>Nama Perusahaan, Nomor HP, Kota</code> (pisahkan dengan koma atau titik koma <code>;</code>). Kota bukan nama provinsi.
                            </p>
                            <textarea id="entries" name="entries" rows="6" required
                                class="mt-1 block w-full text-sm font-mono"
                                placeholder="PT Maju Bersama, 081234567890, Bekasi&#10;CV Abadi Sentosa, 085678901234, Solo&#10;PT Digital Kreatif, 628987654321, Semarang">{{ old('entries') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button>Simpan Semua Kontak</x-primary-button>
                        </div>
                    </form>
                </div>

                <div class="panel fade-in-up">
                    <h3 class="section-title">Import Massal (Excel/CSV)</h3>
                    <p class="section-subtitle">
                        Upload file dengan kolom wajib: <strong>nama_perusahaan</strong> (atau <code>nama</code>/<code>name</code>),
                        <strong>phone</strong> (atau <code>nomor</code>/<code>no_hp</code>), dan <strong>kota</strong> (atau <code>city</code>/<code>wilayah</code>).
                    </p>
                    <p class="mt-2 text-xs text-gray-500">
                        Format didukung: .csv, .xlsx, .xls (maks 20MB). Nomor duplikat dan baris tanpa nama perusahaan/kota (atau berisi nama provinsi) otomatis dilewati.
                    </p>

                    <form class="mt-4 flex flex-wrap items-end gap-3" method="POST"
                        action="{{ route('subleader.contacts.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="w-full max-w-sm">
                            <x-input-label for="file" value="Pilih File" />
                            <input id="file" name="file" type="file" required accept=".csv,.txt,.xlsx,.xls"
                                class="mt-1 block w-full file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200">
                        </div>
                        <button type="submit" class="btn-main">Import File</button>
                    </form>
                </div>
            @endif

            {{-- TABEL NOMOR YANG SUDAH DIINPUT --}}
            <div class="panel fade-in-up">
                <h3 class="section-title">
                    {{ ($isTopmatch ?? false) ? 'Kontak Perusahaan Yang Sudah Diinput Bulan Ini' : 'Nomor Yang Sudah Diinput' }}
                </h3>
                <div class="table-wrap mt-4">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Nama Perusahaan</th>
                                <th>Nomor</th>
                                @unless ($isTopmatch ?? false)
                                    <th>Kota</th>
                                @endunless
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($contacts as $contact)
                                <tr>
                                    <td>{{ $contact->contact_name ?? '-' }}</td>
                                    <td class="font-medium">{{ $contact->phone }}</td>
                                    @unless ($isTopmatch ?? false)
                                        <td>{{ $contact->city ?? '-' }}</td>
                                    @endunless
                                    <td>{{ $contact->created_at->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($isTopmatch ?? false) ? 3 : 4 }}" class="px-4 py-4 text-slate-500">Belum ada data nomor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $contacts->links() }}
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
