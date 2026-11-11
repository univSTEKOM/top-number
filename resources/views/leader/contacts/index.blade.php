<x-app-layout>
    <div id="toast-container" class="fixed right-4 top-4 z-50 flex flex-col gap-2 items-end pointer-events-none" style="position:fixed;top:1rem;right:1rem;z-index:9999;"></div>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                        Rekap Nomor Marketing Utama
                    </h2>
                    @php
                        $channelBadgeClass = match($channelLabel) {
                            'Topmatch'   => 'bg-violet-100 text-violet-700',
                            'Kerja Malam' => 'bg-amber-100 text-amber-700',
                            default      => 'bg-blue-100 text-blue-700',
                        };
                    @endphp
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $channelBadgeClass }}">
                        {{ $channelLabel }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    @if ($channelLabel === 'Topmatch')
                        Pantau dan tindak lanjuti kontak perusahaan dari asisten marketing Topmatch.
                    @elseif ($isSpecialChannel)
                        Lihat & kelola status nomor dari semua asisten marketing Toploker.
                    @else
                        Pantau semua input nomor dari asisten marketing di bawah kamu.
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="page-wrap space-y-6">
            <div class="panel fade-in-up">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        @if ($isSpecialChannel)
                            <h3 class="section-title">Filter &amp; Pencarian Nomor</h3>
                            <p class="section-subtitle">Tampilan status berdasarkan channel <strong>{{ $channelLabel }}</strong> saja.</p>
                        @else
                            <h3 class="section-title">Asisten Marketing Saya</h3>
                            <p class="section-subtitle">Pilih asisten marketing untuk melihat data yang lebih spesifik.</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('leader.contacts.export', request()->only('sub_leader_id', 'period', 'start_date', 'end_date', 'q', 'status')) }}" class="btn-main">Export CSV</a>
                    </div>
                </div>

                <form method="GET" class="mt-4 space-y-3" x-data="{ period: '{{ $filters['period'] ?? 'all' }}' }">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <input
                            type="search"
                            name="q"
                            value="{{ $uiFilters['q'] ?? '' }}"
                            placeholder="Cari nama, nomor..."
                            autocomplete="off"
                            aria-label="Cari data kontak"
                            class="xl:col-span-2"
                        />
                        @if (!$isSpecialChannel || $channelLabel === 'Topmatch')
                        <select name="sub_leader_id" aria-label="Pilih asisten marketing">
                            <option value="">Semua Asisten Marketing</option>
                            @foreach ($subLeaders as $subLeader)
                                <option value="{{ $subLeader->id }}" @selected($selectedSubLeaderId === $subLeader->id)>
                                    {{ $subLeader->name }}
                                </option>
                            @endforeach
                        </select>
                        @endif
                        <select name="status" aria-label="Pilih status">
                            <option value="all" @selected(($uiFilters['status'] ?? 'all') === 'all')>Semua Status</option>
                            <option value="contacted" @selected(($uiFilters['status'] ?? 'all') === 'contacted')>Sudah Dihubungi</option>
                            <option value="uncontacted" @selected(($uiFilters['status'] ?? 'all') === 'uncontacted')>Belum Dihubungi</option>
                        </select>
                        <select name="per_page" aria-label="Jumlah data per halaman">
                            <option value="10" @selected(($uiFilters['per_page'] ?? 20) === 10)>10 / halaman</option>
                            <option value="20" @selected(($uiFilters['per_page'] ?? 20) === 20)>20 / halaman</option>
                            <option value="50" @selected(($uiFilters['per_page'] ?? 20) === 50)>50 / halaman</option>
                            <option value="100" @selected(($uiFilters['per_page'] ?? 20) === 100)>100 / halaman</option>
                        </select>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @php
                            $baseQuery = request()->except(['period', 'start_date', 'end_date', 'page']);
                        @endphp
                        <a href="{{ route('leader.contacts.index', array_merge($baseQuery, ['period' => 'all'])) }}" class="chip {{ ($filters['period'] ?? 'all') === 'all' ? 'border-blue-200 bg-blue-50 text-blue-700' : '' }}">Semua Data</a>
                        <a href="{{ route('leader.contacts.index', array_merge($baseQuery, ['period' => '7d'])) }}" class="chip {{ ($filters['period'] ?? '') === '7d' ? 'border-blue-200 bg-blue-50 text-blue-700' : '' }}">7 Hari</a>
                        <a href="{{ route('leader.contacts.index', array_merge($baseQuery, ['period' => '30d'])) }}" class="chip {{ ($filters['period'] ?? '') === '30d' ? 'border-blue-200 bg-blue-50 text-blue-700' : '' }}">1 Bulan</a>
                        <button type="button" class="chip" @click="period = 'custom'">Range Tanggal</button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3" x-show="period === 'custom' || '{{ $filters['period'] ?? 'all' }}' === 'custom'">
                        <input type="hidden" name="period" :value="period" />
                        <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}" aria-label="Tanggal mulai" />
                        <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}" aria-label="Tanggal akhir" />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit" class="btn-main">Terapkan</button>
                        @if ($selectedSubLeaderId || ($filters['period'] ?? 'all') !== 'all' || ($filters['start_date'] ?? null) || ($filters['end_date'] ?? null) || ($uiFilters['q'] ?? null) || ($uiFilters['status'] ?? 'all') !== 'all' || ($uiFilters['per_page'] ?? 20) !== 20)
                            <a href="{{ route('leader.contacts.index') }}" class="btn-subtle">Reset Semua Filter</a>
                        @endif
                    </div>
                </form>

                @if (!$isSpecialChannel || $channelLabel === 'Topmatch')
                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        href="{{ route('leader.contacts.index', request()->only('period', 'start_date', 'end_date')) }}"
                        class="chip {{ $selectedSubLeaderId ? '' : 'border-blue-200 bg-blue-50 text-blue-700' }}"
                    >
                        Semua Asisten Marketing
                    </a>
                    @forelse ($subLeaders as $subLeader)
                        <a
                            href="{{ route('leader.contacts.index', array_merge(request()->only('period', 'start_date', 'end_date'), ['sub_leader_id' => $subLeader->id])) }}"
                            class="chip {{ $selectedSubLeaderId === $subLeader->id ? 'border-blue-200 bg-blue-50 text-blue-700' : '' }}"
                        >
                            {{ $subLeader->name }} ({{ $subLeader->contacts_entered_count }} nomor)
                        </a>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada asisten marketing yang ditugaskan.</p>
                    @endforelse
                </div>
                @else
                <div class="mt-3 rounded-xl border border-amber-100 bg-amber-50 px-4 py-2 text-xs text-amber-700">
                    <strong>Info:</strong> Status "Sudah Dihubungi" yang ditampilkan hanya mencerminkan aktivitas channel <strong>{{ $channelLabel }}</strong>. Nomor yang sudah dihubungi oleh channel lain tetap tampil sebagai belum dihubungi di sini.
                </div>
                @endif
            </div>



            <div class="panel fade-in-up">
                <h3 class="section-title">Daftar Nomor</h3>
                <p class="section-subtitle">Total ditemukan: <strong id="total-found-count">{{ number_format($contacts->total()) }}</strong> data.</p>
                @if ($selectedSubLeaderId)
                    <p class="section-subtitle">
                        Menampilkan data dari:
                        <strong>{{ $subLeaders->firstWhere('id', $selectedSubLeaderId)?->name ?? '-' }}</strong>
                    </p>
                @endif
                @if (($filters['period'] ?? 'all') !== 'all' || ($filters['start_date'] ?? null) || ($filters['end_date'] ?? null))
                    <p class="section-subtitle">
                        Filter tanggal:
                        @if (($filters['period'] ?? 'all') === '7d')
                            <strong>7 Hari Terakhir</strong>
                        @elseif (($filters['period'] ?? 'all') === '30d')
                            <strong>1 Bulan Terakhir</strong>
                        @elseif (($filters['period'] ?? 'all') === 'custom')
                            <strong>{{ $filters['start_date'] ?? '-' }} s/d {{ $filters['end_date'] ?? '-' }}</strong>
                        @else
                            <strong>Custom</strong>
                        @endif
                    </p>
                @endif
                <div class="table-wrap mt-4">
                    <table class="table-clean">
                        <thead>
                                <tr>
                                    <th>{{ $channelLabel === 'Topmatch' ? 'Nama Perusahaan' : 'Nama Kontak' }}</th>
                                    <th>Nomor
                                        <button id="bulk-copy-btn" type="button" title="Bulk Copy + Mark as Contacted" class="ml-2 inline-flex h-6 w-6 items-center justify-center rounded border border-slate-300 bg-white text-slate-700 transition hover:bg-slate-100" data-bulk-url="{{ route('leader.contacts.bulk-status') }}" aria-label="Bulk Copy and Mark as Contacted">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M9 4H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-2" />
                                                <path d="M9 4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2" />
                                                <path d="M9 12h6" />
                                            </svg>
                                        </button>
                                    </th>
                                    <th>Input Oleh</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                    <th>Tanggal Dihubungi</th>
                                </tr>
                        </thead>
                        <tbody>
                            @forelse ($contacts as $contact)
                                @php
                                    // Use channel-specific contacted status from the map
                                    $isContactedInChannel = $channelContactedMap[$contact->id] ?? false;
                                    $statusLabelText = $isContactedInChannel ? 'Sudah Dihubungi' : 'Belum Dihubungi';
                                @endphp
                                <tr data-contact-id="{{ $contact->id }}" data-phone="{{ $contact->phone }}">
                                    <td>{{ $contact->contact_name ?? '-' }}</td>
                                    <td class="font-medium">{{ $contact->phone }}</td>
                                    <td>
                                        @if ($contact->subLeader)
                                            {{ $contact->subLeader->name }}
                                        @elseif ($contact->inputBy && $contact->inputBy->role === \App\Models\User::ROLE_SUPERADMIN)
                                            {{ $contact->inputBy->name }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        <span class="contact-status-label">{{ $statusLabelText }}</span>
                                    </td>
                                    <td>{{ $contact->created_at->format('d M Y H:i') }}</td>
                                    <td>
                                        <input
                                            type="checkbox"
                                            class="contact-marked"
                                            data-url="{{ route('leader.contacts.status', $contact) }}"
                                            data-contact-id="{{ $contact->id }}"
                                            data-status-updated-at="{{ optional($contact->status_updated_at)->format('Y-m') }}"
                                            aria-label="Tandai sudah dihubungi"
                                            @checked($isContactedInChannel)
                                        />
                                    </td>
                                    <td class="contacted-at-cell">
                                        @php
                                            $channelContactedAt = $channelContactedDateMap[$contact->id] ?? null;
                                        @endphp
                                        {{ $channelContactedAt ? \Carbon\Carbon::parse($channelContactedAt)->format('d M Y H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-4 text-slate-500">Belum ada data nomor.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $contacts->links() }}
                </div>
            </div>

            <div class="panel fade-in-up">
                <h3 class="section-title">Grafik Tracking Per Bulan</h3>
                <p class="section-subtitle">Jumlah nomor yang sudah dihubungi per bulan.</p>
                <div class="mt-4">
                    <div id="chartContainer">Loading chart...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const data = @json($monthlyContactedData);

        const container = document.getElementById('chartContainer');
        if (container) {
            if (data.length === 0) {
                container.innerHTML = 'Tidak ada data untuk ditampilkan.';
            } else {
                const canvas = document.createElement('canvas');
                canvas.width = 400;
                canvas.height = 200;
                container.innerHTML = '';
                container.appendChild(canvas);

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.map(item => item.label),
                        datasets: [{
                            label: 'Nomor Dihubungi',
                            data: data.map(item => item.count),
                            backgroundColor: 'rgba(59, 130, 246, 0.5)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const toastContainer = document.getElementById('toast-container');
        const totalFoundEl = document.getElementById('total-found-count');
        const currentMonth = new Date().toISOString().slice(0, 7);
        const currentStatusFilter = new URLSearchParams(window.location.search).get('status') || 'all';

        const parseInteger = (element) => {
            if (!element) {
                return 0;
            }

            return parseInt(element.textContent.replace(/[^0-9]/g, ''), 10) || 0;
        };

        const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(value);

        const showToast = (message, type) => {
            if (!toastContainer) {
                return;
            }

            const toast = document.createElement('div');
            toast.className = `rounded-lg px-4 py-3 text-sm font-semibold shadow-lg transition-all pointer-events-auto ${type === 'success' ? 'bg-emerald-500 text-white' : 'bg-red-600 text-white'}`;
            toast.textContent = message;
            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        };



        let totalFoundCount = parseInteger(totalFoundEl);

        const updateTotalFoundCount = (delta) => {
            if (!totalFoundEl) {
                return;
            }

            const nextValue = Math.max(totalFoundCount + delta, 0);
            totalFoundCount = nextValue;
            totalFoundEl.textContent = formatNumber(nextValue);
        };

        const removeRowFromDom = (row) => {
            if (!row || !row.parentNode) {
                return;
            }

            row.parentNode.removeChild(row);
        };

        const insertRowToDom = (row, parent, nextSibling) => {
            if (!row || !parent) {
                return;
            }

            parent.insertBefore(row, nextSibling);
        };

        const formatDateTime = (date) => {
            if (!date) {
                return '-';
            }

            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        };



        // Bulk copy + bulk update handler
        const bulkBtn = document.getElementById('bulk-copy-btn');
        let bulkLock = false;
        if (bulkBtn) {
            bulkBtn.addEventListener('click', async () => {
                if (bulkLock) return;
                bulkLock = true;

                const bulkUrl = bulkBtn.dataset.bulkUrl;
                const rows = Array.from(document.querySelectorAll('table tbody tr'));
                const phones = [];
                const ids = [];
                const rowData = [];

                rows.forEach((row) => {
                    const phone = row.dataset.phone?.trim() ?? '';
                    const id = row.dataset.contactId ? parseInt(row.dataset.contactId, 10) : null;
                    if (phone && id) {
                        phones.push(phone);
                        ids.push(id);
                        rowData.push({ row, id, phone });
                    }
                });

                if (phones.length === 0) {
                    showToast('Tidak ada nomor pada halaman ini untuk dicopy.', 'error');
                    bulkLock = false;
                    return;
                }

                const payloadText = phones.join('\n');

                try {
                    await navigator.clipboard.writeText(payloadText);
                } catch (err) {
                    showToast('Gagal menyalin ke clipboard. Pastikan browser mengizinkan clipboard.', 'error');
                    bulkLock = false;
                    return;
                }

                // Optimistically update UI (checkboxes, labels, highlight) after successful copy
                const prevStates = [];
                let newlyCheckedCount = 0;

                rowData.forEach(({ row }) => {
                    const cb = row.querySelector('.contact-marked');
                    const statusLabel = row.querySelector('.contact-status-label');
                    const contactedAtCell = row.querySelector('.contacted-at-cell');
                    const prevChecked = !!(cb && cb.checked);
                    const parent = row.parentNode;
                    const nextSibling = row.nextSibling;
                    const removed = currentStatusFilter === 'uncontacted' && parent;

                    prevStates.push({ row, cb, prevChecked, removed, parent, nextSibling });

                    if (cb && !prevChecked) {
                        cb.checked = true;
                        if (statusLabel) statusLabel.textContent = 'Sudah Dihubungi';
                        if (contactedAtCell) contactedAtCell.textContent = formatDateTime(new Date());
                        row.classList.add('bg-emerald-50');
                        newlyCheckedCount += 1;

                        cb.dataset.statusUpdatedAt = currentMonth;

                        if (currentStatusFilter === 'uncontacted') {
                            setTimeout(() => {
                                removeRowFromDom(row);
                            }, 2000);
                        }
                    }
                });


                if (currentStatusFilter === 'uncontacted') {
                    updateTotalFoundCount(-newlyCheckedCount);
                }

                // Persist to backend
                try {
                    const resp = await fetch(bulkUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ contact_ids: ids }),
                    });

                    if (!resp.ok) throw new Error('HTTP error');

                    const body = await resp.json();
                    if (!body.ok) throw new Error('Backend error');

                    const updated = body.updated ?? ids.length;
                    showToast(`Berhasil copy & update ${updated} nomor`, 'success');
                } catch (err) {
                    // revert UI
                    prevStates.forEach(({ row, cb, prevChecked, removed, parent, nextSibling }) => {
                        if (removed) {
                            insertRowToDom(row, parent, nextSibling);
                        }

                        if (cb) cb.checked = prevChecked;
                        const statusLabel = row.querySelector('.contact-status-label');
                        if (statusLabel) statusLabel.textContent = prevChecked ? 'Sudah Dihubungi' : 'Belum Dihubungi';
                        row.classList.remove('bg-emerald-50');
                    });
                    if (currentStatusFilter === 'uncontacted') {
                        updateTotalFoundCount(newlyCheckedCount);
                    }
                    // adjust summary back

                    showToast('Gagal memperbarui status pada server. Perubahan dibatalkan.', 'error');
                } finally {
                    bulkLock = false;
                }
            });
        }

        document.querySelectorAll('.contact-marked').forEach((checkbox) => {
            checkbox.addEventListener('change', async (ev) => {
                const cb = ev.currentTarget;
                const url = cb.dataset.url;
                const row = cb.closest('tr');
                const statusLabel = row?.querySelector('.contact-status-label');
                const contactedAtCell = row?.querySelector('.contacted-at-cell');
                const previousChecked = !cb.checked;
                const previousStatusUpdatedAt = cb.dataset.statusUpdatedAt || '';
                const nextChecked = cb.checked;

                cb.disabled = true;

                try {
                    const response = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ is_contacted: nextChecked }),
                    });

                    if (!response.ok) {
                        throw new Error('Gagal memperbarui status');
                    }

                    const payload = await response.json();

                    if (!payload.ok) {
                        throw new Error('Gagal memperbarui status');
                    }

                    if (statusLabel) {
                        statusLabel.textContent = payload.label ?? (nextChecked ? 'Sudah Dihubungi' : 'Belum Dihubungi');
                    }

                    if (contactedAtCell) {
                        contactedAtCell.textContent = nextChecked ? formatDateTime(new Date()) : '-';
                    }

                    if (nextChecked && !previousChecked) {

                        cb.dataset.statusUpdatedAt = currentMonth;

                        if (currentStatusFilter === 'uncontacted') {
                            setTimeout(() => {
                                removeRowFromDom(row);
                            }, 2000);
                            updateTotalFoundCount(-1);
                        }
                    }

                    if (!nextChecked && previousChecked) {

                        cb.dataset.statusUpdatedAt = '';
                    }

                    showToast('Status berhasil disimpan.', 'success');
                } catch (error) {
                    cb.checked = previousChecked;
                    showToast('Gagal memperbarui status. Silakan coba lagi.', 'error');
                } finally {
                    cb.disabled = false;
                }
            });
        });
    </script>
</x-app-layout>
