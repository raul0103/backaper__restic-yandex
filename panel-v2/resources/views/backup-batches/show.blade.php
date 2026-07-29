@extends('layouts.app')

@section('title', 'Очередь #'.$batch->id)

@section('content')
<div class="mb-8 flex flex-wrap items-start justify-between gap-4">
    <div>
        <div class="flex flex-wrap items-center gap-3 mb-2">
            <h1 class="page-title !text-2xl">Очередь #{{ $batch->id }}</h1>
            <span id="batch-badge" class="badge
                @if($batch->status === 'completed') badge-success
                @elseif($batch->status === 'running' || $batch->status === 'pending') bg-blue-100 text-blue-800
                @else badge-error
                @endif">{{ $batch->statusLabel() }}</span>
        </div>
        <p class="page-subtitle">
            {{ $batch->modeLabel() }}
            · проверка SSH каждые {{ (int) round($batch->poll_seconds / 60) }} мин
            · {{ $batch->created_at?->format('d.m.Y H:i') }}
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($batch->isActive())
            <form method="post" action="{{ route('backup-batches.cancel', $batch) }}" onsubmit="return confirm('Отменить очередь? Текущий бэкап на сервере может продолжаться.');">
                @csrf
                <button type="submit" class="btn btn-secondary text-red-600">Отменить</button>
            </form>
        @endif
        <a href="{{ route('backup-batches.create') }}" class="btn btn-secondary">Новая очередь</a>
    </div>
</div>

<div id="batch-message" class="mb-6 rounded-xl border px-4 py-3 text-sm
    @if($batch->isActive()) bg-blue-50 border-blue-200 text-blue-800
    @elseif($batch->status === 'completed') alert-success
    @else alert-error
    @endif">
    {{ $batch->message ?: '…' }}
</div>

<section class="card p-0 overflow-hidden mb-6">
    <div class="px-4 sm:px-6 py-3 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900">Серверы в очереди</h2>
    </div>
    <ul id="batch-items" class="divide-y divide-slate-100">
        @foreach ($batch->items as $item)
            <li class="px-4 sm:px-6 py-4 flex flex-wrap items-start justify-between gap-3" data-item-id="{{ $item->id }}">
                <div>
                    <div class="font-semibold text-slate-900">{{ $item->server?->name }}</div>
                    <div class="text-xs font-mono text-slate-400 mt-0.5">{{ $item->server?->ssh_user }}@{{ $item->server?->host }}</div>
                    <div class="item-msg text-sm text-slate-500 mt-1">{{ $item->message }}</div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="item-badge badge
                        @if($item->status === 'completed') badge-success
                        @elseif($item->status === 'running') bg-blue-100 text-blue-800
                        @elseif($item->status === 'pending') badge-info
                        @else badge-error
                        @endif">{{ $item->statusLabel() }}</span>
                    @if ($item->backup_run_id)
                        <a href="{{ route('backup-runs.show', $item->backup_run_id) }}" class="text-sm text-brand-700 underline run-link">лог</a>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>

<p class="text-xs text-slate-400 mb-4">
    Страница обновляет статус из БД каждые 10 с. SSH-проверка «закончился ли бэкап» — по расписанию очереди ({{ (int) round($batch->poll_seconds / 60) }} мин).
</p>

<a href="{{ route('dashboard') }}" class="text-brand-700 font-medium text-sm underline">← Панель</a>

@if ($batch->isActive())
<script>
(function () {
    const statusUrl = @json(route('backup-batches.status', $batch));
    const badgeEl = document.getElementById('batch-badge');
    const msgEl = document.getElementById('batch-message');
    const POLL_MS = 10000;

    function badgeClass(status) {
        if (status === 'completed') return 'badge badge-success';
        if (status === 'running' || status === 'pending') return 'badge bg-blue-100 text-blue-800';
        return 'badge badge-error';
    }

    function itemBadgeClass(status) {
        if (status === 'completed') return 'badge badge-success';
        if (status === 'running') return 'badge bg-blue-100 text-blue-800';
        if (status === 'pending' || status === 'skipped') return 'badge badge-info';
        return 'badge badge-error';
    }

    function msgClass(active, status) {
        if (active) return 'mb-6 rounded-xl border px-4 py-3 text-sm bg-blue-50 border-blue-200 text-blue-800';
        if (status === 'completed') return 'mb-6 rounded-xl border px-4 py-3 text-sm alert-success';
        return 'mb-6 rounded-xl border px-4 py-3 text-sm alert-error';
    }

    function apply(data) {
        badgeEl.textContent = data.status_label;
        badgeEl.className = badgeClass(data.status);
        msgEl.textContent = data.message || '…';
        msgEl.className = msgClass(data.active, data.status);

        (data.items || []).forEach(function (item) {
            const li = document.querySelector('[data-item-id="' + item.id + '"]');
            if (!li) return;
            const b = li.querySelector('.item-badge');
            const m = li.querySelector('.item-msg');
            if (b) {
                b.textContent = item.status_label;
                b.className = 'item-badge ' + itemBadgeClass(item.status);
            }
            if (m) m.textContent = item.message || '';
            let link = li.querySelector('.run-link');
            if (item.run_url) {
                if (!link) {
                    link = document.createElement('a');
                    link.className = 'text-sm text-brand-700 underline run-link';
                    link.textContent = 'лог';
                    li.querySelector('.flex.items-center')?.appendChild(link);
                }
                link.href = item.run_url;
            }
        });

        return data.active;
    }

    function poll() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (apply(data)) {
                    setTimeout(poll, POLL_MS);
                }
            })
            .catch(function () { setTimeout(poll, POLL_MS); });
    }

    setTimeout(poll, POLL_MS);
})();
</script>
@endif
@endsection
