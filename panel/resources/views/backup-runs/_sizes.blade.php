@php($parsed = $parsed ?? $run->parsedLog())
@php($cloud = $parsed['cloud'])
@php($artifacts = $parsed['artifacts'])
@php($restic = $parsed['restic'])
@php($hasCloud = $cloud['total'] || $cloud['free'] || $cloud['used'])
@php($hasSizes = $hasCloud || $artifacts !== [] || $restic)

@if($parsed['insufficient_storage'] ?? false)
    <div class="mb-4 rounded-xl border px-4 py-3 text-sm alert-error">
        <strong>Недостаточно места на Яндекс.Диске</strong> (ошибка 507).
        Освободите место в облаке или смените аккаунт — это не нехватка RAM на сервере.
        @if($cloud['free'])
            Сейчас свободно: {{ $cloud['free'] }} из {{ $cloud['total'] ?? '?' }}.
        @endif
    </div>
@endif

@if($hasSizes)
    <div id="run-sizes" class="card p-4 sm:p-6 mb-4">
        <h2 class="section-title !text-base !mb-3">Размеры и облако</h2>

        @if($hasCloud)
            <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4 text-sm">
                @if($cloud['total'])
                    <div><dt class="text-slate-400">Всего на Диске</dt><dd class="font-medium">{{ $cloud['total'] }}</dd></div>
                @endif
                @if($cloud['used'])
                    <div><dt class="text-slate-400">Занято</dt><dd class="font-medium">{{ $cloud['used'] }}</dd></div>
                @endif
                @if($cloud['free'])
                    <div><dt class="text-slate-400">Свободно</dt><dd class="font-medium {{ $parsed['insufficient_storage'] ? 'text-red-700' : 'text-brand-700' }}">{{ $cloud['free'] }}</dd></div>
                @endif
                @if($cloud['trashed'])
                    <div><dt class="text-slate-400">В корзине</dt><dd class="font-medium">{{ $cloud['trashed'] }}</dd></div>
                @endif
            </dl>
        @endif

        @if($artifacts !== [] || $restic)
            <div class="table-wrap">
                <table class="data-table text-sm">
                    <thead>
                        <tr>
                            <th>Артефакт</th>
                            <th>Размер</th>
                            <th>В облаке</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($artifacts as $item)
                            <tr>
                                <td>
                                    @if($item['type'] === 'db')
                                        Дамп БД <code class="text-xs">{{ $item['name'] }}</code>
                                    @else
                                        Tar проекта <code class="text-xs">{{ $item['name'] }}</code>
                                    @endif
                                </td>
                                <td class="font-mono">{{ $item['human'] }}</td>
                                <td>
                                    @if($item['uploaded'])
                                        <span class="badge badge-success">да</span>
                                    @else
                                        <span class="badge badge-error">нет</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        @if($restic)
                            <tr>
                                <td>Restic (файлы сайта)</td>
                                <td class="font-mono">
                                    {{ $restic['stored'] ?? $restic['added'] ?? '—' }}
                                    @if($restic['added'] && $restic['stored'])
                                        <span class="text-slate-400 text-xs block">исходно {{ $restic['added'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($restic['files'])
                                        <span class="text-slate-500 text-xs">{{ number_format($restic['files']) }} файлов</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
