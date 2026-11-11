<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                    Manajemen User
                </h2>
                <p class="mt-1 text-sm text-slate-600">Kelola tim, Marketing Utama (Toploker, Topmatch, Kerja Malam), dan Asisten Marketing.</p>
            </div>
            <a href="{{ route('superadmin.contacts.index') }}" class="btn-main">
                Lihat Data Per Marketing Utama
            </a>
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

            {{-- ================================================================
                 TAMBAH MARKETING UTAMA  (semua channel)
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[80]">
                <h3 class="section-title">Tambah Marketing Utama</h3>
                <p class="section-subtitle">Pilih channel marketing saat membuat akun Marketing Utama baru.</p>
                <form class="mt-4 space-y-4" method="POST" action="{{ route('superadmin.leaders.store') }}"
                      x-data="{ channel: 'toploker' }">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="leader_name" value="Nama" />
                            <x-text-input id="leader_name" name="name" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="leader_email" value="Email" />
                            <x-text-input id="leader_email" type="email" name="email" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="leader_password" value="Password" />
                            <x-text-input id="leader_password" type="password" name="password" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="leader_marketing_channel" value="Channel Marketing" />
                            <select id="leader_marketing_channel" name="marketing_channel" class="mt-1 block w-full" x-model="channel" required>
                                <option value="toploker">Toploker</option>
                                <option value="topmatch">Topmatch</option>
                                <option value="kerja_malam">Kerja Malam</option>
                            </select>
                        </div>
                        <div x-show="channel === 'toploker'">
                            <x-input-label for="leader_team_id" value="Pilih Tim (Toploker)" />
                            <select id="leader_team_id" name="team_id" class="mt-1 block w-full"
                                    :required="channel === 'toploker'">
                                <option value="">-- Pilih Tim --</option>
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <x-primary-button>Simpan Marketing Utama</x-primary-button>
                </form>
            </div>

            {{-- ================================================================
                 TAMBAH ASISTEN MARKETING  (Toploker & Topmatch)
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[70]">
                <h3 class="section-title">Tambah Asisten Marketing</h3>
                <p class="section-subtitle">Pilih channel marketing saat membuat akun Asisten Marketing baru.</p>
                <form class="mt-4 space-y-4" method="POST" action="{{ route('superadmin.sub-leaders.store') }}"
                      x-data="{ channel: 'toploker' }">
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="sub_name" value="Nama" />
                            <x-text-input id="sub_name" name="name" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="sub_email" value="Email" />
                            <x-text-input id="sub_email" type="email" name="email" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="sub_password" value="Password" />
                            <x-text-input id="sub_password" type="password" name="password" class="mt-1 block w-full" required />
                        </div>
                        <div>
                            <x-input-label for="sub_marketing_channel" value="Channel Marketing" />
                            <select id="sub_marketing_channel" name="marketing_channel" class="mt-1 block w-full" x-model="channel" required>
                                <option value="toploker">Toploker</option>
                                <option value="topmatch">Topmatch</option>
                            </select>
                        </div>
                        <div x-show="channel === 'toploker'">
                            <x-input-label for="sub_team_id" value="Pilih Tim (Toploker)" />
                            <div x-data="{
                                open: false,
                                options: [
                                    @foreach($teams as $team)
                                        { value: '{{ (string)$team->id }}', label: '{{ addslashes($team->name) }}' },
                                    @endforeach
                                ],
                                selected: [],
                                get selectedOptions() {
                                    return this.options.filter(o => this.selected.includes(o.value));
                                },
                                toggleOption(val) {
                                    if (this.selected.includes(val)) {
                                        this.selected = this.selected.filter(i => i !== val);
                                    } else {
                                        this.selected.push(val);
                                    }
                                }
                            }" class="relative mt-1 w-full" :class="open ? 'z-50' : ''" @click.away="open = false">
                                
                                <div class="flex flex-wrap items-center gap-1 min-h-[42px] px-3 py-1.5 border border-slate-300 rounded-md bg-white cursor-pointer w-full text-sm shadow-sm focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500" @click="open = !open">
                                    <template x-for="option in selectedOptions" :key="option.value">
                                        <span class="inline-flex items-center gap-1 px-2 py-1 bg-slate-100 text-slate-700 border border-slate-200 rounded text-xs font-medium">
                                            <span x-text="option.label"></span>
                                            <button type="button" class="text-slate-400 hover:text-slate-600 focus:outline-none" @click.stop="toggleOption(option.value)">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </span>
                                    </template>
                                    
                                    <div x-show="selected.length === 0" class="text-slate-400 py-0.5">-- Pilih Tim --</div>
                                    
                                    <div class="ml-auto text-slate-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </div>
                                </div>
                                
                                <select name="team_ids[]" multiple :required="channel === 'toploker'" class="absolute opacity-0 h-0 w-0 pointer-events-none" tabindex="-1">
                                    <template x-for="option in options" :key="option.value">
                                        <option :value="option.value" :selected="selected.includes(option.value)"></option>
                                    </template>
                                </select>
                                
                                <div x-show="open" 
                                     x-transition
                                     class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-md shadow-lg max-h-60 overflow-y-auto" style="display: none;">
                                    <template x-for="option in options" :key="option.value">
                                        <div 
                                            class="px-3 py-2 cursor-pointer hover:bg-slate-50 flex items-center justify-between text-sm"
                                            :class="selected.includes(option.value) ? 'bg-slate-50 text-slate-900 font-medium' : 'text-slate-700'"
                                            @click="toggleOption(option.value)"
                                        >
                                            <span x-text="option.label"></span>
                                            <svg x-show="selected.includes(option.value)" class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div x-show="channel === 'topmatch'">
                            <x-input-label for="sub_leader_id_tm" value="Pilih Marketing Utama Topmatch (opsional)" />
                            <select id="sub_leader_id_tm" name="leader_id" class="mt-1 block w-full text-sm">
                                <option value="">-- Semua / Tidak Terikat --</option>
                                @foreach ($leadersTopmatch as $tmLeader)
                                    <option value="{{ $tmLeader->id }}">{{ $tmLeader->name }} ({{ $tmLeader->email }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <x-primary-button>Simpan Asisten Marketing</x-primary-button>
                </form>
            </div>

            {{-- ================================================================
                 TAMBAH TIM
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[60]">
                <h3 class="section-title">Tambah Tim</h3>
                <form class="mt-4 space-y-4" method="POST" action="{{ route('superadmin.teams.store') }}">
                    @csrf
                    <div>
                        <x-input-label for="team_name" value="Nama Tim" />
                        <x-text-input id="team_name" name="name" class="mt-1 block w-full" required />
                    </div>
                    <x-primary-button>Simpan Tim</x-primary-button>
                </form>
            </div>

            {{-- ================================================================
                 DAFTAR MARKETING UTAMA — TOPLOKER
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[50]">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                        Toploker
                    </span>
                    <h3 class="section-title mb-0">Daftar Marketing Utama Toploker</h3>
                </div>
                <div class="table-wrap mt-4 !overflow-visible">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Tim</th>
                                <th>Jumlah Asisten (tim sama)</th>
                                <th>Ubah Tim</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-colspan="6">
                            @forelse ($leaders as $leader)
                                <tr data-user-row="leader-{{ $leader->id }}">
                                    <td>{{ $leader->name }}</td>
                                    <td>{{ $leader->email }}</td>
                                    <td>{{ $leader->team?->name ?? '-' }}</td>
                                    <td>{{ $leader->sub_leaders_count }}</td>
                                    <td>
                                        <form class="flex gap-2" method="POST" action="{{ route('superadmin.users.assign-team', $leader) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="team_id" class="text-sm">
                                                <option value="">-- Tidak ada --</option>
                                                @foreach ($teams as $team)
                                                    <option value="{{ $team->id }}" @selected($leader->team_id === $team->id)>{{ $team->name }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn-subtle px-3 py-2 text-xs">Update</button>
                                        </form>
                                    </td>
                                    <td class="text-right">
                                        <button
                                            type="button"
                                            title="Hapus User"
                                            aria-label="Hapus User"
                                            class="btn-danger inline-flex h-10 w-10 items-center justify-center p-0"
                                            onclick="openDeleteUserModal(@js([
                                                'id' => $leader->id,
                                                'name' => $leader->name,
                                                'username' => $leader->email,
                                                'role' => 'Marketing Utama Toploker',
                                                'deleteUrl' => route('superadmin.users.destroy', $leader),
                                                'rowId' => 'leader-' . $leader->id,
                                            ]))"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4h6v2"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-slate-500">Belum ada Marketing Utama Toploker.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ================================================================
                 DAFTAR MARKETING UTAMA — TOPMATCH
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[40]">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                        Topmatch
                    </span>
                    <h3 class="section-title mb-0">Daftar Marketing Utama Topmatch</h3>
                </div>
                <p class="mt-1 text-xs text-slate-500">Marketing Utama Topmatch dapat melihat semua nomor. Status hubungi hanya tercatat di channel Topmatch.</p>
                <div class="table-wrap mt-4 !overflow-visible">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Target Harian</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-colspan="4">
                            @forelse ($leadersTopmatch as $leader)
                                <tr data-user-row="leader-{{ $leader->id }}">
                                    <td>{{ $leader->name }}</td>
                                    <td>{{ $leader->email }}</td>
                                    <td>{{ number_format(\App\Models\User::TARGET_TOPMATCH) }} nomor/hari</td>
                                    <td class="text-right">
                                        <button
                                            type="button"
                                            title="Hapus User"
                                            aria-label="Hapus User"
                                            class="btn-danger inline-flex h-10 w-10 items-center justify-center p-0"
                                            onclick="openDeleteUserModal(@js([
                                                'id' => $leader->id,
                                                'name' => $leader->name,
                                                'username' => $leader->email,
                                                'role' => 'Marketing Utama Topmatch',
                                                'deleteUrl' => route('superadmin.users.destroy', $leader),
                                                'rowId' => 'leader-' . $leader->id,
                                            ]))"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4h6v2"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-slate-500">Belum ada Marketing Utama Topmatch.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ================================================================
                 DAFTAR MARKETING UTAMA — KERJA MALAM
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[30]">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                        Kerja Malam
                    </span>
                    <h3 class="section-title mb-0">Daftar Marketing Utama Kerja Malam</h3>
                </div>
                <p class="mt-1 text-xs text-slate-500">Marketing Utama Kerja Malam dapat melihat semua nomor. Status hubungi hanya tercatat di channel Kerja Malam.</p>
                <div class="table-wrap mt-4 !overflow-visible">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Target Harian</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-colspan="4">
                            @forelse ($leadersKerjaMalam as $leader)
                                <tr data-user-row="leader-{{ $leader->id }}">
                                    <td>{{ $leader->name }}</td>
                                    <td>{{ $leader->email }}</td>
                                    <td>{{ number_format(\App\Models\User::TARGET_KERJA_MALAM) }} nomor/hari</td>
                                    <td class="text-right">
                                        <button
                                            type="button"
                                            title="Hapus User"
                                            aria-label="Hapus User"
                                            class="btn-danger inline-flex h-10 w-10 items-center justify-center p-0"
                                            onclick="openDeleteUserModal(@js([
                                                'id' => $leader->id,
                                                'name' => $leader->name,
                                                'username' => $leader->email,
                                                'role' => 'Marketing Utama Kerja Malam',
                                                'deleteUrl' => route('superadmin.users.destroy', $leader),
                                                'rowId' => 'leader-' . $leader->id,
                                            ]))"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4h6v2"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-slate-500">Belum ada Marketing Utama Kerja Malam.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ================================================================
                 DAFTAR ASISTEN MARKETING  (Toploker only)
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[20]">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                        Toploker
                    </span>
                    <h3 class="section-title mb-0">Daftar Asisten Marketing</h3>
                </div>
                <div class="table-wrap mt-4 !overflow-visible">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Tim</th>
                                <th>Ubah Tim</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-colspan="5">
                            @forelse ($subLeaders as $subLeader)
                                <tr data-user-row="subLeader-{{ $subLeader->id }}">
                                    <td>{{ $subLeader->name }}</td>
                                    <td>{{ $subLeader->email }}</td>
                                    <td>
                                        @if($subLeader->teams->count() > 0)
                                            {{ $subLeader->teams->pluck('name')->join(', ') }}
                                        @else
                                            {{ $subLeader->team?->name ?? '-' }}
                                        @endif
                                    </td>
                                    <td>
                                        <form class="flex items-start gap-2" method="POST" action="{{ route('superadmin.users.assign-team', $subLeader) }}">
                                            @csrf
                                            @method('PATCH')
                                            
                                            <div x-data="{
                                                open: false,
                                                options: [
                                                    @foreach($teams as $team)
                                                        { value: '{{ (string)$team->id }}', label: '{{ addslashes($team->name) }}' },
                                                    @endforeach
                                                ],
                                                selected: @js($subLeader->teams->count() > 0 ? $subLeader->teams->pluck('id')->map(fn($id) => (string)$id)->toArray() : ($subLeader->team_id ? [(string)$subLeader->team_id] : [])),
                                                get selectedOptions() {
                                                    return this.options.filter(o => this.selected.includes(o.value));
                                                },
                                                toggleOption(val) {
                                                    if (this.selected.includes(val)) {
                                                        this.selected = this.selected.filter(i => i !== val);
                                                    } else {
                                                        this.selected.push(val);
                                                    }
                                                }
                                            }" class="relative w-64" :class="open ? 'z-50' : ''" @click.away="open = false">
                                                
                                                <div class="flex flex-wrap items-center gap-1 min-h-[36px] px-2 py-1 border border-slate-300 rounded-md bg-white cursor-pointer w-full text-xs shadow-sm" @click="open = !open">
                                                    <template x-for="option in selectedOptions" :key="option.value">
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-slate-100 text-slate-700 border border-slate-200 rounded font-medium">
                                                            <span x-text="option.label"></span>
                                                            <button type="button" class="text-slate-400 hover:text-slate-600 focus:outline-none" @click.stop="toggleOption(option.value)">
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                            </button>
                                                        </span>
                                                    </template>
                                                    <div x-show="selected.length === 0" class="text-slate-400 py-0.5">-- Pilih Tim --</div>
                                                </div>
                                                
                                                <select name="team_ids[]" multiple required class="absolute opacity-0 h-0 w-0 pointer-events-none" tabindex="-1">
                                                    <template x-for="option in options" :key="option.value">
                                                        <option :value="option.value" :selected="selected.includes(option.value)"></option>
                                                    </template>
                                                </select>
                                                
                                                <div x-show="open" 
                                                     x-transition
                                                     class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-md shadow-lg max-h-48 overflow-y-auto" style="display: none;">
                                                    <template x-for="option in options" :key="option.value">
                                                        <div 
                                                            class="px-2 py-1.5 cursor-pointer hover:bg-slate-50 flex items-center justify-between text-xs"
                                                            :class="selected.includes(option.value) ? 'bg-slate-50 text-slate-900 font-medium' : 'text-slate-700'"
                                                            @click="toggleOption(option.value)"
                                                        >
                                                            <span x-text="option.label"></span>
                                                            <svg x-show="selected.includes(option.value)" class="h-3 w-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                            <button class="btn-subtle px-3 py-2 text-xs shrink-0 h-[36px]">Update</button>
                                        </form>
                                    </td>
                                    <td class="text-right">
                                        <button
                                            type="button"
                                            title="Hapus User"
                                            aria-label="Hapus User"
                                            class="btn-danger inline-flex h-10 w-10 items-center justify-center p-0"
                                            onclick="openDeleteUserModal(@js([
                                                'id' => $subLeader->id,
                                                'name' => $subLeader->name,
                                                'username' => $subLeader->email,
                                                'role' => 'Asisten Marketing',
                                                'deleteUrl' => route('superadmin.users.destroy', $subLeader),
                                                'rowId' => 'subLeader-' . $subLeader->id,
                                            ]))"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4h6v2"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-slate-500">Belum ada Asisten Marketing Toploker.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ================================================================
                 DAFTAR ASISTEN MARKETING — TOPMATCH
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[15]">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/></svg>
                        Topmatch
                    </span>
                    <h3 class="section-title mb-0">Daftar Asisten Marketing Topmatch</h3>
                </div>
                <p class="mt-1 text-xs text-slate-500">Asisten Marketing Topmatch menginput nomor telepon dan nama perusahaan untuk channel Topmatch.</p>
                <div class="table-wrap mt-4 !overflow-visible">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Marketing Utama Terikat</th>
                                <th>Target Harian</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody data-colspan="5">
                            @forelse ($subLeadersTopmatch as $subLeader)
                                <tr data-user-row="subLeader-{{ $subLeader->id }}">
                                    <td>{{ $subLeader->name }}</td>
                                    <td>{{ $subLeader->email }}</td>
                                    <td>
                                        <form class="flex gap-2" method="POST" action="{{ route('superadmin.users.assign-team', $subLeader) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="leader_id" class="text-xs">
                                                <option value="">-- Semua / Tidak Terikat --</option>
                                                @foreach ($leadersTopmatch as $tmLeader)
                                                    <option value="{{ $tmLeader->id }}" @selected($subLeader->leader_id === $tmLeader->id)>{{ $tmLeader->name }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn-subtle px-2 py-1 text-xs">Update</button>
                                        </form>
                                    </td>
                                    <td>{{ number_format(\App\Models\User::TARGET_SUB_LEADER) }} nomor/hari</td>
                                    <td class="text-right">
                                        <button
                                            type="button"
                                            title="Hapus User"
                                            aria-label="Hapus User"
                                            class="btn-danger inline-flex h-10 w-10 items-center justify-center p-0"
                                            onclick="openDeleteUserModal(@js([
                                                'id' => $subLeader->id,
                                                'name' => $subLeader->name,
                                                'username' => $subLeader->email,
                                                'role' => 'Asisten Marketing Topmatch',
                                                'deleteUrl' => route('superadmin.users.destroy', $subLeader),
                                                'rowId' => 'subLeader-' . $subLeader->id,
                                            ]))"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M3 6h18"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4h6v2"></path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-slate-500">Belum ada Asisten Marketing Topmatch.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ================================================================
                 DAFTAR TIM
                 ================================================================ --}}
            <div class="panel fade-in-up relative z-[10]">
                <h3 class="section-title">Daftar Tim</h3>
                <div class="table-wrap mt-4 !overflow-visible">
                    <table class="table-clean">
                        <thead>
                            <tr>
                                <th>Nama Tim</th>
                                <th>Jumlah Anggota</th>
                                <th>Jumlah Marketing Utama</th>
                                <th>Jumlah Asisten Marketing</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($teams as $team)
                                <tr>
                                    <td>{{ $team->name }}</td>
                                    <td>{{ $team->members_count }}</td>
                                    <td>{{ $team->leaders_count }}</td>
                                    <td>{{ $team->sub_leaders_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-slate-500">Belum ada tim.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="toastContainer" class="fixed top-4 right-4 z-50 flex flex-col items-end gap-3"></div>

    <x-modal name="confirm-user-deletion" focusable maxWidth="md">
        <div class="p-6">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-red-600">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 8v4"></path>
                    <path d="M12 16h.01"></path>
                    <path d="M10 3h4"></path>
                    <path d="M7 6h10"></path>
                </svg>
            </div>
            <div class="mt-4 text-center">
                <h3 class="text-xl font-semibold text-slate-900">Hapus Data User</h3>
                <p class="mt-2 text-sm text-slate-600">Apakah Anda yakin ingin menghapus data user ini? Tindakan ini tidak dapat dibatalkan dan seluruh data terkait user akan dihapus secara permanen.</p>
            </div>
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                <p class="font-semibold text-slate-900">Nama : <span id="deleteUserName"></span></p>
                <p class="mt-2">Username : <span id="deleteUserUsername"></span></p>
                <p class="mt-2">Role : <span id="deleteUserRole"></span></p>
            </div>
            <div id="deleteUserError" class="mt-4 hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"></div>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button type="button" class="btn-subtle w-full sm:w-auto" onclick="closeDeleteUserModal()">Batal</button>
                <button type="button" id="confirmDeleteButton" class="btn-danger w-full sm:w-auto inline-flex items-center justify-center gap-2" onclick="submitDeleteUser()" data-default-text="Hapus Permanen">
                    <span class="button-text">Hapus Permanen</span>
                </button>
            </div>
        </div>
    </x-modal>

    <script>
        const deleteUserState = {
            selectedUser: null,
            csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        };

        function openDeleteUserModal(user) {
            deleteUserState.selectedUser = user;
            document.getElementById('deleteUserName').textContent = user.name ?? '-';
            document.getElementById('deleteUserUsername').textContent = user.username ?? '-';
            document.getElementById('deleteUserRole').textContent = user.role ?? '-';
            const errorElement = document.getElementById('deleteUserError');
            errorElement.classList.add('hidden');
            errorElement.textContent = '';
            const confirmButton = document.getElementById('confirmDeleteButton');
            confirmButton.disabled = false;
            confirmButton.innerHTML = '<span class="button-text">Hapus Permanen</span>';
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'confirm-user-deletion' }));
        }

        function closeDeleteUserModal() {
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'confirm-user-deletion' }));
        }

        async function submitDeleteUser() {
            const user = deleteUserState.selectedUser;
            if (!user) {
                return;
            }

            const button = document.getElementById('confirmDeleteButton');
            button.disabled = true;
            button.innerHTML = '<svg class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg><span>Menghapus...</span>';

            try {
                const response = await fetch(user.deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': deleteUserState.csrfToken,
                        'Accept': 'application/json',
                    },
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok || !payload?.ok) {
                    const errorMessage = payload?.message || 'Terjadi kesalahan saat menghapus data user.';
                    throw new Error(errorMessage);
                }

                closeDeleteUserModal();
                removeUserRow(user.rowId);
                showToast('Berhasil', 'Data user berhasil dihapus.', 'success');
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Terjadi kesalahan saat menghapus data user.';
                const errorElement = document.getElementById('deleteUserError');
                errorElement.textContent = message;
                errorElement.classList.remove('hidden');
                showToast('Gagal', message, 'error');
            } finally {
                button.disabled = false;
                button.innerHTML = '<span class="button-text">Hapus Permanen</span>';
            }
        }

        function removeUserRow(rowId) {
            const row = document.querySelector(`[data-user-row="${rowId}"]`);
            if (!row) {
                return;
            }

            const tbody = row.closest('tbody');
            row.remove();

            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                const placeholder = document.createElement('tr');
                placeholder.innerHTML = `<td colspan="${tbody.dataset.colspan}" class="px-4 py-4 text-slate-500">Belum ada user.</td>`;
                tbody.appendChild(placeholder);
            }
        }

        function showToast(title, message, type) {
            const container = document.getElementById('toastContainer');
            if (!container) {
                return;
            }

            const toast = document.createElement('div');
            const isSuccess = type === 'success';
            toast.className = 'max-w-sm rounded-2xl border px-4 py-3 shadow-xl transition duration-300';
            toast.classList.add(
                isSuccess ? 'border-emerald-200' : 'border-rose-200',
                isSuccess ? 'bg-emerald-50' : 'bg-rose-50',
                isSuccess ? 'text-emerald-700' : 'text-rose-700'
            );
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            toast.style.transition = 'opacity 220ms ease, transform 220ms ease';
            toast.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="mt-0.5">
                        ${isSuccess ? '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"></path></svg>' : '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'}
                    </div>
                    <div>
                        <p class="font-semibold">${title}</p>
                        <p class="mt-1 text-sm">${message}</p>
                    </div>
                </div>
            `;

            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateY(0)';
            });

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    toast.remove();
                }, 220);
            }, 4200);
        }
    </script>
</x-app-layout>
