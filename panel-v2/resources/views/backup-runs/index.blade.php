@extends('layouts.app')

@section('title', 'История бэкапов')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">История бэкапов</h1>
        <p class="page-subtitle">Все запуски — открывайте лог любого прогона</p>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">← Панель</a>
</div>

<div class="flex flex-wrap gap-2 mb-6">
    <a href="{{ route('backup-runs.index') }}" class="btn {{ $filterStatus === '' ? 'btn-primary' : 'btn-secondary' }} !py-2 !text-sm">Все</a>
    <a href="{{ route('backup-runs.index', ['status' => 'running']) }}" class="btn {{ $filterStatus === 'running' ? 'btn-primary' : 'btn-secondary' }} !py-2 !text-sm">Идут</a>
    <a href="{{ route('backup-runs.index', ['status' => 'completed']) }}" class="btn {{ $filterStatus === 'completed' ? 'btn-primary' : 'btn-secondary' }} !py-2 !text-sm">Готово</a>
    <a href="{{ route('backup-runs.index', ['status' => 'failed']) }}" class="btn {{ $filterStatus === 'failed' ? 'btn-primary' : 'btn-secondary' }} !py-2 !text-sm">Ошибки</a>
</div>

<div class="card overflow-hidden divide-y divide-slate-100">
    @forelse ($runs as $run)
        <a href="{{ route('backup-runs.show', $run) }}" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5 no-underline text-inherit hover:bg-slate-50">
            <div class="min-w-0">
                <div class="font-semibold text-slate-900">
                    #{{ $run->id }}
                    <span class="font-medium text-slate-700">{{ $run->server?->name ?? '—' }}</span>
                </div>
                <div class="text-xs text-slate-400 mt-0.5">
                    {{ $run->started_at?->format('d.m.Y H:i:s') ?? $run->created_at?->format('d.m.Y H:i:s') }}
                    @if ($run->finished_at)
                        → {{ $run->finished_at->format('H:i:s') }}
                    @endif
                </div>
            </div>
            <div class="shrink-0">
                @if ($run->status === 'completed')
                    <span class="badge badge-success">готово</span>
                @elseif ($run->status === 'running')
                    <span class="badge bg-blue-100 text-blue-800">идёт</span>
                @elseif ($run->status === 'pending')
                    <span class="badge badge-info">ожидание</span>
                @else
                    <span class="badge" style="background:#fee2e2;color:#b91c1c">ошибка</span>
                @endif
            </div>
        </a>
    @empty
        <div class="p-8 text-center text-slate-500 text-sm">Пока нет запусков</div>
    @endforelse
</div>

<div class="mt-6">
    {{ $runs->links() }}
</div>
@endsection
